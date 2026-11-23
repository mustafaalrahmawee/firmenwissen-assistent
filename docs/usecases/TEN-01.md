# Use-Case-Blatt: TEN-01 · Arbeitsbereich

- **Use Case:** TEN-01, Arbeitsbereich: jeder Nutzer gehört zu einem Tenant, Daten strikt getrennt, MVP (Woche 1)
- **Baut auf:** AUTH-01
- **Status:** fertig
- **Code:** `api/app/Models/Tenant.php`, `api/database/migrations/2026_11_23_081339_create_tenants_table.php`, `api/database/migrations/2026_11_23_081341_add_tenant_id_to_users_table.php`, `api/app/Console/Commands/CreateDemoUser.php`, `api/app/Http/Resources/TenantResource.php`, `api/app/Http/Resources/UserResource.php`, `api/app/Http/CurrentTenant.php`, `api/app/Http/Middleware/SetCurrentTenant.php`, `api/bootstrap/app.php`, `api/routes/api.php`, `api/tests/Feature/Http/Middleware/SetCurrentTenantTest.php`, `app/app/stores/auth.ts`, `app/app/layouts/auth.vue`

Das Blatt hält fest, was der Use Case fachlich bedeutet, in welchen Schritten er gebaut wird (ein Schritt ist ein Endpunkt) und was dafür entschieden wurde. Alles mit dem Vermerk `Person` schreibt die Person selbst; der Schreiber trägt es wortgleich ein.

## 1. Ticket

**Worum es geht:** Den Assistenten nutzen mehrere Firmen nebeneinander. Jede Firma hat ihren eigenen Arbeitsbereich, und jede Person gehört zu genau einem. Wer angemeldet ist, arbeitet immer in seinem Arbeitsbereich, sieht, welcher das ist, und erreicht nur, was dort liegt. Was einer anderen Firma gehört, gibt es für ihn nicht. Auf dieser Trennung bauen später Dokumente, Suche, Chat und der Zugang für KI-Clients auf.

**Wer es braucht und warum:** Alle Mitarbeitenden eines IT-Betriebs, die internes Wissen in den Assistenten legen, und die Besucher der Live-Demo. Ohne Arbeitsbereiche lägen die Dokumente aller Firmen in einem Topf: Eine Frage im Chat könnte mit dem Angebot oder der Kundenakte einer fremden Firma beantwortet werden. Niemand würde dann noch etwas Vertrauliches hochladen. Und wenn jeder spätere Teil der Anwendung die Trennung für sich neu lösen müsste, würde früher oder später einer sie vergessen.

**Beispiel:** Jana Petrov und Tobias Kern arbeiten bei der Nordlicht IT GmbH und gehören beide zum Arbeitsbereich „Nordlicht IT GmbH“. Mehmet Aydin arbeitet bei der Hafenkante Systemhaus GmbH, die einen eigenen Arbeitsbereich hat. Jana meldet sich an und sieht in der Anwendung, dass sie im Arbeitsbereich „Nordlicht IT GmbH“ arbeitet; Tobias sieht dasselbe, Mehmet sieht „Hafenkante Systemhaus GmbH“. Mehmet ist neugierig und schickt bei einer Anfrage von Hand die Kennung von Janas Arbeitsbereich mit. Die Anwendung beachtet das nicht: Er bleibt in seinem Arbeitsbereich und erfährt nicht einmal, ob es den anderen gibt. Ein Besucher der Live-Demo meldet sich mit dem Demo-Zugang an und landet im Demo-Arbeitsbereich, getrennt von beiden Firmen.

**Was schiefgehen kann:**

- Es gibt ein Konto ohne Arbeitsbereich, zum Beispiel den Demo-Zugang aus der Zeit, bevor es Arbeitsbereiche gab.
- Jemand nennt in einer Anfrage einen anderen Arbeitsbereich als seinen eigenen, um dort hineinzukommen.
- Jemand ruft gezielt etwas auf, das zu einem fremden Arbeitsbereich gehört, und will an der Antwort erkennen, ob es das gibt.
- Ein späterer Teil der Anwendung (Dokumente, Suche, Chat) vergisst, auf den eigenen Arbeitsbereich einzuschränken.
- Der Demo-Zugang wird ein zweites Mal angelegt oder zurückgesetzt, oder zwei solche Läufe überschneiden sich.
- Zwei Firmen tragen denselben Namen.
- Jana ist auf zwei Geräten angemeldet.
- Der Server ist nicht erreichbar, während die Anwendung den Arbeitsbereich anzeigen will.
- Der Suchindex ist nicht erreichbar oder kennt einen Arbeitsbereich noch nicht.

**Abnahme:**

- [ ] Jede Person gehört zu genau einem Arbeitsbereich. Ein Konto ohne Arbeitsbereich kann nicht entstehen, und kein bestehendes Konto bleibt ohne.
- [ ] Nach der Anmeldung zeigt die Anwendung, in welchem Arbeitsbereich man arbeitet, auf jedem Gerät denselben.
- [ ] Zwei Personen derselben Firma sehen denselben Arbeitsbereich, Personen verschiedener Firmen jeweils nur ihren eigenen.
- [ ] Der Arbeitsbereich ergibt sich allein aus der Anmeldung. Nennt eine Anfrage einen anderen, ändert das nichts.
- [ ] Wer etwas aus einem fremden Arbeitsbereich aufruft, bekommt dieselbe Antwort wie bei etwas, das es nicht gibt.
- [ ] Der Demo-Zugang gehört zu einem Demo-Arbeitsbereich, ohne dass jemand von Hand etwas in der Datenbank anlegt. Ein zweiter Lauf ergibt keinen zweiten Demo-Arbeitsbereich.
- [ ] Zwei Arbeitsbereiche mit demselben Namen bleiben getrennt.
- [ ] Jeder Arbeitsbereich hat eine Kennung, die sich nie ändert. Unter derselben Kennung liegen später seine Inhalte im Suchindex.
- [ ] Spätere Teile der Anwendung haben einen einheitlichen Weg, ihre Daten auf den Arbeitsbereich der angemeldeten Person zu beschränken, und ein Test zeigt, dass er Fremdes ausschließt.
- [ ] Ist der Server nicht erreichbar, verhält sich die Anwendung wie in AUTH-01 festgelegt, statt einen leeren oder falschen Arbeitsbereich zu zeigen.

**Nicht Teil:** Selbst registrieren und dabei einen Arbeitsbereich bekommen gehört zu AUTH-02. Kolleginnen und Kollegen einladen, den Arbeitsbereich wechseln, umbenennen oder löschen, Rollen und Rechte innerhalb eines Arbeitsbereichs. Dokumente (DOC-01 bis DOC-03), Suche (RAG-01) und Chat (CHAT-01) wenden die Trennung auf ihre eigenen Daten an. Der Zugang für KI-Clients und seine Bindung an einen Arbeitsbereich gehören zu MCP-01. Den Suchindex neu aufbauen gehört zu SYS-01.

**Begriffe:**

- **Arbeitsbereich (Tenant):** der abgeschlossene Bereich einer Firma im Assistenten. Alles, was die Firma hochlädt oder fragt, gehört dazu.
- **Fremder Arbeitsbereich:** jeder Arbeitsbereich, zu dem die angemeldete Person nicht gehört.
- **Kennung:** das unveränderliche Merkmal, an dem die Anwendung einen Arbeitsbereich erkennt, unabhängig von seinem Namen.
- **Demo-Arbeitsbereich:** der Arbeitsbereich des Demo-Zugangs aus AUTH-01.
- **Suchindex:** der Teil der Anwendung, in dem später die Textstücke der Dokumente zum Durchsuchen liegen.

## 2. Schnitt

| Nr | Schritt | Was er tut | Oberfläche | Deckt ab | Status |
| --- | --- | --- | --- | --- | --- |
| S1 | Befehl `app:create-demo-user` (erweitert) | Legt mit dem Demo-Zugang auch den Demo-Arbeitsbereich an und ordnet ihm den Zugang zu; ab jetzt gehört jedes Konto zu genau einem Arbeitsbereich. | entfällt – läuft im Terminal | genau ein Arbeitsbereich je Konto, kein neues und kein bestehendes Konto ohne; Demo-Zugang im Demo-Arbeitsbereich ohne Handarbeit; zweiter oder gleichzeitiger Lauf ergibt keinen zweiten Demo-Arbeitsbereich; Kennung ändert sich nie; gleiche Namen bleiben getrennt | fertig |
| S2 | `GET /api/v1/user` (erweitert) | Sagt der Anwendung zusätzlich, in welchem Arbeitsbereich die angemeldete Person arbeitet. | Name des Arbeitsbereichs in der Seitenleiste | Anwendung zeigt den Arbeitsbereich; gleiche Firma sieht denselben, andere Firma nur ihren eigenen; auf jedem Gerät derselbe; ein anderer Arbeitsbereich in der Anfrage ändert nichts; Server nicht erreichbar wie in AUTH-01, kein leerer Arbeitsbereich | fertig |
| S3 | Middleware `tenant` | Legt für jede geschützte Anfrage den Arbeitsbereich allein aus der Anmeldung fest und beschränkt die Daten darauf; Fremdes sieht aus wie nicht vorhanden. | entfällt – wirkt nur im Server, erste Nutzer sind DOC-01 und folgende | Arbeitsbereich allein aus der Anmeldung, Angabe in der Anfrage wird nicht beachtet; Fremdes ergibt dieselbe Antwort wie Nicht-Vorhandenes; einheitlicher Weg für spätere Teile, damit keiner die Einschränkung vergisst, mit Test, der Fremdes ausschließt | fertig |

**Nicht abgedeckt:** Der Fall „Suchindex nicht erreichbar oder kennt den Arbeitsbereich noch nicht“ und die zweite Hälfte von „Unter derselben Kennung liegen später seine Inhalte im Suchindex“. In TEN-01 gibt es noch keinen Suchindex; beides gehört zu DOC-02 (Indexieren) und RAG-01 (Suchen). TEN-01 sorgt nur für die unveränderliche Kennung (S1).

## 3. Entscheidungen

### E1 · S1 · Welche Kennung bekommt ein Arbeitsbereich?

**Warum sich die Frage stellt:** Jeder Arbeitsbereich braucht eine Kennung, die sich nie ändert, und unter genau dieser Kennung liegen später seine Inhalte im Suchindex. Alle späteren Daten (Dokumente, Chats, Zugänge für KI-Clients) verweisen auf sie.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Fortlaufende Zahl, wie bei den Konten (`id`). | Wie überall im Projekt, kein Zusatzaufwand; Verweise in späteren Tabellen sind Zahlen. Nach dem Neuaufsetzen der Datenbank und in jedem Test beginnt die Zählung wieder bei 1: Ein Suchindex mit altem Stand ordnet dessen Inhalte dem neuen Arbeitsbereich 1 zu, er muss dann geleert oder neu aufgebaut werden (SYS-01), und Tests gegen `weaviate-test` müssen ihre Arbeitsbereiche dort selbst aufräumen. Die Zahl verrät ungefähr, wie viele Arbeitsbereiche es gibt. |
| 2 | Zufällige, sortierbare Kennung (ULID) als Schlüssel des Arbeitsbereichs. | Eindeutig auch nach dem Neuaufsetzen der Datenbank und zwischen Testläufen; ein alter Indexstand trifft nie einen neuen Arbeitsbereich. Verrät nichts über die Anzahl. Verweise in allen späteren Tabellen sind dann Zeichenketten mit 26 Zeichen statt Zahlen, das muss jede spätere Migration und Factory beachten. |

**Wahl (Person):** E1 Vorschlag 2

### E2 · S1 · Was geschieht mit Konten, die es schon vor den Arbeitsbereichen gab?

**Warum sich die Frage stellt:** Kein bestehendes Konto darf ohne Arbeitsbereich bleiben. In der Entwicklungsdatenbank gibt es heute zwei Konten, eines davon ist der Demo-Zugang, und mehrere Anmeldungen.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Die Migration legt für jedes bestehende Konto einen eigenen Arbeitsbereich mit dem Namen des Kontos an und ordnet es ihm zu; erst danach wird die Zuordnung Pflicht. | Kein Konto und keine Anmeldung geht verloren, und bestehende Konten sind voneinander getrennt wie verschiedene Firmen. Auch der Demo-Zugang hat danach einen Arbeitsbereich aus der Migration; ob der Befehl ihn übernimmt, hängt an E3. Etwas Datencode in der Migration, mit Test. |
| 2 | Die Migration legt einen gemeinsamen Arbeitsbereich an (etwa „Bestand“) und ordnet ihm alle bestehenden Konten zu. | Kein Konto geht verloren, der Datencode ist kleiner. Alle bisherigen Konten sehen später dieselben Dokumente, auch der Demo-Zugang und das andere Konto. Nimmt der Befehl den Arbeitsbereich des Demo-Zugangs (E3 · 1), wird „Bestand“ zum Demo-Arbeitsbereich und das andere Konto liegt darin. |
| 3 | Die Migration bricht mit einer Meldung ab, wenn es schon Konten gibt. Vorher wird die Datenbank geleert (`migrate:fresh`) und der Demo-Zugang mit dem Befehl neu angelegt. | Kein Datencode, die Zuordnung ist von Anfang an Pflicht. Alle bisherigen Konten und Anmeldungen der Entwicklungsdatenbank verschwinden; das geht nur, weil es noch keinen Betrieb mit echten Konten gibt. |

**Wahl (Person):** E2 Vorschalg 3

### E3 · S1 · Woran erkennt der Befehl den Demo-Arbeitsbereich?

**Warum sich die Frage stellt:** Ein zweiter Lauf darf keinen zweiten Demo-Arbeitsbereich anlegen. Am Namen lässt er sich nicht erkennen, weil zwei Arbeitsbereiche denselben Namen tragen dürfen und eine echte Firma so heißen könnte. Später liegen im Demo-Arbeitsbereich die Beispieldokumente der Live-Demo.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Über den Demo-Zugang: Gibt es das Konto schon, bleibt sein Arbeitsbereich der Demo-Arbeitsbereich, und der Befehl setzt dessen Namen zurück; sonst legt er einen neuen an. | Keine zusätzliche Spalte. Wird `DEMO_USER_EMAIL` geändert, entsteht mit dem neuen Konto ein neuer, leerer Demo-Arbeitsbereich; der alte bleibt mit seinen Beispieldokumenten beim alten Konto liegen. |
| 2 | Der Arbeitsbereich trägt eine Kennzeichnung als Demo-Arbeitsbereich (zusätzliche Spalte, höchstens einer). Der Befehl sucht ihn, legt ihn sonst an und setzt den Demo-Zugang hinein. | Wird `DEMO_USER_EMAIL` geändert, kommt der neue Demo-Zugang in denselben Arbeitsbereich mit seinen Beispieldokumenten. Ein bestehender Demo-Zugang in einem anderen Arbeitsbereich wird in den gekennzeichneten verschoben. Dafür braucht die Tabelle der Arbeitsbereiche eine zusätzliche Spalte. |

**Wahl (Person):** E3 Vorschlag 1

### E4 · S3 · Wie beschränken spätere Teile ihre Daten auf den Arbeitsbereich?

**Warum sich die Frage stellt:** Später kommen Dokumente, Textstücke, Chats und Zugänge für KI-Clients dazu, jeweils mit Arbeitsbereich. Es soll einen einheitlichen Weg geben, damit kein Teil die Einschränkung vergisst, und Fremdes soll dieselbe Antwort ergeben wie etwas, das es nicht gibt.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Ein globaler Scope: Jedes Modell mit Arbeitsbereich bekommt ein Trait. Jede Eloquent-Abfrage filtert dann von selbst auf den aktuellen Arbeitsbereich, und neue Datensätze bekommen ihn von selbst. | Ein Controller kann die Einschränkung nicht vergessen; ein fremder Datensatz in der Adresse ergibt ohne weiteren Code 404. Ohne aktuellen Arbeitsbereich (Befehl, Job) wirft die Abfrage einen Fehler, statt alles zu liefern; dort muss der Code einen Arbeitsbereich setzen oder den Scope bewusst abschalten. Abfragen an Eloquent vorbei (`DB::table`, Weaviate) filtert er nicht. Die Wirkung steht nicht in der Abfrage selbst, das ist beim Lesen und bei der Fehlersuche weniger sichtbar. |
| 2 | Ausdrücklich über die Beziehung: Jede Abfrage geht vom aktuellen Arbeitsbereich aus, zum Beispiel `$tenant->documents()->findOrFail($id)`; die Middleware stellt den aktuellen Arbeitsbereich dafür bereit. | Die Einschränkung steht sichtbar in jeder Abfrage, ohne versteckte Wirkung in Befehlen und Jobs. Schreibt ein Controller `Document::find($id)`, gibt es keinen Schutz; das fällt nur im Review oder in einem Test auf. Deshalb braucht jeder spätere Endpunkt einen eigenen Test mit einem fremden Datensatz. |

**Wahl (Person):** E4 Vorschlag 2

**Hinweis des Schreibers:** S3 soll laut Schnitt auch den Fall „Ein späterer Teil der Anwendung vergisst, auf den eigenen Arbeitsbereich einzuschränken“ abdecken. Mit Vorschlag 2 verhindert das kein Code: Schreibt ein späterer Controller `Document::findOrFail($id)` statt über den aktuellen Arbeitsbereich zu suchen, bekommt Mehmet mit der Nummer eines Dokuments der Nordlicht IT GmbH dieses Dokument. Abgesichert ist der Fall dann nur durch einen Test mit fremdem Datensatz in jedem späteren Endpunkt.

### E5 · S3 · Wie kommt die Middleware an die geschützten Routen?

**Warum sich die Frage stellt:** Jede Anfrage, die Daten eines Arbeitsbereichs liest oder schreibt, braucht den aktuellen Arbeitsbereich. Heute gibt es nur `GET /user` und `POST /logout`, später viele Routen für Dokumente, Chat und Auswertung.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Die Middleware hängt an der ganzen Gruppe `auth:sanctum` in `routes/api.php`. | Jede neue geschützte Route hat sie ohne weiteren Eintrag, auch `GET /user` und `POST /logout`, wo sie nichts ändert. Routen für KI-Clients (MCP-01) liegen voraussichtlich in einer eigenen Gruppe und brauchen sie dort ebenfalls. |
| 2 | Jede Route oder Gruppe, die Daten eines Arbeitsbereichs braucht, nennt die Middleware selbst. | Man sieht an jeder Route, dass sie einen Arbeitsbereich braucht. Vergisst eine neue Route den Eintrag, fehlt der aktuelle Arbeitsbereich: Mit E4 · 1 wirft die erste Abfrage einen Fehler (500), mit E4 · 2 bekommt der Controller keinen Arbeitsbereich. |

**Wahl (Person):** E5 Vorschalg 1

### Vom Schreiber entschieden

| Schritt | Was | Entscheidung | Warum |
| --- | --- | --- | --- |
| S1 | Name des Demo-Arbeitsbereichs | Kommt aus der Konfiguration (`DEMO_TENANT_NAME`, Vorgabe `Demo-Arbeitsbereich`) neben `DEMO_USER_*`. | Alle Daten der Demo stehen an einer Stelle, wie der Name des Demo-Zugangs in AUTH-01. |
| S1 | Daten eines Arbeitsbereichs | Nur ein Name (Pflicht, nicht eindeutig) und Zeitstempel. | Gleichnamige Arbeitsbereiche bleiben getrennt; mehr braucht S1 nicht. |
| S1 | Zuordnung des Kontos | Pflicht-Verweis auf den Arbeitsbereich; ein Arbeitsbereich mit Konten lässt sich nicht löschen. | Kein Konto ohne Arbeitsbereich; Löschen ist nicht Teil von TEN-01. |
| S1 | Setzen des Arbeitsbereichs | Die Zuordnung ist nicht massenweise zuweisbar und wird nur über die Beziehung gesetzt. | Der Arbeitsbereich darf nie aus einer Anfrage kommen, auch nicht beim Registrieren in AUTH-02. |
| S1 | Gleichzeitige Läufe | Arbeitsbereich und Konto entstehen in der bestehenden Transaktion; scheitert ein zweiter Lauf an der eindeutigen E-Mail-Adresse, rollt auch sein Arbeitsbereich zurück. | Ein überschneidender Lauf hinterlässt keinen verwaisten zweiten Demo-Arbeitsbereich. |
| S1 | Factory | `UserFactory` legt zu jedem Konto einen eigenen Arbeitsbereich an. | Bestehende Tests bleiben gültig, und zwei Konten aus der Factory sind getrennt wie zwei Firmen. |
| S1 | Beziehungen | Nur „Konto gehört zu Arbeitsbereich“; die Gegenrichtung entsteht erst, wenn ein Schritt sie braucht. | Ein Schritt baut nichts für einen späteren. |
| S2 | Antwort | `tenant` als Objekt mit nur `name` (eigene API Resource), keine Kennung. | Die Seitenleiste braucht nur den Namen; die ULID ist der Name im Suchindex und bleibt im Server. Weitere Felder kommen später ohne Bruch dazu. |
| S2 | Herkunft | Der Arbeitsbereich kommt nur über die Beziehung des angemeldeten Kontos; Abfrageparameter und Inhalt der Anfrage werden nicht gelesen. | Regel aus `CLAUDE.md`; ein Test schickt einen fremden Arbeitsbereich mit. |
| S2 | Ort in der Oberfläche | Name des Arbeitsbereichs als zweite Zeile unter „Firmenwissen“ im Kopf der Seitenleiste, gekürzt mit vollem Namen als Tooltip. | Der Arbeitsbereich gilt für die ganze Anwendung, nicht nur für die Person; der Kopf ist auf jeder Seite sichtbar. |
| S3 | Quelle | Die Middleware nimmt den Arbeitsbereich nur aus dem angemeldeten Konto; Inhalt, Abfrageparameter und Header der Anfrage liest sie nicht. Die Quelle Token für KI-Clients ergänzt MCP-01. | Regel aus `CLAUDE.md`; das Beispiel mit Header aus den Laravel-Regeln wird nicht übernommen. |
| S3 | Ablage | Der aktuelle Arbeitsbereich liegt nur für die laufende Anfrage im Container. Wie ein Job den Arbeitsbereich bekommt, entscheidet DOC-02. | Ein Schritt baut nichts für einen späteren; ein weitergereichter Wert in Jobs wäre eine Entscheidung von DOC-02. |
| S3 | Reihenfolge | Die Middleware läuft nach `auth:sanctum`; ohne Anmeldung antwortet schon diese mit 401. | Kein zweiter Weg für fehlende Anmeldung. |
| S3 | Tests | Geprüft wird mit einer Route und einem Modell samt Tabelle, die es nur im Test gibt. | In TEN-01 gibt es außer den Konten noch keine Daten mit Arbeitsbereich. |

# Offene Punkte

- S1 · Re-Review R3: Zwei wirklich gleichzeitige erste Läufe von `app:create-demo-user` sind nicht getestet. Der Test löst die Kollision der E-Mail-Adresse nur in derselben Transaktion aus (SQLite im Speicher, eine Verbindung); dass am Ende genau ein Demo-Zugang in genau seinem Arbeitsbereich bleibt, ließe sich nur mit zwei Verbindungen auf MySQL prüfen.
- S3 · Review R2: Mit E4 · 2 schützt kein Code vor einer Abfrage am aktuellen Arbeitsbereich vorbei. Jeder spätere Endpunkt mit Daten eines Arbeitsbereichs (ab DOC-01) braucht deshalb einen Test, der einen vorhandenen fremden Datensatz mit einem fehlenden vergleicht.
