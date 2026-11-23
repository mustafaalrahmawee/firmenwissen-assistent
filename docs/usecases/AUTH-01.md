# Use-Case-Blatt: AUTH-01 · Anmelden und Abmelden

- **Use Case:** AUTH-01, Anmelden und Abmelden mit Sanctum-Token, MVP (Woche 1)
- **Baut auf:** nichts
- **Status:** Schritte
- **Code:** –

Das Blatt hält fest, was der Use Case fachlich bedeutet, in welchen Schritten er gebaut wird (ein Schritt ist ein Endpunkt) und was dafür entschieden wurde. Alles mit dem Vermerk `Person` schreibt die Person selbst; der Schreiber trägt es wortgleich ein.

## 1. Ticket

**Worum es geht:** Der Assistent enthält internes Firmenwissen. Wer ihn benutzen will, weist sich deshalb zuerst mit E-Mail und Passwort aus. Danach ist die Person angemeldet, sieht die Anwendung statt der Anmeldeseite und bleibt angemeldet, bis sie sich selbst abmeldet oder ihre Anmeldung ungültig wird. Mit dem Abmelden endet der Zugang auf diesem Gerät sofort.

**Wer es braucht und warum:** Alle, die mit dem Assistenten arbeiten, also Mitarbeitende eines IT-Betriebs, und Besucher der Live-Demo mit dem Demo-Zugang. Ohne Anmeldung könnte jeder, der die Adresse kennt, Dokumente lesen und Fragen stellen. Außerdem wüsste die Anwendung nicht, wer vor ihr sitzt; darauf bauen später der Arbeitsbereich, die Dokumente und der Chat auf. Ohne Abmelden bliebe der Zugang auf einem geteilten oder verliehenen Rechner offen.

**Beispiel:** Jana Petrov arbeitet im Support der Nordlicht IT GmbH. Morgens öffnet sie den Assistenten und landet auf der Anmeldeseite, weil sie noch nicht angemeldet ist. Sie gibt ihre E-Mail-Adresse und ihr Passwort ein und kommt auf die Startseite. Mittags lädt sie die Seite neu und ist weiterhin angemeldet. Am Nachmittag arbeitet sie am Rechner im Besprechungsraum, meldet sich dort an und nach dem Termin wieder ab. Ihr Kollege Tobias Kern setzt sich danach an denselben Rechner, sieht nur die Anmeldeseite und kommt auch über den Zurück-Knopf des Browsers nicht an Janas Sitzung.

**Was schiefgehen kann:**

- Jana vertippt sich beim Passwort oder bei der E-Mail-Adresse.
- Jemand gibt eine E-Mail-Adresse ein, zu der es kein Konto gibt, und will daran erkennen, ob die Adresse im System bekannt ist.
- Jemand probiert für ein Konto viele Passwörter hintereinander durch.
- Ein Feld bleibt leer oder die E-Mail-Adresse hat keine gültige Form.
- Jemand gibt ein Passwort ein, das die Passwortregel nicht erfüllt, zum Beispiel zu kurz, ohne Ziffer oder mit kleinem Anfangsbuchstaben.
- Jana klickt zweimal schnell auf „Anmelden“.
- Jana ist auf zwei Geräten gleichzeitig angemeldet und meldet sich auf einem ab.
- Jana klickt zweimal auf „Abmelden“ oder meldet sich ab, obwohl ihre Anmeldung schon ungültig ist.
- Eine nicht angemeldete Person ruft direkt die Adresse einer geschützten Seite auf.
- Eine angemeldete Person ruft die Anmeldeseite auf.
- Die Anmeldung wird ungültig, während die Anwendung noch im Browser offen ist.
- Der Server ist nicht erreichbar, während Jana sich anmelden oder abmelden will.

**Abnahme:**

- [ ] Mit richtiger E-Mail-Adresse und richtigem Passwort ist man angemeldet und sieht die Startseite.
- [ ] Mit falschen Daten bleibt man auf der Anmeldeseite und bekommt eine verständliche Meldung auf Deutsch. Die Meldung verrät nicht, ob die E-Mail-Adresse bekannt ist.
- [ ] Fehlende oder ungültig geformte Eingaben werden gemeldet, ohne dass eine Anmeldung versucht wird, die gelingen könnte.
- [ ] Ein Passwort, das die Passwortregel nicht erfüllt, wird mit einer Meldung abgewiesen, die die Regel nennt.
- [ ] Das Passwort des Demo-Zugangs erfüllt die Passwortregel.
- [ ] Nach zu vielen Fehlversuchen in kurzer Zeit werden weitere Versuche eine Zeit lang abgewiesen, und die Seite sagt das.
- [ ] Nach dem Neuladen der Seite ist man weiterhin angemeldet.
- [ ] Die Anwendung zeigt, wer angemeldet ist, und bietet das Abmelden an.
- [ ] Nach dem Abmelden sieht man die Anmeldeseite, und der bisherige Zugang dieses Geräts funktioniert nicht mehr, auch nicht, wenn ihn jemand aufbewahrt hat.
- [ ] Das Abmelden auf einem Gerät beendet die Anmeldung auf einem anderen Gerät nicht.
- [ ] Ohne Anmeldung führt jede geschützte Seite zur Anmeldeseite; nach der Anmeldung landet man auf der Seite, die man aufrufen wollte.
- [ ] Wer angemeldet ist und die Anmeldeseite aufruft, landet auf der Startseite.
- [ ] Wird die Anmeldung ungültig, führt die nächste Aktion zur Anmeldeseite.
- [ ] Ist der Server nicht erreichbar, sagt die Seite das, statt nichts zu tun.
- [ ] Es gibt einen Demo-Zugang, mit dem man sich anmelden kann, ohne dass jemand von Hand ein Konto in der Datenbank anlegt.

**Nicht Teil:** Selbst registrieren gehört zu AUTH-02. Passwort vergessen oder ändern, E-Mail-Adresse bestätigen, Nutzer verwalten und Rollen. Die Zuordnung der Person zu einem Arbeitsbereich und die Trennung der Daten gehören zu TEN-01. Der Zugang für KI-Clients gehört zu MCP-01.

**Begriffe:**

- **Anmeldung:** der Nachweis, wer man ist, und der Zustand danach. Sie gilt je Gerät beziehungsweise Browser.
- **Passwortregel:** Ein Passwort hat mindestens 8 Zeichen, enthält Buchstaben und Ziffern und beginnt mit einem Großbuchstaben.
- **Geschützte Seite:** jede Seite außer der Anmeldeseite.
- **Demo-Zugang:** ein fertiges Konto mit bekannten Zugangsdaten für die Live-Demo.

## 2. Schnitt

| Nr | Schritt | Was er tut | Oberfläche | Deckt ab | Status |
| --- | --- | --- | --- | --- | --- |
| S1 | Befehl `app:create-demo-user` | Legt den Demo-Zugang mit bekannten Zugangsdaten an oder setzt ihn zurück. | entfällt – läuft im Terminal | Demo-Zugang ohne Handarbeit; Demo-Passwort erfüllt die Passwortregel | fertig |
| S2 | `POST /api/v1/login` | Meldet die Person mit E-Mail und Passwort an und gibt ihr einen Zugang für dieses Gerät. | Anmeldeseite: Formular mit Vuetify-Regeln, Fehler am Feld, Meldung bei falschen Daten, Sperre und Serverausfall, danach Startseite | richtige Daten führen zur Startseite; falsche Daten mit Meldung ohne Hinweis auf bekannte Adressen; leere und ungültige Eingaben; Passwortregel; Sperre nach Fehlversuchen; doppelter Klick; Server nicht erreichbar | fertig |
| S3 | `GET /api/v1/user` | Sagt der Anwendung, wer mit dem gespeicherten Zugang angemeldet ist. | Pinia-Store `auth` lädt die Person; Name und E-Mail in der Seitenleiste | zeigt, wer angemeldet ist; nach dem Neuladen weiterhin angemeldet; ungültig gewordene Anmeldung führt zur Anmeldeseite | fertig |
| S4 | Middleware `auth` | Schickt Personen ohne Anmeldung von jeder geschützten Seite zur Anmeldeseite und nach der Anmeldung zurück. | Seitenwächter in `app/app/middleware/`, Anmeldeseite übernimmt das Ziel | geschützte Seite ohne Anmeldung; Rückkehr zur gewünschten Seite; Zurück-Knopf nach dem Abmelden | fertig |
| S5 | Middleware `guest` | Schickt angemeldete Personen von der Anmeldeseite zur Startseite. | Seitenwächter in `app/app/middleware/` | angemeldete Person ruft die Anmeldeseite auf | offen – Code vorhanden |
| S6 | `POST /api/v1/logout` | Beendet die Anmeldung auf diesem Gerät. | Knopf „Abmelden“ in der Seitenleiste, danach Anmeldeseite | Abmelden wird angeboten; alter Zugang ungültig, auch aufbewahrt; andere Geräte bleiben angemeldet; doppeltes Abmelden und schon ungültige Anmeldung; Server nicht erreichbar | offen |

**Nicht abgedeckt:** alles abgedeckt

## 3. Entscheidungen

### E1 · S1 · Woher kommen E-Mail-Adresse und Passwort des Demo-Zugangs?

**Warum sich die Frage stellt:** Besucher der Live-Demo sollen sich mit bekannten Zugangsdaten anmelden, und das Passwort muss die Passwortregel erfüllen. Offen ist, ob diese Daten überall gleich sind oder je Umgebung festgelegt werden.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | E-Mail-Adresse und Passwort stehen fest im Befehl. | Lokal, in den Tests und auf dem Demo-Server gelten dieselben Daten, die jeder im Repository nachlesen kann. Ein Test prüft das Passwort gegen die Passwortregel. Wer die Daten ändern will, ändert den Code. Wenig Aufwand. |
| 2 | E-Mail-Adresse und Passwort kommen aus der Konfiguration (Werte in `.env`, mit Vorgabewerten). | Der Demo-Server kann eigene Daten haben, ohne dass der Code sich ändert. Weil ein falsch gesetztes Passwort erst beim Ausführen sichtbar wird, prüft der Befehl es gegen die Passwortregel und bricht mit einer Meldung ab, wenn es sie nicht erfüllt. Etwas mehr Aufwand: Konfigurationseintrag, Einträge in `.env.example`, ein Test für den Abbruch. |

**Wahl (Person):** E-Mail-Adresse und Passwort kommen aus der Konfiguration (Werte in .env, mit Vorgabewerten).

### E2 · S1 · Was geschieht mit bestehenden Anmeldungen, wenn der Befehl den Demo-Zugang zurücksetzt?

**Warum sich die Frage stellt:** Viele Besucher teilen sich den Demo-Zugang, und jede Anmeldung legt einen eigenen Zugang für das jeweilige Gerät an. Wenn der Befehl später noch einmal läuft, zum Beispiel nach einem Update des Demo-Servers, sind möglicherweise noch Besucher angemeldet.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Der Befehl setzt nur Name und Passwort zurück. Bestehende Anmeldungen bleiben. | Wer gerade in der Demo ist, arbeitet ungestört weiter. Die Zugänge der Besucher sammeln sich an, bis jeder sich selbst abmeldet (S6). |
| 2 | Der Befehl setzt Name und Passwort zurück und beendet alle Anmeldungen des Demo-Zugangs. | Nach dem Zurücksetzen ist niemand mehr angemeldet. Wer noch in der Demo ist, landet bei seiner nächsten Aktion auf der Anmeldeseite (Verhalten aus S3). Die Zugänge sammeln sich nicht über längere Zeit an. |

**Wahl (Person):** Der Befehl setzt Name und Passwort zurück und beendet alle Anmeldungen des Demo-Zugangs.

### E3 · S1 · Woran erkennt der Befehl, dass ein vorhandenes Konto der Demo-Zugang ist?

**Warum sich die Frage stellt:** Der Befehl findet den Demo-Zugang über die E-Mail-Adresse aus der Konfiguration. Steht dort aus Versehen die Adresse einer echten Person, würde er deren Konto auf das bekannte Demo-Passwort zurücksetzen.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Das Konto trägt eine eigene Kennzeichnung als Demo-Zugang (zusätzliche Spalte), und der Befehl setzt nur ein so gekennzeichnetes Konto zurück. | Eine falsche Adresse kann kein fremdes Konto treffen. Dafür braucht die Tabelle der Konten eine zusätzliche Spalte. |
| 2 | Die E-Mail-Adresse des Demo-Zugangs muss mit `demo` beginnen, sonst bricht der Befehl ab. | Keine Änderung an der Tabelle. Adressen echter Personen sind ausgeschlossen, solange sie nicht selbst mit `demo` beginnen. |

**Wahl (Person):** Nein will hier kein extra Spalte, einfach das email demo muss fangt mit prefax demo an.

### E4 · S2 · Was zählt beim Rate-Limit fürs Anmelden?

**Warum sich die Frage stellt:** Jemand probiert für ein Konto viele Passwörter hintereinander durch. Nach zu vielen Fehlversuchen in kurzer Zeit sollen weitere Versuche eine Zeit lang abgewiesen werden. Gleichzeitig teilen sich viele Besucher den Demo-Zugang, manche hinter derselben IP-Adresse.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Ein benanntes Rate-Limit an der Route: höchstens 5 Anfragen pro Minute je E-Mail-Adresse und IP-Adresse. Jede Anfrage zählt, auch eine erfolgreiche oder eine mit leerem Feld. | Sehr wenig Code. Laravel antwortet selbst mit 429 und sagt in `Retry-After`, wie lange die Sperre dauert. Wer sich richtig, aber oft anmeldet, wird ebenfalls gebremst, zum Beispiel mehrere Demo-Besucher hinter derselben IP-Adresse. |
| 2 | Nur Fehlversuche zählen: nach 5 falschen Passwörtern je E-Mail-Adresse und IP-Adresse ist eine Minute gesperrt; eine erfolgreiche Anmeldung setzt den Zähler zurück. | Wer die richtigen Daten hat, wird nie gebremst, auch nicht beim Demo-Zugang. Etwas mehr Code: Zählen und Zurücksetzen in der Anmeldung selbst, eigene Antwort mit der verbleibenden Wartezeit. Anfragen mit leerem oder ungültigem Feld zählen nicht mit, sie prüfen aber auch kein Passwort. |

**Wahl (Person):** "E4 · S2 . Vorschlag 1"

### E5 · S2 · Wie lange gilt ein Zugang nach der Anmeldung?

**Warum sich die Frage stellt:** Die Person bleibt angemeldet, auch nach dem Neuladen, bis sie sich abmeldet oder ihre Anmeldung ungültig wird. Offen ist, ob eine Anmeldung auch von selbst endet.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Kein Ablauf. Der Zugang gilt, bis die Person sich abmeldet (S6) oder der Demo-Zugang zurückgesetzt wird (S1). | Nichts zusätzlich zu bauen. Ein vergessener oder kopierter Zugang bleibt unbegrenzt gültig, und die Zugänge von Geräten, auf denen sich niemand abmeldet, sammeln sich an. |
| 2 | Fester Ablauf nach einer Zeit ab der Anmeldung, zum Beispiel 7 Tage, einstellbar in `.env`. | Ein vergessener oder kopierter Zugang wird von selbst ungültig; danach führt die nächste Aktion zur Anmeldeseite (S3). Wer täglich arbeitet, meldet sich trotzdem nach Ablauf neu an. Abgelaufene Zugänge bleiben in der Datenbank, bis ein geplanter Aufräum-Befehl sie löscht; dafür braucht es einen Eintrag im Zeitplan. |

**Wahl (Person):** "E5 · S2 . Vorschlag 2"

### E6 · S3 · Wann lädt die Anwendung, wer angemeldet ist?

**Warum sich die Frage stellt:** Nach dem Neuladen liegt im Browser nur der gespeicherte Zugang; wer dahintersteht, muss die Anwendung erst beim Server erfragen. Davon hängt ab, was die Seitenleiste anfangs zeigt und worauf sich die Seitenwächter in S4 und S5 stützen können.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Das Layout für angemeldete Personen lädt die Person, sobald es erscheint, falls sie noch nicht geladen ist. | Die Seite erscheint sofort; Name und E-Mail-Adresse kommen in der Seitenleiste kurz danach dazu. S4 und S5 wissen beim Seitenwechsel nur, ob ein Zugang gespeichert ist, nicht ob er noch gilt; ein ungültiger Zugang fällt erst bei dieser Anfrage auf und führt dann zur Anmeldeseite. |
| 2 | Beim Start der Anwendung lädt ein Plugin die Person, wenn ein Zugang gespeichert ist, bevor die erste Seite erscheint. | Die erste Seite wartet auf eine Anfrage, danach ist die Person überall bekannt. S4 und S5 können sich auf die geladene Person stützen; ein ungültiger Zugang führt gleich beim Start zur Anmeldeseite. Ist der Server beim Start nicht erreichbar, muss das Plugin den Start trotzdem fortsetzen. |

**Wahl (Person):** E6: Vorschlag 2

### E7 · S4 · Woran erkennt der Seitenwächter, dass jemand angemeldet ist?

**Warum sich die Frage stellt:** Meist sind gespeicherter Zugang und geladene Person beide da oder beide weg. Ist der Server beim Start aber nicht erreichbar, bleibt der Zugang gespeichert und die Person leer (E6). Dann muss der Wächter entscheiden, ob er die geschützte Seite zeigt.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Angemeldet ist, wer eine geladene Person im Store hat. | Bei Serverausfall beim Start landet die Person auf der Anmeldeseite, obwohl ihr Zugang gespeichert bleibt; nach dem nächsten Neuladen mit erreichbarem Server ist sie wieder drin. Eine geschützte Seite erscheint nie ohne bekannte Person. S5 kann dieselbe Prüfung umgekehrt nutzen. |
| 2 | Angemeldet ist, wer einen gespeicherten Zugang hat. | Bei Serverausfall beim Start erscheint die geschützte Seite ohne Person in der Seitenleiste; ob der Zugang noch gilt, klärt die nächste Anfrage (401 führt zur Anmeldeseite). Seiten müssen damit rechnen, dass die Person fehlt. |

**Wahl (Person):** E7 Vorschlag 1

### E8 · S4 · Wie kommt der Seitenwächter an jede geschützte Seite?

**Warum sich die Frage stellt:** Geschützt ist jede Seite außer der Anmeldeseite. Später kommen viele Seiten dazu (Dokumente, Chat, MCP, Auswertung).

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Jede geschützte Seite nennt den Wächter selbst (`middleware: 'auth'`), wie heute die Startseite. | Man sieht an jeder Seite, dass sie geschützt ist. Vergisst eine neue Seite den Eintrag, ist sie ungeschützt; die API verlangt trotzdem einen Zugang, aber die Seite erscheint. |
| 2 | Der Wächter läuft global vor jeder Seite; nur Seiten, die als Gast-Seite markiert sind (heute die Anmeldeseite), nimmt er aus. | Eine neue Seite ist ohne weiteren Eintrag geschützt. Die Ausnahme steht an der Anmeldeseite; der Eintrag `middleware: 'auth'` an der Startseite fällt weg. |

**Wahl (Person):** E8 Vorschlag 1

### Vom Schreiber entschieden

| Schritt | Was | Entscheidung | Warum |
| --- | --- | --- | --- |
| S1 | Passwortregel | Einmal als `Password::defaults()` in `AppServiceProvider`: mindestens 8 Zeichen, Buchstaben, Ziffern, Großbuchstabe am Anfang. | S1 prüft das Demo-Passwort dagegen; S2 und AUTH-02 verwenden dieselbe Regel, statt sie zu wiederholen. |
| S1 | Zweiter Lauf | Der Befehl sucht den Demo-Zugang über die E-Mail-Adresse und legt ihn an oder setzt ihn zurück. | So entsteht beim zweiten Lauf kein zweites Konto, und die E-Mail-Adresse ist ohnehin eindeutig. |
| S1 | Name des Demo-Zugangs | Kommt wie E-Mail-Adresse und Passwort aus der Konfiguration (`DEMO_USER_NAME`, Vorgabe `Demo`). | Alle Daten des Demo-Zugangs stehen an einer Stelle. |
| S1 | `DatabaseSeeder` | Bleibt unverändert, der Demo-Zugang entsteht nur über den Befehl. | Ein einziger Weg zum Demo-Zugang. |
| S2 | Falsche Zugangsdaten | Unbekannte Adresse und falsches Passwort ergeben dieselbe Antwort: 422 mit derselben Meldung unter `errors.credentials`; die Seite zeigt sie oben unter dem Einleitungstext, und sie verschwindet nach 8 Sekunden (Wunsch der Person). | Die Meldung verrät nicht, ob die Adresse bekannt ist; ein 401 würde in `$api` die Umleitung zur Anmeldeseite auslösen. |
| S2 | Antwortzeit | Auch bei unbekannter Adresse wird ein Passwort-Hash geprüft, damit beide Fälle gleich lange dauern. | Sonst ließe sich an der Antwortzeit erkennen, ob die Adresse bekannt ist. |
| S2 | Passwortregel beim Anmelden | Der Form Request prüft das Passwort gegen `Password::defaults()`, bevor es verglichen wird, mit einer deutschen Meldung, die die ganze Regel nennt. | Abnahme: Ein Passwort, das die Regel nicht erfüllt, wird mit einer Meldung abgewiesen, die die Regel nennt. |
| S2 | Antwort | Die Anmeldung gibt nur den Zugang zurück (`token`), keine Daten der Person. | Wer angemeldet ist, liefert S3; S2 baut nichts für einen späteren Schritt. |
| S2 | Deutsche Meldungen | Im Form Request (`messages()`), dazu in der Seite für Sperre und Serverausfall; keine Sprachdateien. | Nur diese Meldungen braucht der Schritt. |
| S2 | Doppelter Klick | Der Knopf ist gesperrt, solange die Anfrage läuft. | Ein zweiter Klick schickt keine zweite Anmeldung. |
| S3 | Antwort | `id`, `name` und `email` über eine API Resource in der Hülle `data`. | Mehr braucht die Seitenleiste nicht; jede Antwort steht in `data` (Vorgabe der Person, `CLAUDE.md`). |
| S3 | Antwort von `login` | Wird in S3 auf `{ data: { token } }` umgestellt, mit Test und Store. | Jede Antwort steht in `data` (Vorgabe der Person); `login` aus S2 kam noch ohne Hülle. |
| S3 | Rate-Limit | `GET /user` bekommt kein eigenes Rate-Limit. | Die Route verlangt einen gültigen Zugang und gibt nur die eigenen Daten zurück; missbrauchbar ist sie nicht. |
| S3 | Ungültiger Zugang | Bei jeder Antwort 401, egal von welcher Anfrage, löscht `$api` den gespeicherten Zugang, leert die Person im Store und geht zur Anmeldeseite. | Ein Zugang kann ablaufen, während die Anwendung offen ist; sonst bliebe die Person im Store stehen, obwohl ihr Zugang nicht mehr gilt. |
| S4 | Ziel nach der Anmeldung | Der Wächter hängt die gewünschte Seite als `redirect` an die Anmeldeseite; die Anmeldeseite folgt nur Pfaden, die mit genau einem `/` beginnen, sonst geht sie zu `/`. | Sonst ließe sich über einen Link zur Anmeldeseite auf eine fremde Adresse weiterleiten. |
| S4 | Ziel bei 401 | Führt eine 401-Antwort zur Anmeldeseite, nimmt `$api` die aktuelle Seite als `redirect` mit. | Wer während der Arbeit abgemeldet wird, landet nach der Anmeldung wieder dort. |

# Offene Punkte

- S1 · Bcrypt berücksichtigt nur die ersten 72 Byte eines Passworts. Für den Demo-Zugang steht die Grenze als Hinweis in `.env.example`; ob Passwörter normaler Nutzer (Anmelden in S2, Registrieren in AUTH-02) über 72 Byte abgelehnt werden, wird später entschieden.
- S2 · Abgelaufene Zugänge löscht `sanctum:prune-expired` täglich über den Zeitplan. In `docker-compose.yml` läuft aber noch kein Dienst mit `schedule:work`, bis dahin bleiben sie in der Datenbank (sie gelten trotzdem nicht mehr).
- S2 · Re-Review R2: `Str::ascii()` gleicht im Schlüssel des Rate-Limits nur Akzente und Groß- und Kleinschreibung an. MySQL (`utf8mb4_unicode_ci`) hält auch Vollbreitenbuchstaben wie `ｊａｎａ…` für dieselbe Adresse wie `jana…`, sie zählen weiter getrennt.
- S2 · Re-Review R4: Sanctum prüft neben `expires_at` weiter die aktuelle Einstellung `sanctum.expiration`. Ein Token mit 14 Tagen wird bei einer Senkung auf 7 Tage am achten Tag ungültig und nach Rückkehr auf 14 Tage wieder gültig, solange sein Datensatz noch da ist.
- S2 · Re-Review N1: Der Ersatz-Hash in `AuthController` hat feste bcrypt-Kosten von 12. Weicht `BCRYPT_ROUNDS` davon ab, dauert die Prüfung für eine unbekannte Adresse anders lang als für ein vorhandenes Konto, und die Antwortzeit kann verraten, ob die Adresse bekannt ist.
- S3 · Re-Review R2: ofetch stoppt die Zeitgrenze, sobald die HTTP-Header da sind. Sendet die API die Header und liefert den Inhalt nicht zu Ende, wartet das Start-Plugin weiter.
- S3 · Re-Review R4: Ein 401 während der Nutzung (Store schon gefüllt, Seite offen) ist noch nicht im Browser geprüft; das geht erst, wenn eine Seite ohne Neuladen die API aufruft (S6 oder später).
- S3 · Re-Review N1: Das Zurückrollen in `login` löscht den gespeicherten Zugang auch dann, wenn ein anderer Tab ihn inzwischen durch einen neuen ersetzt hat. Wie bei R3 aus S3 betrifft das nur mehrere Tabs mit genau diesem Timing.
- S4 · Re-Review R3: Die Ausnahme in `$api` greift nur für genau `/login`. Kommt ein 401 auf `/login/?redirect=…`, wird das Ziel durch die Anmeldeseite selbst ersetzt, und der nächste Versuch landet auf `/`.
- S4 · Re-Review R4: Im Browser ungeprüft sind die Rückkehr mit Fragment (`#…`) und der Zurück-Knopf bei geleerter Person; Letzteres geht erst mit S6.
- S4 · Re-Review N1: `new URL()` in `redirectTarget()` wirft bei einem ungültigen Ziel wie `/\[`; dann bleibt die Anmeldeseite stehen und meldet fälschlich „Server nicht erreichbar“.
- S4 · Re-Review N2: Ein Ziel wie `/.//example.invalid` besteht die Origin-Prüfung, ergibt nach der Normalisierung aber `//example.invalid`; Nuxt bricht die Navigation ab. Auch das zusammengesetzte Ziel muss mit genau einem `/` beginnen.
