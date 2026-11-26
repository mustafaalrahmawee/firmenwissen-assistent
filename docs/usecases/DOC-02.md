# Use-Case-Blatt: DOC-02 · Dokument verarbeiten

- **Use Case:** DOC-02, Dokument verarbeiten per Queue: Text extrahieren, zerteilen, einbetten, indexieren, mit sichtbarem Status, MVP (Woche 2)
- **Baut auf:** DOC-01
- **Status:** Schritte
- **Code:** –

Das Blatt hält fest, was der Use Case fachlich bedeutet, in welchen Schritten er gebaut wird (ein Schritt ist ein Endpunkt), was dafür entschieden wurde und wie er abgenommen wird. Alles mit dem Vermerk `Person` schreibt die Person selbst; der Schreiber trägt es wortgleich ein.

## 1. Ticket

**Worum es geht:** Ein hochgeladenes Dokument liegt bisher nur als Datei im Arbeitsbereich; der Assistent kennt seinen Inhalt nicht. Mit diesem Use Case macht der Assistent aus jedem hochgeladenen Dokument von selbst ein durchsuchbares: Er liest den Text heraus, teilt ihn in Textstücke, bettet jedes Stück ein und legt die Stücke in den Suchindex des Arbeitsbereichs. Das geschieht im Hintergrund, die Person muss nicht warten. Sie sieht für ihr Dokument, ob es noch in Arbeit ist, durchsuchbar ist oder gescheitert ist, und im letzten Fall warum. Auf den Textstücken bauen danach Suche, Chat und der Zugang für KI-Clients auf.

**Wer es braucht und warum:** Alle Mitarbeitenden eines IT-Betriebs, die Dokumente hochladen, und Besucher der Live-Demo. Ohne Verarbeitung bleibt jedes Dokument eine abgelegte Datei, und der Assistent kann zu keiner Frage etwas daraus sagen. Ohne sichtbaren Stand weiß niemand, ob ein Dokument schon gefunden werden kann: Wer im Support eine Antwort nicht bekommt, kann nicht unterscheiden, ob sie in keinem Dokument steht, ob das Dokument noch in Arbeit ist oder ob es nie gelesen werden konnte, und lädt es womöglich immer wieder hoch.

**Beispiel:** Jana Petrov von der Nordlicht IT GmbH lädt das „Betriebshandbuch VPN“ als PDF und ihre Notizen zu neuen Laptops als Markdown-Datei hoch. Beide werden sofort angenommen; Jana sieht, dass sie verarbeitet werden, und arbeitet weiter. Kurz darauf sind die Notizen durchsuchbar, etwas später auch das Handbuch mit seinen 60 Seiten. Tobias Kern lädt einen eingescannten Wartungsvertrag hoch, der nur aus Bildern besteht. Das Dokument wird angenommen, scheitert aber bei der Verarbeitung, und Tobias liest den Grund: Das PDF enthält keinen lesbaren Text. Am Abend lädt er ein Angebot hoch, während der Dienst für das Einbetten gerade nicht läuft. Das Hochladen gelingt trotzdem; als der Dienst wieder da ist, wird das Angebot durchsuchbar, ohne dass Tobias es noch einmal hochladen muss. Mehmet Aydin von der Hafenkante Systemhaus GmbH sieht von alldem nichts, und kein Textstück der Nordlicht IT landet in seinem Arbeitsbereich.

**Was schiefgehen kann:**

- F1 · Das PDF ist beschädigt, mit einem Kennwort geschützt oder ein Scan ohne lesbaren Text; die Markdown-Datei ist leer.
- F2 · Das Dokument ist sehr kurz (ein Satz) oder sehr lang (mehrere hundert Seiten, 10 MB).
- F3 · Der Dienst für das Einbetten ist nicht erreichbar, sehr langsam oder kennt das Modell nicht.
- F4 · Der Suchindex ist nicht erreichbar oder kennt den Arbeitsbereich noch nicht, etwa beim ersten Dokument einer Firma.
- F5 · Die Verarbeitung bricht mittendrin ab, zum Beispiel weil der Dienst im Hintergrund neu startet: Ein Teil der Textstücke ist schon da, der Rest fehlt.
- F6 · Dasselbe Dokument wird ein zweites Mal verarbeitet, weil ein Versuch wiederholt oder doppelt gestartet wurde.
- F7 · Jana lädt zwanzig Dokumente auf einmal hoch; zwei Arbeitsbereiche laden zur selben Zeit hoch.
- F8 · Im Hintergrund läuft gerade nichts, das die Dokumente abarbeitet; sie bleiben liegen.
- F9 · Die Datei eines Dokuments fehlt in der Ablage.
- F10 · Jana schließt die Seite oder meldet sich ab, während ihr Dokument verarbeitet wird, oder sie lässt die Seite offen und wartet auf das Ende.
- F11 · Ein Dokument ist gescheitert, und Jana lädt es noch einmal hoch; das Hochladen weist es ab, weil es schon im Arbeitsbereich liegt.
- F12 · Es gibt Dokumente, die hochgeladen wurden, bevor es die Verarbeitung gab.
- F13 · Das Modell für das Einbetten wird in der Konfiguration geändert, während ältere Dokumente noch mit dem alten eingebettet sind.
- F14 · Mehmet versucht, den Stand eines Dokuments der Nordlicht IT abzufragen.
- F15 · Ein PDF ergibt beim Lesen viel mehr Text, als die Datei groß ist, bis hin zu Gigabytes, etwa eine absichtlich so gebaute Datei, die jemand in der Live-Demo hochlädt.

**Abnahme:**

- [ ] A1 · Nach dem Hochladen beginnt die Verarbeitung von selbst und im Hintergrund; das Hochladen wartet nicht darauf und gelingt auch, wenn die Dienste für Einbetten und Suche nicht erreichbar sind.
- [ ] A2 · Die Person sieht in der Anwendung für ihr Dokument, ob es noch in Arbeit ist, durchsuchbar ist oder gescheitert ist. Die Verarbeitung läuft weiter, wenn sie die Seite schließt oder sich abmeldet.
- [ ] A3 · Aus PDF und Markdown wird der Text gelesen und in Textstücke geteilt. Zu jedem Stück ist festgehalten, aus welchem Dokument und von welcher Stelle es stammt (bei einem PDF die Seite, bei Markdown die Überschrift), damit eine Antwort später zur Textstelle springen kann.
- [ ] A4 · Text und Textstücke liegen dauerhaft in den eigenen Daten des Assistenten. Der Suchindex enthält nur eine Kopie und ließe sich daraus neu aufbauen.
- [ ] A5 · Jedes Textstück ist eingebettet und liegt im Suchindex unter dem Arbeitsbereich des Dokuments, und nur dort. Beim ersten Dokument einer Firma entsteht ihr Bereich im Suchindex von selbst.
- [ ] A6 · Zu jedem Dokument ist festgehalten, mit welchem Modell und welcher Dimension es eingebettet wurde.
- [ ] A7 · Ein Dokument gilt erst als durchsuchbar, wenn alle seine Textstücke im Suchindex liegen.
- [x] A8 · Ein Dokument ohne lesbaren Text (beschädigt, geschützt, Scan, leer) scheitert mit einem Grund, den die Person auf Deutsch versteht. Die Datei bleibt in der Ablage.
- [ ] A9 · Ein vorübergehender Ausfall eines Dienstes lässt das Dokument nicht für immer scheitern: Es wird später durchsuchbar, ohne dass die Person es neu hochlädt.
- [ ] A10 · Bricht die Verarbeitung mittendrin ab oder läuft sie zweimal, gibt es am Ende jedes Textstück genau einmal, in den eigenen Daten und im Suchindex.
- [x] A11 · Viele Dokumente auf einmal werden nacheinander abgearbeitet; keines geht verloren, und ein gescheitertes hält die anderen nicht auf.
- [x] A12 · Den Stand eines Dokuments sieht nur, wer zu seinem Arbeitsbereich gehört. Ein fremdes Dokument sieht aus wie eines, das es nicht gibt.
- [ ] A13 · Dokumente, die vor diesem Use Case hochgeladen wurden, bleiben nicht für immer unverarbeitet.

**Nicht Teil:** Die Liste aller Dokumente und die Ansicht des Textes gehören zu DOC-03. Suchen in den Textstücken gehört zu RAG-01, Fragen im Chat zu CHAT-01, der Zugang für KI-Clients zu MCP-01 und MCP-02. Den ganzen Suchindex neu aufbauen, auch nach einem Wechsel des Modells, gehört zu SYS-01; die Güte der Textstücke messen zu EVAL-01. Text aus Scans erkennen (OCR), Kennwörter für geschützte PDFs, Bilder und Tabellen auswerten, weitere Formate. Dokumente löschen oder ersetzen. Die vorgeladenen Beispieldokumente der Live-Demo.

**Begriffe:**

- **Verarbeitung:** die Schritte, die aus einem hochgeladenen Dokument ein durchsuchbares machen: Text lesen, teilen, einbetten, in den Suchindex legen.
- **Textstück (Chunk):** ein zusammenhängender Abschnitt eines Dokuments, klein genug, um einzeln gefunden und in einer Antwort als Quelle genannt zu werden.
- **Einbetten:** ein Modell rechnet ein Textstück in eine Zahlenreihe (Vektor) um; Texte mit ähnlicher Bedeutung bekommen ähnliche Zahlenreihen, und daran findet die Suche sie.
- **Dimension:** die Länge dieser Zahlenreihe; sie hängt vom Modell ab.
- **Suchindex:** der Teil des Assistenten, in dem die Textstücke zum Durchsuchen liegen, je Arbeitsbereich getrennt (siehe TEN-01).
- **Im Hintergrund (Queue):** die Arbeit wird in eine Warteschlange gestellt und von einem eigenen Dienst abgearbeitet, während die Person weiterarbeitet.
- **Stand:** was die Person über die Verarbeitung ihres Dokuments sieht.
- **Arbeitsbereich (Tenant), Ablage, Dokument:** wie in TEN-01 und DOC-01.

## 2. Schnitt

| Nr | Schritt | Was er tut | Oberfläche | Deckt ab | Anchor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| S1 | Job `ExtractDocumentText` | Liest nach dem Hochladen von selbst im Hintergrund den Text aus dem PDF oder der Markdown-Datei und hält ihn fest; ohne lesbaren Text scheitert das Dokument mit einem Grund. | entfällt – läuft im Hintergrund; den Stand zeigt S2 | A1; A3 Text aus PDF und Markdown, mit Seite; A4 Text dauerhaft; A8; A11 viele nacheinander, ein gescheitertes hält die anderen nicht auf; F1; F2 sehr lang; F6 Text einmal; F7 zwanzig Dokumente nacheinander; F9; F15; außerdem: Arbeit im Hintergrund kennt den Arbeitsbereich des Dokuments (offen aus TEN-01 · S3) | rag/01 | fertig |
| S2 | `GET /api/v1/documents/{document}` | Sagt der Person, wie weit ihr Dokument ist und, wenn es gescheitert ist, warum. | Seite für Dokumente: Stand je Dokument, das die Seite hochgeladen hat (wartet, in Arbeit, gescheitert mit Grund), aktuell gehalten, solange es in Arbeit ist | A2 Stand wartet, in Arbeit, gescheitert; läuft weiter nach Schließen oder Abmelden; A12; F8 Dokument wartet sichtbar; F10; F14 | – | fertig |
| S3 | Job `ChunkDocument` | Teilt den gelesenen Text in Textstücke und hält zu jedem fest, von welcher Stelle des Dokuments es stammt. | entfällt – läuft im Hintergrund | A3 in Textstücke geteilt, je Stück Dokument und Stelle (Seite beim PDF, Überschrift bei Markdown); A4 Textstücke dauerhaft; A10 jedes Stück genau einmal in den eigenen Daten; F2 sehr kurz und sehr lang; F5 und F6 in den eigenen Daten | rag/02 | offen |
| S4 | Befehl `app:create-search-index` | Legt den Suchindex für Textstücke einmalig an, getrennt je Arbeitsbereich; ein zweiter Lauf ändert nichts. | entfällt – läuft im Terminal | A5 Suchindex nach Arbeitsbereichen getrennt; F4 nicht erreichbar beim Anlegen; außerdem: zweiter Lauf des Befehls ändert nichts | rag/04 | offen |
| S5 | Job `IndexDocumentChunks` | Bettet die Textstücke ein, legt sie in den Suchindex des Arbeitsbereichs und macht das Dokument damit durchsuchbar. | Seite für Dokumente: Stand „durchsuchbar“ | A2 Stand durchsuchbar; A4 Suchindex nur Kopie; A5 jedes Stück eingebettet, nur unter dem eigenen Arbeitsbereich, Bereich entsteht beim ersten Dokument von selbst; A6; A7; A9 kurzer Ausfall wird von selbst erneut versucht; A10 jedes Stück genau einmal im Suchindex; F3; F4; F5 und F6 im Suchindex; F7 zwanzig Dokumente und zwei Arbeitsbereiche zur selben Zeit; F13 Modell je Dokument festgehalten | rag/03, rag/05 | offen |
| S6 | Befehl `app:process-documents` | Stellt alle Dokumente, die noch nicht durchsuchbar sind, erneut zur Verarbeitung an: alte, liegengebliebene und gescheiterte. | entfällt – läuft im Terminal | A9 längerer Ausfall: gescheitertes Dokument wird ohne neues Hochladen durchsuchbar; A13; F6 zweiter Lauf stellt kein Dokument doppelt an; F12 | rag/05 | offen |

**Zuordnung:**

- A1 S1 · A2 S2, S5 · A3 S1, S3 · A4 S1, S3, S5 · A5 S4, S5 · A6 S5 · A7 S5 · A8 S1 · A9 S5, S6 · A10 S3, S5 · A11 S1 · A12 S2 · A13 S6
- F1 S1 · F2 S1, S3 · F3 S5 · F4 S4, S5 · F5 S3, S5 · F6 S1, S3, S5, S6 · F7 S1, S5 · F8 S2 · F9 S1 · F10 S2 · F11 – · F12 S6 · F13 S5 · F14 S2 · F15 S1

**Nicht abgedeckt:** F11 nur zum Teil: Jana selbst kann die Verarbeitung nicht neu anstoßen, das tut der Befehl in S6 im Terminal. F13 nur zum Teil: S5 hält das Modell je Dokument fest; die älteren Dokumente mit dem neuen Modell einzubetten ist der Neuaufbau in SYS-01.

## 3. Entscheidungen

### E1 · S1 · Womit wird der Text aus einem PDF gelesen?

**Warum sich die Frage stellt:** Jana lädt ein Handbuch mit 60 Seiten hoch, Tobias einen Scan und vielleicht ein geschütztes PDF. Der Text muss genau herauskommen, mit Seitenzahl, und ein unlesbares PDF muss sich sicher erkennen lassen. Im Image der API gibt es heute kein Werkzeug für PDF und kein Paket dafür.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | `pdftotext` aus poppler-utils, installiert im Image der API (`docker/api/Dockerfile`), aufgerufen über Laravels `Process`; die Seiten trennt das Werkzeug mit einem Seitenvorschub. (`rag/01 · Genau parsen`, `rag/01 · Erst den billigsten Parser, der es schafft`) | Ausgereift auch bei schwierigen Schriften, Ligaturen und mehrspaltigem Satz; schnell und mit wenig Speicher, auch bei 10 MB. Ein geschütztes oder beschädigtes PDF meldet das Werkzeug mit eigenem Fehlercode und eigener Meldung. Das Image muss neu gebaut werden (`api` und `worker`); wer Artisan oder die Tests auf dem Host statt im Container startet, braucht poppler-utils dort auch. |
| 2 | Das Composer-Paket `smalot/pdfparser`, reines PHP; Seite für Seite über die Seiten des Dokuments. (`rag/01 · Genau parsen`, `rag/01 · Erst den billigsten Parser, der es schafft`) | Kein Eingriff ins Image, läuft überall, wo PHP läuft. Schwächer bei manchen Schriften und Kodierungen: fehlende Leerzeichen zwischen Wörtern oder falsche Zeichen kommen vor und wirken bis in die Suche durch. Lädt die ganze Datei in den Speicher; ein bebildertes PDF mit 10 MB kann an die 128 MB von PHP stoßen, dann braucht der Job eine höhere Grenze. Ein geschütztes PDF erkennt es nur an einer Ausnahme mit englischem Text. |

**Wahl (Person):** E1, E2  Vorschlag 1 , E3 acuh Vorschlag 1 weil Wenige Stände, wenige Test -> einfaches code , weil wir bis 10 MB haben, dann kein Problem "S6 stellt jedes nicht durchsuchbare Dokument von vorn an" das wird selten Fehler dan lohnt sich einfaches code zu schreiben.

### E2 · S1 · Wo liegt der gelesene Text?

**Warum sich die Frage stellt:** Der Text muss dauerhaft in MySQL liegen, damit sich der Suchindex daraus neu aufbauen lässt. S3 braucht je Textstück die Seite, DOC-03 zeigt den Text an, und CHAT-01 springt zur Textstelle.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Eine eigene Tabelle mit einer Zeile je Seite: Dokument, Seitenzahl, Text. Eine Markdown-Datei ist eine Zeile mit Seite 1. Ein zweiter Lauf ersetzt alle Seiten des Dokuments in einer Transaktion; Dokument und Seitenzahl sind zusammen eindeutig. (`rag/01 · Jede Aufgabe gefahrlos wiederholbar`) | Die Seite steht als eigener Wert fest; S3 teilt je Seite oder über Seiten hinweg und kennt sie immer. DOC-03 kann den Text seitenweise laden, CHAT-01 springt zu einer Seite. Ein PDF mit 600 Seiten sind 600 Zeilen. Eine Textstelle über eine Seitengrenze hinweg muss S3 aus zwei Zeilen zusammensetzen. |
| 2 | Eine Spalte am Dokument mit dem ganzen Text; die Seiten sind durch einen Seitenvorschub getrennt. Ein zweiter Lauf überschreibt die Spalte. (`rag/01 · Jede Aufgabe gefahrlos wiederholbar`) | Keine neue Tabelle. S3 kann jedem Textstück seine genaue Position im ganzen Text geben, und DOC-03 springt damit zur Stelle; die Seite muss S3 aus den Seitenvorschüben davor zählen. Die Liste der Dokumente (DOC-03, `list_sources`) darf die Spalte nicht mitladen. Der Text eines langen PDF liegt als ein Wert von einigen MB in einer Zeile. |

**Wahl (Person):** E1, E2  Vorschlag 1 , E3 acuh Vorschlag 1 weil Wenige Stände, wenige Test -> einfaches code , weil wir bis 10 MB haben, dann kein Problem "S6 stellt jedes nicht durchsuchbare Dokument von vorn an" das wird selten Fehler dan lohnt sich einfaches code zu schreiben.

### E3 · S1 · Welchen Stand hat ein Dokument während der Verarbeitung?

**Warum sich die Frage stellt:** Jana soll sehen, ob ihr Dokument wartet, in Arbeit, durchsuchbar oder gescheitert ist (S2). S1, S3 und S5 bewegen den Stand nacheinander, und S6 muss erkennen, welche Dokumente noch nicht durchsuchbar sind.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Wenige Stände: wartet, in Arbeit, gescheitert; „durchsuchbar“ kommt mit S5. S1 setzt „in Arbeit“ zu Beginn und lässt es danach stehen, S3 ändert nichts, S5 setzt „durchsuchbar“. | Jana sieht nur „in Arbeit“, egal ob gerade gelesen, geteilt oder eingebettet wird. Wo es gescheitert ist, steht nur im Grund. S6 stellt jedes nicht durchsuchbare Dokument von vorn an, auch wenn nur das Einbetten gescheitert war; es wird dann noch einmal gelesen und geteilt. Wenige Stände, wenige Tests. |
| 2 | Ein Stand je Abschnitt: wartet, Text wird gelesen, Text gelesen, gescheitert; S3 ergänzt „wird geteilt“ und „geteilt“, S5 „wird eingebettet“ und „durchsuchbar“. Am gescheiterten Dokument steht, in welchem Abschnitt es scheiterte. | Jana sieht, woran gerade gearbeitet wird. S6 kann bei dem Abschnitt neu ansetzen, der gescheitert ist, und spart das Lesen eines langen PDF, wenn nur Ollama fehlte. Jeder spätere Schritt bringt eigene Stände und Übergänge mit, die getestet werden müssen; S2 braucht für jeden Stand einen Text. |

**Wahl (Person):** E1, E2  Vorschlag 1 , E3 acuh Vorschlag 1 weil Wenige Stände, wenige Test -> einfaches code , weil wir bis 10 MB haben, dann kein Problem "S6 stellt jedes nicht durchsuchbare Dokument von vorn an" das wird selten Fehler dan lohnt sich einfaches code zu schreiben.

### E4 · S1 · Steht der Arbeitsbereich auch am Text jeder Seite?

**Warum sich die Frage stellt:** Der Text des Handbuchs von Jana liegt Seite für Seite in einer eigenen Tabelle (E2 · 1), und jede Seite gehört zu einem Dokument der Nordlicht IT. Mehmet darf keine dieser Seiten je zu sehen bekommen, auch nicht über DOC-03 oder den Neuaufbau in SYS-01.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Die Seite trägt nur den Verweis auf ihr Dokument; der Arbeitsbereich steht allein am Dokument. Jede Abfrage nach Seiten geht über ein Dokument, das über den Arbeitsbereich gefunden wurde (`$tenant->documents()->findOrFail($id)->pages`). | Der Arbeitsbereich steht an genau einer Stelle; eine Seite kann nie zu einem anderen Arbeitsbereich gehören als ihr Dokument. Eine Abfrage aller Seiten eines Arbeitsbereichs (etwa im Neuaufbau, SYS-01) braucht einen Join über die Dokumente. Wer Seiten direkt abfragt, ohne über das Dokument zu gehen, hat keinen Schutz; wie bei TEN-01 · E4 · 2 fängt das nur ein Test. |
| 2 | Die Seite trägt zusätzlich den Arbeitsbereich, den der Job aus dem Dokument übernimmt; ein zusammengesetzter Fremdschlüssel auf Dokument und Arbeitsbereich stellt sicher, dass beide übereinstimmen. | Seiten lassen sich direkt nach Arbeitsbereich abfragen, ohne Join, und jede Abfrage zeigt den Filter selbst. Der Wert steht doppelt; dass er stimmt, sichert die Datenbank über einen zusätzlichen eindeutigen Index am Dokument. Die Textstücke in S3 stehen dann vor derselben Frage und folgen am einfachsten derselben Wahl. |

**Wahl (Person):** E4,  Vorschlag 1

### E5 · S1 · Wie geht S1 mit dem Speicher bei sehr großen PDFs um?

**Warum sich die Frage stellt:** Ein PDF von 9,2 MB, das nur aus Text besteht, ergab in der Messung 6.667 Seiten und 33 MB Text; ein Lauf brauchte dafür 111 MB, der Worker darf 128 MB. Ein absichtlich gebautes PDF von 10 MB kann sich zu Gigabytes Text entpacken (F15).

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Speicher im Job sparen: das Ergebnis von `pdftotext` nach dem Zerlegen freigeben und die Seiten der Reihe nach bereinigen. (`rag/01 · Große Dateien in Teilen verarbeiten`) | Mehr Code im Job; ob 33 MB Text dann unter 128 MB passen, ist offen. Gegen ein PDF, das sich zu Gigabytes entpackt, hilft es nicht. |
| 2 | Die Speichergrenze des Dienstes `worker` auf 512 MB anheben. (`rag/01 · Große Dateien in Teilen verarbeiten`) | Eine Zeile in `docker-compose.yml`. Ohne Grenze für den Container kann ein Ausreißer andere Dienste treffen; mehrere Worker vervielfachen die Grenze; ein PDF, das sich zu Gigabytes entpackt, belastet den Rechner bis 512 MB. |
| 3 | Eine Obergrenze für die Menge an Text: Liefert `pdftotext` mehr, wird es beendet, und das Dokument scheitert sofort mit einem deutschen Grund, wie bei F1. (`rag/01 · Fehler eines Dokuments halten nicht den ganzen Ablauf an`) | Schützt gegen F15 unabhängig von der Speichergrenze. Sehr dichte PDFs über der Grenze werden nicht gelesen. |

**Wahl (Person):** Wegen eine Kompressionsbombe dann machen wir beide deine Vorschläge nicht, Deswegen finde ich dies beste Lösung: "Schutz, unabhängig vom Weg: eine Obergrenze für die Menge an Text. Ist die Ausgabe von pdftotext"

### E6 · S2 · Welche Dokumente zeigt die Seite mit ihrem Stand, und wie lange?

**Warum sich die Frage stellt:** Bis DOC-03 gibt es keine Liste aller Dokumente (O1); die Seite kennt nur, was sie selbst hochgeladen hat. Jana lädt das Handbuch hoch, schließt die Seite (F10) und kommt nach dem Mittag zurück: Ob sie dann den Stand noch sieht, hängt davon ab, wo sich die Seite die Dokumente merkt.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Nur im Speicher der Seite (Pinia-Store): die Dokumente, die in diesem Tab seit dem Öffnen hochgeladen wurden. Neu laden, Abmelden oder ein zweiter Tab beginnen leer. | Wenig Code, nichts im Browser, das DOC-03 wieder entfernen muss. Wer wartet und die Seite offen lässt, sieht den Stand bis zum Ende; wer sie schließt, sieht ihn erst wieder mit DOC-03. Dass die Verarbeitung weiterläuft, zeigt dann nur der Server (Test), nicht die Seite. |
| 2 | Zusätzlich im Browser (`localStorage`) gemerkt, getrennt je Konto: die Kennungen der hochgeladenen Dokumente. Nach Neuladen oder erneutem Anmelden fragt die Seite deren Stand wieder ab; eine Kennung, die 404 ergibt, wird vergessen. | Jana sieht nach dem Schließen und nach dem Abmelden wieder, wie weit ihr Handbuch ist, und A2 lässt sich auf der Seite selbst ausprobieren. Ein zweites Konto im selben Browser sieht die Liste des ersten nicht, und ein fremdes Dokument ergäbe ohnehin 404. Mehr Code (Lesen, Schreiben, Schlüssel je Konto, Fehler beim Zugriff auf den Speicher), den DOC-03 durch die Liste vom Server ersetzt. |

**Wahl (Person):** Also wenn Datei Erfolgreich in DB hochgeladen, dann sollte zum List hinzugefügt, und den User dies List Datei Tabelle schauen kann, und mit Status gezeigt werden, nachdem Dateien gesendet haben, user bekommt Nachricht, dass Datei erfolgreich gesendet aber in Prozess und dannn nach paar Sekunden dies NAchricht verschwinden sollte. E6 · 1

### E7 · S2 · Wie zeigt die Seite die Stände?

**Warum sich die Frage stellt:** Jana lädt zwanzig Dokumente auf einmal hoch (F7); jedes braucht seinen Stand, ein gescheitertes wie Tobias' Scan auch den Grund. DOC-01 hat festgelegt, dass die Liste aller Dokumente mit DOC-03 als sortierbare Tabelle kommt.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Eine Tabelle (`v-data-table`) unter dem Kopf der Seite mit Name, Hochgeladen und Stand; der Stand als farbiger Chip, bei „gescheitert“ der Grund in der Zeile darunter. DOC-03 füllt dieselbe Tabelle später mit allen Dokumenten und ergänzt Spalten. | Die Seite sieht schon so aus, wie sie bleibt; DOC-03 tauscht nur die Quelle der Zeilen. Bis dahin ist die Tabelle leer, solange in diesem Tab (oder nach E6 · 2 mit diesem Konto) nichts hochgeladen wurde, und braucht einen Text für den leeren Zustand. Ein langer Grund muss in die Zeile passen. |
| 2 | Eine schlichte Liste „Gerade hochgeladen“ unter dem Kopf, die nur erscheint, wenn es etwas zu zeigen gibt: je Dokument Name und Stand als Chip, der Grund darunter. DOC-03 ersetzt die Liste durch die Tabelle. | Weniger Aufbau, und die Seite bleibt ohne Uploads so leer wie heute. Ein langer Grund hat Platz. DOC-03 baut die Anzeige neu und entfernt die Liste. |

**Wahl (Person):** Glaube wie E6 Antwort, aber auch gescheiterten Nachricht, falls fehler es gibt


### Vom Schreiber entschieden

| Schritt | Was | Entscheidung | Warum |
| --- | --- | --- | --- |
| S1 | Anstoß | Der Controller stellt den Job an, nachdem das Dokument gespeichert ist; die Antwort des Hochladens bleibt gleich. Scheitert das Anstellen, wird das gemeldet und das Hochladen gelingt trotzdem; das Dokument bleibt „wartet“, bis S6 es anstellt. | Abnahme: Hochladen wartet nicht und gelingt ohne die Dienste; der Job braucht nur MySQL und die Ablage. |
| S1 | Arbeitsbereich im Job | Der Job trägt nur die Kennung des Dokuments; Arbeitsbereich und Pfad kommen aus dem Dokument in MySQL. | In einem Job gibt es weder Anmeldung noch MCP-Token; das Dokument ist die einzige Quelle (offen aus TEN-01 · S3). |
| S1 | Ablauf | Laravel-Queue `database` mit dem Dienst `worker`, ohne zusätzliches Werkzeug. | `rag/01 · Großes Einlesen als verwalteten Ablauf bauen`: Wiederholung, `failed_jobs` und Stand am Dokument reichen für Dokumente bis 10 MB. |
| S1 | Wiederholen | Ein zweiter Lauf ersetzt den Text des Dokuments vollständig in einer Transaktion; zwei Läufe desselben Dokuments zugleich verhindert die Job-Middleware `WithoutOverlapping` je Dokument; ein zweiter Lauf wird verworfen, die Sperre läuft nach 85 s ab, zwischen Zeitgrenze (80 s) und `retry_after` (90 s). | `rag/01 · Jede Aufgabe gefahrlos wiederholbar`. |
| S1 | Scheitern | Ein PDF ohne lesbaren Text, geschützt oder beschädigt, eine leere Markdown-Datei und eine fehlende Datei scheitern sofort ohne Wiederholung mit einem Grund auf Deutsch. Andere Fehler wiederholt die Queue (drei Versuche mit Abstand); erst danach scheitert das Dokument mit einem allgemeinen Grund. Jedes Scheitern schreibt eine Warnung ins Log; andere Dokumente laufen weiter. | `rag/01 · Fehler eines Dokuments halten nicht den ganzen Ablauf an`. |
| S1 | Grund | Der Grund steht als kurzer deutscher Satz am Dokument und wird beim nächsten Lauf geleert. | Er wird so angezeigt, wie er ist, wie die Meldungen des Hochladens. |
| S1 | Erkennen „kein lesbarer Text“ | Bleibt nach der Bereinigung kein Zeichen außer Leerraum übrig, gilt das Dokument als ohne lesbaren Text; der Grund nennt bei einem PDF einen möglichen Scan. | Ticket: Scan, leere Markdown-Datei; Texterkennung ist nicht Teil. |
| S1 | Bereinigung | Zeilenenden auf `\n`, BOM und Nullzeichen entfernt, Unicode in NFC, Leerraum am Zeilenende entfernt, mehr als zwei Leerzeilen auf zwei gekürzt. Markdown-Auszeichnungen bleiben, weil S3 die Überschriften braucht. Kopf- und Fußzeilen werden nicht erkannt. | `rag/01 · Erst sichten, dann passend bereinigen`. |
| S1 | Tabellen | Tabellen und Fließtext werden nicht getrennt; Tabellen bleiben als Text in der Reihenfolge der Seite. | `rag/01 · Tabellen und Fließtext trennen`; Tabellen auswerten ist nicht Teil. |
| S1 | Metadaten | Festgehalten wird nur die Seite; Titel, Autor und Schriften aus dem PDF werden verworfen. | `rag/01 · Gestaltung verwerfen, nützliche Auszeichnungen als Metadaten behalten`; die Seite braucht der Sprung zur Textstelle. |
| S1 | Zeitgrenze | Der Job hat eine Zeitgrenze unter dem `retry_after` der Queue (90 s). | Sonst startet die Queue einen zweiten Lauf, während der erste noch liest. |
| S1 | Bestand | Stand und Grund stehen in der Migration der Tabelle `documents`, ohne eigene Migration; die Datenbank wird neu aufgesetzt. Ein Dokument ohne Stand bekommt „wartet“; angestellt wird es erst mit S6. | Vorgabe der Person (2026-11-24); es gibt noch keinen Betrieb mit echten Daten. |
| S1 | Tests | Kleine echte PDF-Dateien als Testdaten: Text auf zwei Seiten, ohne Text, geschützt, beschädigt. | `rag/01 · Genau parsen`: Das Parsen wird je Format geprüft. |
| S1 | Große Dateien | Ein Aufruf von `pdftotext` für das ganze PDF, getrennt an den Seitenvorschüben; die Seiten werden in Bündeln von 100 gespeichert. Kein Teilen in Seitenbereiche. | `rag/01 · Große Dateien in Teilen verarbeiten`: `pdftotext` liest Seite für Seite, ohne die Datei in PHP zu laden; die Menge an Text begrenzt E5. |
| S1 | Obergrenze für Text (E5 · 3) | Höchstens 20 MB reiner Text je PDF; beim Überschreiten wird `pdftotext` sofort beendet, und das Dokument scheitert ohne Wiederholung. Für Markdown keine eigene Grenze, die Datei hat höchstens 10 MB. | Gemessen: 19,5 MB Text brauchen im Lauf 65 MB, mit 23 MB Grundlast des Workers etwa 88 MB von 128 MB; ein PDF, das sich zu 200 MB entpackt, endet nach etwa 3 s. |
| S1 | Leere Seiten | Jede Seite des PDF bekommt eine Zeile, auch eine ohne Text, damit die Seitenzahlen denen im PDF entsprechen. Kein lesbarer Text heißt: keine Seite hat Text. | `rag/01 · Genau parsen`; der Sprung zur Textstelle braucht die echte Seitenzahl. |
| S1 | Fehlende Datei | Vor dem Lesen prüft der Job, ob die Datei in der Ablage liegt; fehlt sie, scheitert das Dokument sofort. Ein anderer Fehler beim Lesen der Ablage wird wiederholt. | `rag/01 · Fehler eines Dokuments halten nicht den ganzen Ablauf an`: Was nie gelingen kann, wird nicht wiederholt. |
| S1 | Versuche | Zahl der Versuche und Abstand stehen am Job selbst, nicht nur im `--tries=3` des Dienstes `worker`. | Dann gelten sie auch, wenn der Job anders gestartet wird, und die Tests prüfen sie. |
| S2 | Antwort | `DocumentResource` bekommt `status` (`pending`, `processing`, `failed`) und `failure_reason` (sonst `null`); dieselbe Resource gilt für Hochladen und Abfragen, sodass die Seite den Stand „wartet“ schon aus der Antwort des Hochladens kennt. | Eine Form für ein Dokument; die Seite braucht nach dem Hochladen keine zweite Anfrage. |
| S2 | Abfrage | Das Dokument wird über den aktuellen Arbeitsbereich gesucht (`documents()->findOrFail`), ohne implizites Route Model Binding. Eine unbekannte, fremde oder ungültige Kennung ergibt dieselbe 404 im Laravel-Format. | TEN-01 · E4 · 2 und Regel aus `CLAUDE.md`; Binding lüde das Dokument ohne Arbeitsbereich. |
| S2 | Sichtbar für | Jedes Konto des Arbeitsbereichs sieht den Stand, nicht nur das hochladende. | Abnahme A12: Grenze ist der Arbeitsbereich. |
| S2 | Rate-Limit | Kein eigenes Limit; es gilt nur `auth:sanctum`. | Nur Lesen eigener Daten über den Primärschlüssel; die Seite fragt regelmäßig ab, ein knappes Limit träfe gerade Jana mit zwanzig Dokumenten. |
| S2 | Aktuell halten | Die Seite fragt alle 3 Sekunden den Stand jedes Dokuments ab, das „wartet“ oder „in Arbeit“ ist, eine Anfrage je Dokument; sie hört auf, wenn es gescheitert ist (mit S5 auch durchsuchbar), und wenn die Seite verlassen wird. Kein WebSocket und kein Server-Sent Events. | Ein Schritt ist ein Endpunkt; im Stack gibt es keinen Dienst für Push. |
| S2 | Fehler beim Abfragen | Ohne Antwort oder bei 5xx bleibt der letzte Stand stehen und der nächste Takt fragt erneut, ohne Meldung; 401 führt wie überall zum Login; 404 nimmt das Dokument aus der Anzeige. | Ein kurzer Ausfall soll keine Meldungen häufen; 404 heißt, dass es für dieses Konto nicht (mehr) sichtbar ist. |
| S2 | Meldungen (E7) | Die Meldung nach dem Hochladen bleibt, wie sie ist (5 Sekunden). Erkennt die Seite beim Abfragen, dass ein Dokument gescheitert ist, erscheint zusätzlich eine Meldung mit Name und Grund in derselben Snackbar-Reihe; der Grund steht weiter in der Tabelle. | Wahl der Person zu E7. |
| S2 | Abmelden | Abmelden beendet nur den Zugang; ein Test zeigt, dass ein Dokument nach dem Abmelden weiter verarbeitet wird. | Abnahme A2; der Job hängt an keinem Zugang. |

## 4. Abnahme

**Durchlauf (Person):**

# Offene Punkte

- O1 · S2 · A2 · Ohne die Liste aus DOC-03 zeigt die Seite den Stand nur für Dokumente, die sie selbst hochgeladen hat; alle Dokumente des Arbeitsbereichs mit ihrem Stand zeigt erst DOC-03. DOC-03 erweitert denselben Endpunkt um den Text.
- O2 · S3, S5 · F2 · Teilen und Einbetten arbeiten auf bis zu 20 MB Text je Dokument; ihr Speicherbedarf im Worker (128 MB) wird dort gemessen. → S3, S5
- O3 · S1 · A1 · `api` wartet beim Kaltstart weiter darauf, dass Weaviate und Ollama gestartet sind; scheitert ihr Start, etwa an einem belegten Port 11434, startet die API nicht, und Hochladen ist nicht möglich.
- O4 · S1 · F15 · Der Test für die genaue Textgrenze wäre auch mit dem Code vor dem Review grün; dass die Ausgabe ab dem Start gezählt wird und ein Signal an einen schon beendeten `pdftotext` nicht bis in den Job wirft, prüft kein Test.
- O5 · S1 · – · Der Test für die genaue Textgrenze setzt `memory_limit` auf 512M und stellt den alten Wert nicht wieder her; spätere Tests im selben PHP-Prozess laufen mit 512M.
- O6 · S2, S5 · A2, F8, F10 · Vorgabe der Person (2026-11-26): Ab diesem Punkt wird nur Backend-Code umgesetzt, weil die Zeit knapp ist. Die Oberfläche von S2 (Tabelle mit Stand, Grund und Meldung beim Scheitern nach E7) und der Stand „durchsuchbar“ auf der Seite aus S5 werden nicht gebaut; den Stand gibt bis dahin nur der Endpunkt.
