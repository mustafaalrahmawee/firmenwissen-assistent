# Firmenwissen-Assistent (Advanced RAG + MCP)

Portfolio-Projekt: Ein Multi-Tenant-Wissensassistent für IT-KMU. Teams laden Dokumente hoch, stellen Fragen im Chat und bekommen Antworten mit Quellen. Dasselbe Wissen ist über einen MCP-Server in KI-Clients (Claude, Cursor, VS Code) nutzbar. Alles läuft lokal per Docker Compose, ohne externe KI-API.

**Ziel:** In fünf Minuten zeigen, dass ich Advanced RAG, MCP, Laravel und Nuxt sauber beherrsche. Das Projekt muss nicht verkaufbar sein.

---

## 1. Stack

| Bereich | Technik |
|---|---|
| API | Laravel (Sanctum, Queues, Pest) |
| Frontend | Nuxt |
| Relationale DB | MySQL 8 (Quelle der Wahrheit) |
| Vektor-DB | Weaviate (Multi-Tenancy, Hybrid-Suche) |
| Embeddings | Ollama lokal, Modell `bge-m3` (mehrsprachig) |
| Reranking | Weaviate `reranker-transformers` (lokaler Cross-Encoder) |
| Antwort-LLM | Ollama lokal (Modell noch festlegen) |
| MCP | MCP-Server in Laravel (`laravel/mcp`, aktuellen Stand prüfen) |
| Umgebung | Windows + WSL Ubuntu, Docker Compose |

---

## 2. MVP-Umfang

### Enthalten

- **Anmeldung und Arbeitsbereiche:** Login mit Sanctum, jeder Nutzer gehört zu genau einem Tenant, Daten sind strikt getrennt. Nutzer können sich mit E-Mail und Passwort selbst registrieren; für die Live-Demo gibt es zusätzlich einen fertigen Demo-Zugang.
- **Dokumenten-Upload:** PDF und Markdown. Verarbeitung (Text extrahieren, zerteilen, einbetten, indexieren) läuft über Queues mit sichtbarem Status.
- **RAG-Chat:** Gestreamte Antworten mit Quellen und Sprung zur Textstelle.
- **Advanced RAG:** Anfrage umschreiben, Hybrid-Suche, Reranking, Kontext erweitern (Details im Anchor unter `docs/anchor/rag`).
- **MCP-Server:** Tools `search_knowledge`, `get_document`, `list_sources`, mit Token pro Nutzer.
- **Auswertung:** Testset mit Fragen; Vergleich nur Vektor / Hybrid / Hybrid mit Reranking.
- **Tests und CI:** Pest-Tests für die API, GitHub Actions.

### Nicht enthalten

- Abrechnung und Abos
- Anbindung an Confluence, SharePoint, Jira
- Aufwendige Rollenverwaltung
- Admin-Dashboard

---

## 3. Architektur

```
Nuxt (web) ──HTTP──> Laravel API ──> MySQL      (Tenants, Dokumente, Chats)
                         │
KI-Client ──MCP────────> │ ────────> Weaviate   (Chunks, Hybrid-Suche)
                         │               └────> Reranker (Stufe 2)
                         │
                    Queue-Worker ──> Ollama     (Embeddings, Antworten)
```

### Aufteilung der Daten

- **MySQL:** `tenants`, `users`, `documents`, `chunks` (Text, Position, Seite), `chats`, `messages`, `mcp_tokens`, Verarbeitungsstatus.
- **Weaviate:** Eine Collection `Chunk` mit aktivierter Multi-Tenancy. Pro Objekt: Text, `document_id`, `chunk_id`, Seite, Überschrift, Vektor.

### Regeln

1. Der Tenant-Name in Weaviate ist die Tenant-ID aus MySQL.
2. Der Tenant kommt immer aus dem angemeldeten Nutzer oder dem MCP-Token, nie aus der Anfrage.
3. Weaviate ist nur ein Suchindex und muss sich jederzeit aus MySQL und den Dateien neu aufbauen lassen.
4. Embedding-Modell und Dimension stehen in der Konfiguration und werden pro Dokument gespeichert. Ein Modellwechsel bedeutet Neuaufbau des Index.
5. Laravel bettet selbst über Ollama ein und schickt fertige Vektoren an Weaviate (Vectorizer `none`).

---

## 4. RAG-Pipeline (Kurzfassung)

Die ausführliche Beschreibung steht im Anchor (`docs/anchor/rag`).

1. **Anfrage aufbereiten:** Folgefrage per LLM in eigenständige Suchanfrage umschreiben.
2. **Stufe 1, Hybrid-Suche:** 30 bis 50 Kandidaten aus dem Tenant (BM25 plus Vektor).
3. **Stufe 2, Reranking:** Cross-Encoder sortiert neu, die besten 5 bis 8 bleiben.
4. **Kontext erweitern:** Nachbar-Chunk oder übergeordneten Abschnitt aus MySQL dazuladen.
5. **Antwort mit Quellen:** LLM antwortet nur aus dem Kontext und nennt die Chunk-IDs.
6. **Messen:** Testset vergleicht die Varianten; Ergebnis als Tabelle im README.

---

## 5. MCP-Tools

| Tool | Eingabe | Ausgabe |
|---|---|---|
| `search_knowledge` | `query`, optional `limit` | Treffer mit Text, Dokument, Seite, Score |
| `get_document` | `document_id` | Metadaten und Text des Dokuments |
| `list_sources` | (keine) | Alle Dokumente des Tenants mit Status |

Alle Tools nutzen dieselbe Suchlogik wie der Chat und sind über das Token an genau einen Tenant gebunden.

---

## 6. Ordnerstruktur

```
knowledge-rag/
├── api/                  # Laravel
├── web/                  # Nuxt
├── docker/               # Dockerfiles, Init-Skripte
├── docs/
│   ├── anchor/           # Anchor-Dokumente (rag, mcp, ...)
│   └── PROJEKT.md
├── eval/                 # Testfragen und Auswertungsskript
├── docker-compose.yml
└── README.md
```

Das Projekt im Linux-Dateisystem von WSL ablegen (z. B. `/home/musta/projects/knowledge-rag`), nicht unter `/mnt/c`. Sonst sind die gemounteten Ordner sehr langsam.

---

## 7. Docker Compose (Skizze)

Nicht getestet. Vor dem ersten Start die Platzhalter `<...>` durch feste Versionen ersetzen.

```yaml
name: knowledge-rag

services:
  mysql:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: app
      MYSQL_ROOT_PASSWORD: root
    ports: ["127.0.0.1:3307:3306"]
    volumes: [mysqldata:/var/lib/mysql]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      retries: 10

  weaviate:
    image: cr.weaviate.io/semitechnologies/weaviate:<version-pinnen>
    environment:
      PERSISTENCE_DATA_PATH: /var/lib/weaviate
      DEFAULT_VECTORIZER_MODULE: none
      ENABLE_MODULES: reranker-transformers
      RERANKER_INFERENCE_API: http://reranker:8080
      AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED: "true"   # nur lokal
      CLUSTER_HOSTNAME: node1
    ports: ["127.0.0.1:8080:8080"]
    volumes: [weaviatedata:/var/lib/weaviate]
    depends_on: [reranker]

  reranker:
    image: cr.weaviate.io/semitechnologies/reranker-transformers:<modell-tag>

  ollama:
    image: ollama/ollama:<version-pinnen>
    ports: ["127.0.0.1:11434:11434"]
    volumes: [ollamadata:/root/.ollama]

  ollama-init:
    image: ollama/ollama:<version-pinnen>
    entrypoint: ["ollama", "pull", "bge-m3"]
    environment:
      OLLAMA_HOST: http://ollama:11434
    depends_on: [ollama]
    restart: "no"

  api:
    build: ./api            # PHP-FPM oder FrankenPHP
    volumes: [./api:/app]
    ports: ["127.0.0.1:8000:8000"]
    depends_on:
      mysql: { condition: service_healthy }
      weaviate: { condition: service_started }
      ollama: { condition: service_started }

  worker:
    build: ./api
    command: php artisan queue:work
    volumes: [./api:/app]
    depends_on: [api]

  web:
    image: node:24-bookworm-slim
    working_dir: /app
    command: npm run dev -- --host 0.0.0.0
    volumes: [./web:/app]
    ports: ["127.0.0.1:3000:3000"]
    depends_on: [api]

volumes:
  mysqldata:
  weaviatedata:
  ollamadata:
```

### Hinweise zum Compose

- **Netzwerk:** Kein Host-Networking. Dienste sprechen sich über ihre Namen an (`http://ollama:11434`, `http://weaviate:8080`, `mysql:3306`).
- **Tests:** Zweiten Dienst `weaviate-test` ohne Volume ergänzen (wie `chroma-test` in job-match) und eine eigene Test-Datenbank in MySQL anlegen.
- **Healthchecks:** Für Weaviate und Ollama noch ergänzen, damit die API erst startet, wenn alles bereit ist.
- **GPU:** Ohne GPU läuft Ollama auf der CPU; für die Demo reicht das. Mit NVIDIA-Karte das NVIDIA Container Toolkit in WSL einrichten und im Dienst `ollama` die GPU freigeben.
- **Reranker:** Für deutsche Dokumente einen mehrsprachigen Cross-Encoder wählen.
- **Weaviate aus PHP:** Über den Laravel-HTTP-Client (REST/GraphQL) ansprechen und in einer eigenen Service-Klasse kapseln. Vorher prüfen, ob es ein gepflegtes PHP-Paket gibt.

---

## 8. Zeitplan (4 bis 6 Wochen nebenbei)

1. **Woche 1:** Compose, Laravel-API, Login, Tenants, Upload.
2. **Woche 2:** Verarbeitung per Queue, Embeddings mit Ollama, Weaviate-Schema, Hybrid-Suche.
3. **Woche 3:** Reranking, Kontext-Erweiterung, Nuxt-Chat mit Streaming und Quellen.
4. **Woche 4:** MCP-Server und Test mit einem KI-Client.
5. **Woche 5 bis 6:** Auswertung, Tests, CI, Deployment, README, Demo-Video.

---

## 9. Was am Ende im Portfolio steht

- Live-Demo mit Demo-Zugang und vorgeladenen Beispieldokumenten
- Video (ca. 2 Minuten): KI-Client greift über MCP auf das Wissen zu
- README mit Architektur-Diagramm, Entscheidungen mit Begründung und bekannten Grenzen
- Auswertungstabelle: nur Vektor / Hybrid / Hybrid mit Reranking

---

## 10. Nächste Schritte

- [ ] Ordner anlegen und Git-Repository starten
- [ ] Versionen im Compose festlegen und `docker compose up` testen
- [ ] Laravel in `api/` und Nuxt in `web/` aufsetzen
- [ ] MySQL-Migrationen für Tenants, Nutzer, Dokumente, Chunks
- [ ] Weaviate-Collection `Chunk` mit Multi-Tenancy anlegen
- [ ] Service-Klassen: `OllamaClient`, `WeaviateClient`, `Retriever`
- [ ] Antwort-LLM für Ollama auswählen
