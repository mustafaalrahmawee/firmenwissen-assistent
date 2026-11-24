# Use-Case-Blatt: DOC-01 · Dokument hochladen

- **Use Case:** DOC-01, Dokument hochladen (PDF und Markdown), MVP (Woche 1)
- **Baut auf:** TEN-01
- **Status:** fertig
- **Code:** `api/database/migrations/2026_11_23_213910_create_documents_table.php`, `api/app/Models/Document.php`, `api/app/Models/Tenant.php`, `api/database/factories/DocumentFactory.php`, `api/config/documents.php`, `api/app/Http/Requests/StoreDocumentRequest.php`, `api/app/Http/Controllers/DocumentController.php`, `api/app/Http/Resources/DocumentResource.php`, `api/routes/api.php`, `api/app/Providers/AppServiceProvider.php`, `api/tests/Feature/Http/Controllers/DocumentControllerTest.php`, `docker/api/uploads.ini`, `docker-compose.yml`, `app/app/stores/documents.ts`, `app/app/pages/documents.vue`, `app/app/components/DocumentUploadDialog.vue`, `app/app/layouts/auth.vue`

Das Blatt hält fest, was der Use Case fachlich bedeutet, in welchen Schritten er gebaut wird (ein Schritt ist ein Endpunkt) und was dafür entschieden wurde. Alles mit dem Vermerk `Person` schreibt die Person selbst; der Schreiber trägt es wortgleich ein.

## 1. Ticket

**Worum es geht:** Bisher ist jeder Arbeitsbereich leer: Man kann sich anmelden, aber der Assistent weiß nichts über die Firma. Mit diesem Use Case legt eine angemeldete Person ein Dokument in den Arbeitsbereich ihrer Firma, als PDF oder als Markdown-Datei. Danach liegt die Datei unverändert und sicher im Assistenten, gehört zu genau diesem Arbeitsbereich, und die Person hat eine Bestätigung, dass das Dokument angenommen wurde. Durchsuchbar ist es damit noch nicht; darauf bauen Verarbeitung, Liste, Suche und Chat auf.

**Wer es braucht und warum:** Alle Mitarbeitenden eines IT-Betriebs, die Handbücher, Anleitungen, Angebote oder Notizen für die Kolleginnen und Kollegen auffindbar machen wollen, und Besucher der Live-Demo, die den Assistenten mit einer eigenen Datei ausprobieren. Ohne Hochladen bleibt das Wissen in Ordnern und Postfächern einzelner Personen: Wer im Support eine Antwort braucht, fragt weiter herum oder sucht in alten E-Mails, und der Assistent kann zu keiner Frage etwas sagen, weil er nichts kennt.

**Beispiel:** Jana Petrov aus dem Support der Nordlicht IT GmbH hat das „Betriebshandbuch VPN“ als PDF und ihre Notizen zur Einrichtung neuer Laptops als Markdown-Datei. Sie meldet sich an, geht in der Anwendung zu den Dokumenten, wählt das PDF aus und lädt es hoch. Während des Hochladens sieht sie, dass es läuft; danach bestätigt die Anwendung die Annahme mit dem Namen der Datei und sagt, dass das Dokument noch verarbeitet werden muss. Mit der Markdown-Datei macht sie dasselbe. Aus Versehen wählt sie danach eine Word-Datei aus; die Anwendung nimmt sie nicht an und sagt, welche Formate gehen. Tobias Kern lädt am Nachmittag dasselbe Betriebshandbuch noch einmal hoch, weil er nicht weiß, dass Jana es schon getan hat, und erfährt dabei, dass es schon im Arbeitsbereich liegt. Mehmet Aydin von der Hafenkante Systemhaus GmbH lädt in seinem Arbeitsbereich eigene Dokumente hoch; von Janas Dateien erfährt er nichts, auch nicht, wenn eine seiner Dateien denselben Namen oder denselben Inhalt hat.

**Was schiefgehen kann:**

- Die Datei hat ein anderes Format (Word, Bild, Archiv), oder sie trägt nur die Endung eines PDF und ist in Wahrheit etwas anderes.
- Die Datei ist leer oder sehr groß.
- Das PDF ist beschädigt, mit einem Kennwort geschützt oder ein Scan ohne lesbaren Text.
- Der Dateiname enthält Umlaute, Leerzeichen oder Sonderzeichen, ist sehr lang oder enthält Pfadangaben, die auf einen anderen Ort zeigen sollen.
- Jana klickt zweimal schnell auf „Hochladen“ oder wählt mehrere Dateien auf einmal aus.
- Dieselbe Datei wird im selben Arbeitsbereich ein zweites Mal hochgeladen, von derselben oder einer anderen Person.
- Zwei verschiedene Dateien tragen denselben Namen, zum Beispiel eine neue Fassung des Betriebshandbuchs.
- Jana und Tobias laden im selben Augenblick hoch.
- Jemand nennt beim Hochladen von Hand einen fremden Arbeitsbereich, um die Datei dort abzulegen.
- Die Verbindung bricht mitten im Hochladen ab, oder der Server ist nicht erreichbar.
- Die Anmeldung wird ungültig, während die Seite zum Hochladen offen ist.
- Die Ablage ist voll oder nicht beschreibbar, sodass die Datei ankommt, aber nicht gespeichert werden kann.
- Die Dienste für Verarbeitung und Suche sind gerade nicht erreichbar.
- Jemand lädt automatisch sehr viele Dateien hintereinander hoch.

**Abnahme:**

- [ ] Eine angemeldete Person erreicht in der Anwendung eine Seite für Dokumente und kann dort eine PDF- oder Markdown-Datei auswählen und hochladen. Jede Person des Arbeitsbereichs darf das.
- [ ] Die Seite nennt vorab, welche Formate und welche Größe angenommen werden, und zeigt während des Hochladens, dass es läuft.
- [ ] Nach dem Hochladen bestätigt die Anwendung die Annahme mit dem Namen der Datei und sagt, dass das Dokument noch nicht durchsuchbar ist.
- [ ] Das Dokument gehört zum Arbeitsbereich der hochladenden Person; festgehalten ist, wer es wann hochgeladen hat. Nennt die Anfrage einen anderen Arbeitsbereich, ändert das nichts.
- [ ] Die Datei liegt danach unverändert im Assistenten, sodass sie sich später jederzeit erneut verarbeiten lässt.
- [ ] Ein anderes Format oder eine zu große Datei wird mit einer verständlichen Meldung auf Deutsch abgewiesen, ebenso eine Datei, deren Inhalt nicht zu ihrer Endung passt. Nichts davon wird abgelegt. Eine leere Markdown-Datei wird angenommen (Vorgabe der Person, 2026-11-24).
- [ ] Der Name der Datei wird so angezeigt, wie sie hochgeladen wurde, auch mit Umlauten und Sonderzeichen. Ein Name über 255 Zeichen wird auf seine ersten 255 gekürzt, und die Seite sagt das (Vorgabe der Person, 2026-11-24). Kein Name kann bewirken, dass die Datei an einem anderen Ort landet oder eine andere überschreibt.
- [ ] Ein doppelter Klick ergibt genau ein Dokument.
- [ ] Liegt eine Datei mit demselben Inhalt schon im Arbeitsbereich, erfährt die Person das; ein unbemerktes Doppel entsteht nicht.
- [ ] Zwei verschiedene Dateien mit demselben Namen bleiben zwei Dokumente; keine ersetzt stillschweigend die andere.
- [ ] Dieselbe Datei in zwei Arbeitsbereichen ergibt zwei getrennte Dokumente, und keine Seite erfährt von der anderen.
- [ ] Scheitert das Hochladen mittendrin, bleibt nichts Halbes zurück: kein Dokument ohne Datei und keine Datei ohne Dokument. Die Seite meldet das, und ein neuer Versuch ist möglich.
- [ ] Das Hochladen gelingt auch, wenn die Dienste für Verarbeitung und Suche nicht erreichbar sind.
- [ ] Nach zu vielen Uploads in kurzer Zeit werden weitere eine Zeit lang abgewiesen, und die Seite sagt das.
- [ ] Ohne Anmeldung lässt sich nichts hochladen. Wird die Anmeldung ungültig oder ist der Server nicht erreichbar, verhält sich die Seite wie in AUTH-01 festgelegt.

**Nicht Teil:** Das Dokument verarbeiten (Text herausziehen, zerteilen, einbetten, indexieren) und den Stand der Verarbeitung zeigen gehört zu DOC-02; dort zeigt sich auch, ob ein PDF beschädigt, geschützt oder ein Scan ohne Text ist. Die Liste aller Dokumente und die Ansicht eines Dokuments gehören zu DOC-03, Suche und Chat zu RAG-01 und CHAT-01, das Neuaufbauen des Suchindex zu SYS-01. Dokumente löschen, ersetzen, umbenennen oder herunterladen, Fassungen eines Dokuments verwalten, Ordner und Schlagworte. Weitere Formate wie Word oder HTML, Prüfung auf Schadsoftware, Hochladen über einen KI-Client. Rollen und Rechte innerhalb eines Arbeitsbereichs. Die vorgeladenen Beispieldokumente der Live-Demo.

**Begriffe:**

- **Dokument:** eine hochgeladene Datei zusammen mit dem, was der Assistent über sie festhält (Name, Arbeitsbereich, wer sie wann hochgeladen hat).
- **Markdown-Datei:** eine einfache Textdatei mit Auszeichnungen für Überschriften und Listen, wie sie Entwickler für Notizen und Anleitungen benutzen.
- **Arbeitsbereich (Tenant):** der abgeschlossene Bereich einer Firma im Assistenten (siehe TEN-01).
- **Ablage:** der Ort im Assistenten, an dem die hochgeladenen Dateien unverändert liegen.
- **Verarbeitung:** die Schritte, die aus einem hochgeladenen Dokument ein durchsuchbares machen (DOC-02).
- **Doppel:** ein zweites Dokument mit genau demselben Inhalt im selben Arbeitsbereich.

## 2. Schnitt

| Nr | Schritt | Was er tut | Oberfläche | Deckt ab | Anchor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | `POST /api/v1/documents` | Nimmt von der angemeldeten Person eine PDF- oder Markdown-Datei an, legt sie unverändert ab und hält sie als Dokument ihres Arbeitsbereichs fest. | Seite für Dokumente mit Eintrag in der Seitenleiste: Dateiauswahl mit Hinweis auf Formate und Größe, Anzeige während des Hochladens, Bestätigung mit Dateiname und Hinweis „noch nicht durchsuchbar“, Meldungen bei Abweisung, Doppel, Sperre und Serverausfall | Seite für Dokumente, jede Person des Arbeitsbereichs darf hochladen; Formate und Größe vorab, Anzeige während des Hochladens; Bestätigung mit Dateiname, noch nicht durchsuchbar; Dokument im Arbeitsbereich der Person mit wer und wann, Arbeitsbereich in der Anfrage ändert nichts; Datei unverändert abgelegt; anderes Format, falsche Endung, leere und zu große Datei abgewiesen, nichts abgelegt; Dateiname mit Umlauten, Sonderzeichen, Überlänge und Pfadangaben; doppelter Klick und mehrere Dateien auf einmal; dieselbe Datei ein zweites Mal im Arbeitsbereich; gleicher Name mit anderem Inhalt; zwei Personen gleichzeitig; dieselbe Datei in zwei Arbeitsbereichen; Abbruch mittendrin und volle Ablage, nichts Halbes; gelingt ohne die Dienste für Verarbeitung und Suche; Sperre nach zu vielen Uploads; ohne Anmeldung nichts, ungültige Anmeldung und Server nicht erreichbar wie in AUTH-01 | rag/01, rag/05 | fertig |

**Nicht abgedeckt:** Der Fall „Das PDF ist beschädigt, mit einem Kennwort geschützt oder ein Scan ohne lesbaren Text“ nur zum Teil: S1 prüft, ob die Datei ein PDF ist, nicht, ob sich ihr Text lesen lässt. Das zeigt sich erst bei der Verarbeitung in DOC-02 (siehe „Nicht Teil“).

## 3. Entscheidungen

### E1 · S1 · Was geschieht, wenn eine Datei mit demselben Inhalt schon im Arbeitsbereich liegt?

**Warum sich die Frage stellt:** Tobias lädt das Betriebshandbuch hoch, das Jana schon hochgeladen hat; Jana klickt zweimal schnell auf „Hochladen“. Die Abnahme verlangt, dass die Person von dem vorhandenen Dokument erfährt und kein unbemerktes Doppel entsteht. In beiden Vorschlägen erkennt der Server denselben Inhalt an einem Fingerabdruck der Datei (SHA-256), der Name spielt keine Rolle.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Abweisen: Je Arbeitsbereich gibt es jeden Inhalt nur einmal (eindeutiger Index auf Arbeitsbereich und Fingerabdruck). Ein zweiter Upload wird am Feld abgewiesen mit der Meldung, unter welchem Namen, von wem und wann die Datei schon hochgeladen wurde. (`rag/01 · Jede Aufgabe gefahrlos wiederholbar`, `rag/05 · Festlegen, wie Änderungen erkannt werden`) | Doppelter Klick, wiederholter Versuch und zwei Personen im selben Augenblick ergeben über den Index immer genau ein Dokument. DOC-02 bettet keinen Inhalt zweimal ein, und die Suche liefert keine Textstelle doppelt. Denselben Inhalt bewusst unter einem zweiten Namen abzulegen geht nicht. Wenig Code. |
| 2 | Nachfragen und zulassen: Liegt der Inhalt schon vor, legt der Server nichts an und meldet das vorhandene Dokument. Die Seite fragt „Trotzdem hochladen?“; erst eine zweite Anfrage mit ausdrücklicher Bestätigung legt ein zweites Dokument an. (`rag/01 · Jede Aufgabe gefahrlos wiederholbar`, `rag/05 · Festlegen, wie Änderungen erkannt werden`) | Ein Doppel entsteht nur bewusst. DOC-02 bettet es dann ein zweites Mal ein, und in RAG-01 können gleiche Textstellen aus beiden Dokumenten zwei der 5 bis 8 Plätze belegen. Ohne eindeutigen Index braucht es für doppelten Klick und gleichzeitiges Hochladen eine kurze Sperre je Arbeitsbereich und Fingerabdruck. Zusätzlich ein Dialog auf der Seite und ein Feld für die Bestätigung in der Anfrage; zusammen mit E4 · 2 fragt die Seite je Datei einzeln nach. |

**Wahl (Person):** E1, E2 Vorschlag 1

### E2 · S1 · Wie groß darf eine Datei sein?

**Warum sich die Frage stellt:** Eine sehr große Datei soll abgewiesen werden, und die Seite nennt die Grenze vorab. PHP nimmt im Container heute nur 2 MB an; jede Grenze darüber braucht höhere Werte in der Konfiguration von PHP. Die Grenze gilt für PDF und Markdown gleich.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Höchstens 10 MB je Datei, als Wert in der Konfiguration. (`rag/01 · Große Dateien in Teilen verarbeiten`) | Handbücher und Anleitungen mit Text und einigen Bildern passen, ein bebildertes Handbuch mit mehreren hundert Seiten oft nicht. DOC-02 kann eine Datei dieser Größe als Ganzes laden, ohne an die Speichergrenze zu kommen; die Verarbeitung bleibt auch auf der CPU überschaubar. |
| 2 | Höchstens 50 MB je Datei, als Wert in der Konfiguration. (`rag/01 · Große Dateien in Teilen verarbeiten`) | Auch große, bebilderte Handbücher passen. Das Hochladen dauert bei langsamer Leitung Minuten. DOC-02 muss große PDFs Seite für Seite verarbeiten, statt sie als Ganzes zu laden, und das Einbetten eines langen Dokuments dauert auf der CPU deutlich länger. Dasselbe Rate-Limit lässt fünfmal so viel Speicher belegen. |

**Wahl (Person):** E1, E2 Vorschlag 1

### E3 · S1 · Welche Kennung bekommt ein Dokument?

**Warum sich die Frage stellt:** Auf die Kennung des Dokuments verweisen später die Textstücke (DOC-02), die Objekte im Suchindex, die Adresse der Ansicht (DOC-03) und das MCP-Tool `get_document` mit seiner Eingabe `document_id`. Sie nachträglich zu ändern, trifft alle diese Stellen. Arbeitsbereiche haben eine ULID (TEN-01 · E1), Konten eine fortlaufende Zahl.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Fortlaufende Zahl, wie bei den Konten. | Kurze Kennung in Adressen und im MCP-Tool, Verweise in späteren Tabellen sind Zahlen. Übernimmt ein KI-Client die Zahl falsch, kann er ein anderes Dokument desselben Arbeitsbereichs treffen, ohne es zu merken. Die Zählung läuft über alle Arbeitsbereiche: An der Zahl sieht eine Person ungefähr, wie viele Dokumente es insgesamt gibt, und kann Nachbarzahlen ausprobieren; dass sie dann 404 bekommt, hängt am Test mit fremdem Datensatz in jedem späteren Endpunkt. Nach dem Neuaufsetzen der Datenbank beginnt die Zählung bei 1; weil Arbeitsbereiche ULIDs haben, trifft ein alter Indexstand trotzdem keinen neuen Arbeitsbereich. Der Name der Datei in der Ablage braucht eine eigene, zufällige Kennung. |
| 2 | ULID, wie bei den Arbeitsbereichen. | Verrät nichts über die Anzahl und lässt sich nicht erraten. Dieselbe Kennung kann der Name der Datei in der Ablage sein. Verweise in den Tabellen von DOC-02 und im Suchindex sind Zeichenketten mit 26 Zeichen; Adressen werden länger. Ein KI-Client übernimmt die Kennung aus dem Ergebnis von `list_sources` oder `search_knowledge` in `get_document`; verändert er dabei ein Zeichen, gibt es das Dokument nicht, und er bekommt „nicht gefunden“. |

**Wahl (Person):** E3 Vorschalg 2

### E4 · S1 · Was geschieht, wenn Jana mehrere Dateien auf einmal auswählt?

**Warum sich die Frage stellt:** Wer einen Arbeitsbereich füllt, hat meist mehrere Dokumente. Der Endpunkt nimmt in beiden Vorschlägen genau eine Datei je Anfrage an; die Frage ist, was die Seite zulässt und zeigt.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Die Dateiauswahl nimmt genau eine Datei. Nach der Bestätigung kann Jana die nächste wählen. | Kleine Seite mit einem Zustand: läuft, angenommen oder abgewiesen. Zwanzig Dokumente sind zwanzig Durchgänge. Das Rate-Limit pro Minute kann niedrig bleiben. |
| 2 | Die Dateiauswahl nimmt mehrere Dateien. Die Seite lädt sie nacheinander hoch, je Datei eine Anfrage, und zeigt eine Liste mit dem Ergebnis je Datei: angenommen, abgewiesen mit Grund oder schon vorhanden. | Ein Ordner voller Dokumente ist ein Durchgang. Bricht die Verbindung mittendrin ab, sind einige Dateien angenommen und andere nicht; die Liste zeigt, welche. Das Rate-Limit pro Minute muss einen solchen Stapel zulassen, sonst weist es den Rest ab und die Liste muss das sagen. Mehr Zustand und mehr Code auf der Seite. |

**Wahl (Person):** E4 Vorschlag 2 .

### Vom Schreiber entschieden

| Schritt | Was | Entscheidung | Warum |
| --- | --- | --- | --- |
| S1 | Ablage | Die Datei liegt auf der privaten lokalen Disk in einem Ordner je Arbeitsbereich unter einem vom Server erzeugten Namen; der hochgeladene Name steht nur in MySQL. | Kein Name kann den Ort bestimmen oder eine andere Datei überschreiben; `api` und `worker` sehen dieselbe Ablage, und der Index lässt sich aus MySQL und den Dateien neu aufbauen. |
| S1 | Daten eines Dokuments | Arbeitsbereich, hochladendes Konto, Name, Format, Größe, Fingerabdruck, Ort in der Ablage, Zeitstempel. Kein Stand der Verarbeitung, kein Embedding-Modell. | Abnahme: Arbeitsbereich, wer und wann; alles Weitere gehört zu DOC-02, ein Schritt baut nichts für einen späteren. |
| S1 | Erkennen desselben Inhalts | SHA-256 über die Bytes der Datei, verglichen nur innerhalb des Arbeitsbereichs, allein über den eindeutigen Index; der Controller fängt den Verstoß ab und antwortet mit 422. Gegen einen doppelten Klick sperrt die Seite den Knopf. | `rag/05 · Festlegen, wie Änderungen erkannt werden`; keine eigene Prüfung vorab (Vorgabe der Person, 2026-11-24). |
| S1 | Prüfung des Formats | Endung `.pdf`, `.md` oder `.markdown` ohne Rücksicht auf Groß- und Kleinschreibung, dazu der Inhalt: ein PDF muss als PDF erkannt werden, eine Markdown-Datei gültiges UTF-8 ohne Nullzeichen sein und darf nicht als PDF erkannt werden. Der vom Browser genannte Typ zählt nicht. | Abnahme: Inhalt muss zur Endung passen; Markdown hat kein eigenes Kennzeichen (`rag/01 · Erst sichten, dann passend bereinigen`). |
| S1 | Dateiname | Gespeichert wird der Name ohne Pfadangaben; ungültige Zeichen werden durch `?` ersetzt, ein Name über 255 Zeichen wird auf die ersten 255 gekürzt. Die Seite nennt den gekürzten Namen. Eine leere Datei wird nicht eigens geprüft. | Vorgabe der Person (2026-11-24); so führt kein Name zu einem Fehler beim Speichern. |
| S1 | Herkunft des Arbeitsbereichs | Das Dokument entsteht über die Beziehung des aktuellen Arbeitsbereichs; außer der Datei wird aus der Anfrage nur gelesen, was E1 verlangt. | Regel aus `CLAUDE.md` und TEN-01 · E4 · 2; ein Test schickt einen fremden Arbeitsbereich mit. |
| S1 | Nichts Halbes | Erst wird die Datei geschrieben, dann der Datensatz; scheitert der Datensatz, wird die Datei wieder entfernt, scheitert das Schreiben, entsteht kein Datensatz. | Abnahme: kein Dokument ohne Datei und keine Datei ohne Dokument. |
| S1 | Antwort | 201 mit `data`: Kennung, Name, Format, Größe, Zeitpunkt. Kein Ort in der Ablage, kein Fingerabdruck. | Die Bestätigung braucht Name und Größe; was nur der Server braucht, bleibt dort. |
| S1 | Rate-Limit | Je Konto 30 pro Minute und 200 pro Tag; jede Anfrage, die die Route erreicht, zählt, auch eine abgewiesene. Eine Anfrage über 12 MB weist Laravel vorher ab, sie legt nichts ab und zählt nicht. | Wie AUTH-02 · E3 · 2, Zahlen nach E4 · 2; die Route ist geschützt, deshalb zählt das Konto und nicht die IP-Adresse. |
| S1 | Grenze in PHP | Eine eigene ini-Datei, die `docker-compose.yml` nur in `api` einbindet: 12 MB je Anfrage, eine Datei je Anfrage, PHP-Warnungen nicht in die Antwort. Eine Anfrage über 12 MB ergibt 413, und die Seite meldet sie wie eine zu große Datei. | `worker` nimmt keine Uploads an; ohne die Datei nimmt PHP nur 2 MB an, und eine ausgegebene Warnung machte aus 413 ein 200. |
| S1 | Oberfläche | Seite `/documents` im Layout `auth` mit Eintrag „Dokumente“ in der Seitenleiste und Store `documents`. Knopf „Hochladen“ oben rechts öffnet den Dialog `DocumentUploadDialog` (offen über `defineModel()` und `v-model`, Beschriftung des Aktionsknopfs als Prop) mit Hinweis auf Formate, Größe und Kürzen langer Namen und dem Knopf „Dateien auswählen“. Während eine Auswahl hochlädt, zeigt der Dialog „n von m: Name“ ohne Prozentwert und lässt sich nicht schließen; danach schließt er sich. Das Ergebnis je Datei erscheint als Snackbar (`v-snackbar-queue`, bis zu drei übereinander, je 5 Sekunden). Keine Liste der Dokumente auf der Seite; die kommt mit DOC-03 als Tabelle. | Vorgabe der Person (2026-11-24); `$api` meldet keinen Fortschritt beim Senden. |
| S1 | Prüfung vorab | Endung und zu große Datei prüft die Seite je Datei vor dem Senden und meldet eine Abweisung als Snackbar mit dem Dateinamen, nicht mit `:rules` an einem Feld; die 422-Meldung des Servers erscheint ebenso. | E4 · 2 verlangt ein Ergebnis je Datei, und eine Auswahl kann gültige und ungültige Dateien mischen. |
| S1 | Meldung beim Doppel | Zeitpunkt des vorhandenen Dokuments in der Zeit von `Europe/Berlin`, Datum und Uhrzeit. | Der Server rechnet in UTC; die Meldung ist für Personen in Deutschland. |
| S1 | Abbruch einer Auswahl | Bei 429, 401, nicht erreichbarem Server oder geänderter Anmeldung bleiben die übrigen Dateien der Auswahl ungesendet, und eine Snackbar nennt ihre Zahl; bei 422, 413 und anderen Fehlern geht es mit der nächsten Datei weiter. Ohne Antwort des Servers erscheint die laufende Datei als Warnung, weil sie angekommen sein kann. | Nach einer Sperre oder ohne Server scheiterte jede weitere Anfrage ebenso. |

# Offene Punkte

- `rag/05 · Neue, geänderte und gelöschte Dokumente nachführen`: Das Ticket nimmt nur neue Dokumente auf. Ersetzen und Löschen sind nicht Teil; eine neue Fassung wird ein zweites Dokument neben der alten.
- S1: Das Rate-Limit begrenzt die Zahl der Uploads je Konto, nicht den Speicher je Arbeitsbereich. Eine Obergrenze für den belegten Speicher gibt es nicht.
- S1 · Review R7: Die Grenze von 10 MB steht an drei Stellen, `api/config/documents.php`, `docker/api/uploads.ini` (12 MB) und die 10 MB in Text und Vorabprüfung von `app/app/components/DocumentUploadDialog.vue`; wer sie ändert, muss alle drei ändern. Die Seite kann den Wert erst vom Server lesen, wenn es dafür einen Endpunkt gibt.
- S1 · Review R10, im Re-Review nicht ok: Die Seite für Dokumente ist nicht im Browser abgenommen: Dialog öffnen und schließen, Mehrfachauswahl mit gültigen und ungültigen Dateien nacheinander, gesperrter Knopf, Snackbars bei 422, 413, 429 mitten in einer Auswahl, Zeitgrenze, Serverausfall, Anmeldewechsel in einem anderen Tab und Weiterleitung bei 401.
- S1 · Re-Review R1: Scheitert nach einem teilweisen Schreiben auch das Löschen, bleibt eine Datei ohne Dokument in der Ablage; nur eine Warnung im Log nennt den Pfad. Der Test prüft den Löschaufruf, nicht eine echte Teildatei.
- S1 · Re-Review R2: Die Seite vergleicht den Zugang erst ab Beginn einer Auswahl. Hat sich in einem anderen Tab schon vorher jemand anders angemeldet, geht bereits die erste Datei mit dessen Zugang in seinen Arbeitsbereich, obwohl der Tab noch den alten zeigt.
- S1 · Re-Review R4: Die Zeitgrenze von `ofetch` endet, sobald die Kopfzeilen der Antwort da sind. Hält ein Proxy danach den Inhalt der Antwort offen, bleibt die Auswahl gesperrt.
- S1 · Re-Review N1: Der Test für einen nur teilweise angekommenen Upload legt bei jedem Lauf eine temporäre Datei an und löscht sie nicht.
