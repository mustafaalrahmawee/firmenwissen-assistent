# Use-Case-Blatt: AUTH-02 · Registrieren

- **Use Case:** AUTH-02, Registrieren: Konto mit E-Mail und Passwort selbst anlegen, gehört zu genau einem Tenant, MVP (Woche 1)
- **Baut auf:** TEN-01
- **Status:** fertig
- **Code:** `api/app/Http/Controllers/AuthController.php`, `api/app/Http/Requests/RegisterRequest.php`, `api/app/Http/Middleware/ThrottleEveryRequest.php`, `api/app/Providers/AppServiceProvider.php`, `api/routes/api.php`, `api/tests/Feature/Http/Controllers/AuthControllerTest.php`, `app/app/stores/auth.ts`, `app/app/pages/register.vue`, `app/app/pages/login.vue`

Das Blatt hält fest, was der Use Case fachlich bedeutet, in welchen Schritten er gebaut wird (ein Schritt ist ein Endpunkt) und was dafür entschieden wurde. Alles mit dem Vermerk `Person` schreibt die Person selbst; der Schreiber trägt es wortgleich ein.

## 1. Ticket

**Worum es geht:** Bisher kommt nur in den Assistenten, wer schon ein Konto hat, und Konten gibt es nur über den Demo-Zugang. Mit diesem Use Case legt eine Person ihr Konto selbst an: Sie nennt ihren Namen, ihre E-Mail-Adresse und ein Passwort. Danach hat sie ein eigenes Konto, mit dem sie sich anmelden kann, und dieses Konto gehört von Anfang an zu genau einem Arbeitsbereich. Dort kann sie später Dokumente hochladen und Fragen stellen, getrennt von allen anderen Firmen.

**Wer es braucht und warum:** IT-Betriebe, die den Assistenten mit ihren eigenen Unterlagen ausprobieren wollen, und Besucher der Live-Demo, denen der geteilte Demo-Zugang nicht reicht. Ohne Registrieren müsste jemand jedes Konto von Hand in der Datenbank anlegen. Wer den Assistenten testen will, bliebe auf den Demo-Zugang angewiesen, den sich alle Besucher teilen: Dort lädt niemand etwas Vertrauliches hoch, und was einer hochlädt, sehen alle anderen.

**Beispiel:** Carla Brandt leitet die Elbwerk Software GmbH und hat vom Assistenten gehört. Sie öffnet ihn, landet auf der Anmeldeseite und wechselt von dort zum Registrieren. Sie gibt ihren Namen, den Namen ihrer Firma, ihre E-Mail-Adresse und ein Passwort ein, das die Passwortregel erfüllt. Kurz darauf sieht sie die Startseite, ihren Namen und einen eigenen, noch leeren Arbeitsbereich „Elbwerk Software GmbH“. Von den Arbeitsbereichen der Nordlicht IT GmbH, der Hafenkante Systemhaus GmbH und der Demo sieht sie nichts. Am nächsten Tag meldet sie sich auf ihrem Laptop mit denselben Daten an und ist im selben Arbeitsbereich. Mehmet Aydin versucht später, sich mit Carlas E-Mail-Adresse ein zweites Konto anzulegen; das gelingt nicht, und an Carlas Konto ändert sich nichts.

**Was schiefgehen kann:**

- Zur E-Mail-Adresse gibt es schon ein Konto, auch in anderer Groß- und Kleinschreibung.
- Jemand probiert Adressen durch, um an der Antwort zu erkennen, wer schon ein Konto hat.
- Jemand registriert sich mit der Adresse, die für den Demo-Zugang vorgesehen ist, bevor oder nachdem es ihn gibt.
- Das Passwort erfüllt die Passwortregel nicht, oder Carla vertippt sich dabei, ohne es zu merken.
- Carla wählt ein sehr langes Passwort.
- Ein Feld bleibt leer, die E-Mail-Adresse hat keine gültige Form oder der Name ist übermäßig lang.
- Carla klickt zweimal schnell auf „Registrieren“, oder zwei Personen registrieren sich im selben Augenblick mit derselben Adresse.
- Jemand nennt beim Registrieren von Hand einen bestehenden Arbeitsbereich, um dort hineinzukommen.
- Eine zweite Person der Elbwerk Software GmbH registriert sich und erwartet, bei Carla zu landen.
- Die Registrierung bricht mittendrin ab, sodass nur das Konto oder nur der Arbeitsbereich entstanden ist.
- Jemand legt automatisch sehr viele Konten hintereinander an.
- Eine angemeldete Person ruft die Seite zum Registrieren auf.
- Der Server ist nicht erreichbar, während Carla sich registrieren will.

**Abnahme:**

- [ ] Von der Anmeldeseite kommt man zum Registrieren und von dort zurück zur Anmeldeseite.
- [ ] Mit Name, Firma, noch freier E-Mail-Adresse und einem Passwort, das die Passwortregel erfüllt, entsteht ein Konto. Mit diesen Daten kann man sich danach anmelden, auch auf einem anderen Gerät.
- [ ] Das neue Konto gehört zu genau einem Arbeitsbereich mit dem Namen der Firma. Die Anwendung zeigt ihn nach der Anmeldung, und er enthält nichts von anderen Firmen oder aus der Demo.
- [ ] Nennt die Anfrage einen bestehenden Arbeitsbereich, ändert das nichts: Durch Registrieren kommt niemand in den Arbeitsbereich einer anderen Person.
- [ ] Für eine E-Mail-Adresse, zu der es schon ein Konto gibt, entsteht kein zweites, auch nicht in anderer Groß- und Kleinschreibung. Das bestehende Konto bleibt unverändert, und die Person bekommt eine verständliche Meldung auf Deutsch.
- [ ] Ein Passwort, das die Passwortregel nicht erfüllt, wird mit einer Meldung abgewiesen, die die Regel nennt. Es gilt dieselbe Regel wie beim Anmelden.
- [ ] Fehlende oder ungültig geformte Eingaben werden am jeweiligen Feld gemeldet, ohne dass ein Konto entsteht.
- [ ] Ein doppelter Klick oder zwei gleichzeitige Registrierungen mit derselben Adresse ergeben genau ein Konto.
- [ ] Es entsteht nie ein Konto ohne Arbeitsbereich und nie ein Arbeitsbereich ohne Konto, auch wenn die Registrierung mittendrin scheitert.
- [ ] Nach zu vielen Registrierungen in kurzer Zeit werden weitere eine Zeit lang abgewiesen, und die Seite sagt das.
- [ ] Der Demo-Zugang lässt sich weiterhin anlegen und zurücksetzen; eine Registrierung kann ihn weder übernehmen noch verhindern.
- [ ] Wer angemeldet ist und die Seite zum Registrieren aufruft, landet auf der Startseite.
- [ ] Ist der Server nicht erreichbar, sagt die Seite das, statt nichts zu tun.

**Nicht Teil:** Anmelden, Abmelden und der Demo-Zugang gehören zu AUTH-01, die Trennung der Daten nach Arbeitsbereich zu TEN-01. E-Mail-Adresse bestätigen, Passwort vergessen oder ändern, Konto oder Arbeitsbereich löschen. Kolleginnen und Kollegen einladen oder einem bestehenden Arbeitsbereich beitreten, den Arbeitsbereich wechseln oder umbenennen, Rollen und Rechte. Schutz vor automatischen Registrierungen, der über eine Begrenzung der Versuche hinausgeht. Der Zugang für KI-Clients gehört zu MCP-01.

**Begriffe:**

- **Registrieren:** ein Konto selbst anlegen, ohne dass jemand anderes es einrichtet.
- **Konto:** Name, E-Mail-Adresse und Passwort einer Person; die E-Mail-Adresse gibt es im Assistenten nur einmal.
- **Arbeitsbereich (Tenant):** der abgeschlossene Bereich einer Firma im Assistenten (siehe TEN-01). Jedes Konto gehört zu genau einem.
- **Passwortregel:** Ein Passwort hat mindestens 8 Zeichen, enthält Buchstaben und Ziffern und beginnt mit einem Großbuchstaben (aus AUTH-01).
- **Demo-Zugang:** das fertige Konto mit bekannten Zugangsdaten für die Live-Demo (aus AUTH-01).

## 2. Schnitt

| Nr | Schritt | Was er tut | Oberfläche | Deckt ab | Status |
| --- | --- | --- | --- | --- | --- |
| S1 | `POST /api/v1/register` | Legt für die Person ein Konto mit Name, E-Mail-Adresse und Passwort an, das von Anfang an zu genau einem Arbeitsbereich gehört. | Seite zum Registrieren: Formular mit Vuetify-Regeln, Fehler am Feld, Meldung bei Sperre und Serverausfall; Wechsel zwischen Anmeldeseite und Registrieren in beide Richtungen; Seitenwächter `guest` an der neuen Seite | Wechsel zwischen Anmeldeseite und Registrieren; Konto entsteht und taugt zum Anmelden, auch auf anderem Gerät; genau ein Arbeitsbereich, ohne Fremdes und ohne Demo; Arbeitsbereich in der Anfrage ändert nichts; zweite Person derselben Firma landet nicht bei Carla; vergebene Adresse auch in anderer Schreibweise, bestehendes Konto unverändert; Adressen durchprobieren; Passwortregel, Tippfehler und sehr langes Passwort; leere und ungültige Eingaben; doppelter Klick und gleichzeitige Registrierung; nie Konto ohne Arbeitsbereich oder umgekehrt, auch bei Abbruch; Sperre nach zu vielen Registrierungen; Adresse des Demo-Zugangs, Demo-Zugang bleibt anlegbar und zurücksetzbar; angemeldete Person auf der Seite zum Registrieren; Server nicht erreichbar | fertig |

**Nicht abgedeckt:** alles abgedeckt

## 3. Entscheidungen

### E1 · S1 · Wie heißt der Arbeitsbereich, der beim Registrieren entsteht?

**Warum sich die Frage stellt:** Jedes neue Konto bekommt einen eigenen Arbeitsbereich, und jeder Arbeitsbereich braucht einen Namen; die Anwendung zeigt ihn nach der Anmeldung in der Seitenleiste. Das Ticket nennt als Eingaben nur Name, E-Mail-Adresse und Passwort, umbenennen ist nicht Teil.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Zusätzliches Pflichtfeld „Firma“ im Formular; sein Inhalt wird der Name des Arbeitsbereichs. | Carla sieht „Elbwerk Software GmbH“ in der Seitenleiste. Ein Feld mehr im Formular, mit Regeln und Meldungen; das Ticket bekommt diese Eingabe dazu. Zwei Personen derselben Firma erhalten zwei Arbeitsbereiche mit demselben Namen und sehen nicht, dass sie getrennt sind. |
| 2 | Kein zusätzliches Feld; der Arbeitsbereich bekommt den Namen der Person (etwa „Carla Brandt“). | Das Formular bleibt wie im Ticket. In der Seitenleiste steht der Name der Person zweimal, als Arbeitsbereich und als Konto. Da niemand umbenennen kann, bleibt der Name, bis ein späterer Use Case das erlaubt. |

**Wahl (Person):** E1 Vorschlag 1.

### E2 · S1 · Wie schützt das Registrieren die Adresse des Demo-Zugangs?

**Warum sich die Frage stellt:** Der Befehl für den Demo-Zugang findet das Konto über die E-Mail-Adresse aus der Konfiguration (die mit `demo` beginnen muss) und macht dessen Arbeitsbereich zum Demo-Arbeitsbereich. Registriert sich jemand vorher mit dieser Adresse, übernimmt der Befehl dessen Konto samt Arbeitsbereich und Dokumenten; registriert er sich danach, ist sie schon vergeben.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Das Registrieren weist jede Adresse ab, die mit `demo` beginnt (ohne Rücksicht auf Groß- und Kleinschreibung), dieselbe Regel wie im Befehl. | Kein Konto aus einer Registrierung kann je vom Befehl getroffen werden, auch nicht nach einer Änderung von `DEMO_USER_EMAIL`. Echte Personen mit Adressen wie `demo.team@…` oder `demokrit@…` können sich nicht registrieren und bekommen eine Meldung. |
| 2 | Das Registrieren weist nur genau die konfigurierte Adresse `DEMO_USER_EMAIL` ab (ohne Rücksicht auf Groß- und Kleinschreibung). | Nur die eine Adresse ist gesperrt. Wird `DEMO_USER_EMAIL` später auf eine Adresse geändert, die schon jemand registriert hat, setzt der Befehl dessen Passwort zurück, beendet seine Anmeldungen und macht seinen Arbeitsbereich zum Demo-Arbeitsbereich. |

**Wahl (Person):** E2 Vorschlag 1.

### E3 · S1 · Was zählt beim Rate-Limit fürs Registrieren?

**Warum sich die Frage stellt:** Jemand legt automatisch viele Konten an oder probiert Adressen durch, um zu sehen, welche vergeben sind. Beides wechselt die E-Mail-Adresse bei jedem Versuch, eine Grenze kann also nur an der IP-Adresse hängen; Demo-Besucher sitzen manchmal hinter derselben.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Ein benanntes Rate-Limit an der Route: höchstens 5 Anfragen pro Minute je IP-Adresse, jede Anfrage zählt (wie beim Anmelden). | Sehr wenig Code, Laravel antwortet mit 429 und `Retry-After`. Von einer IP-Adresse lassen sich so trotzdem bis zu 7200 Konten am Tag anlegen oder ebenso viele Adressen prüfen. |
| 2 | Zwei Grenzen je IP-Adresse: höchstens 5 Anfragen pro Minute und höchstens 20 pro Tag, jede Anfrage zählt. | Massenhaft Konten oder Adressen prüfen geht nur noch mit vielen IP-Adressen. Eine Gruppe hinter einer gemeinsamen IP-Adresse (Firma, Konferenz) wird nach 20 Versuchen am Tag abgewiesen, auch bei Tippfehlern. Die Seite muss dann eine längere Wartezeit melden. |

**Wahl (Person):** E3 Vorschlag 2

### E4 · S1 · Was geschieht mit Passwörtern über 72 Byte?

**Warum sich die Frage stellt:** Bcrypt berücksichtigt nur die ersten 72 Byte eines Passworts (offener Punkt aus AUTH-01, dort für das Registrieren vertagt). Wählt Carla ein sehr langes Passwort, gilt der Rest stillschweigend nicht.

| Vorschlag | Was gebaut würde | Was daraus folgt |
| --- | --- | --- |
| 1 | Das Registrieren weist Passwörter über 72 Byte mit einer Meldung am Feld ab, die die Grenze nennt; das Formular prüft dasselbe vorab. | Jedes Konto hat ein Passwort, das vollständig zählt. Bei Umlauten und anderen Sonderzeichen ist die Grenze in Zeichen kleiner als 72, die Meldung muss das verständlich sagen. Das Anmelden bleibt unverändert. |
| 2 | Keine Grenze außer einer großzügigen Höchstlänge in Zeichen (etwa 255); bcrypt nutzt die ersten 72 Byte. | Kein zusätzlicher Code. Bei einem Passwort über 72 Byte meldet auch ein anderes Passwort mit denselben ersten 72 Byte Carla an; das betrifft nur sehr lange Passwörter. |

**Wahl (Person):** E4 Vorschlag 1

### Vom Schreiber entschieden

| Schritt | Was | Entscheidung | Warum |
| --- | --- | --- | --- |
| S1 | Antwort | 201 mit `{ data: { token } }` wie beim Anmelden, mit demselben Ablauf; die Seite speichert den Zugang, lädt die Person und geht zur Startseite. | Ticket: Carla sieht direkt danach die Startseite; eine Anfrage statt Registrieren und Anmelden nacheinander. |
| S1 | Transaktion | Arbeitsbereich und Konto entstehen in einer Transaktion; das Konto wird über die Beziehung zugeordnet. | Nie ein Konto ohne Arbeitsbereich oder umgekehrt; Zuordnung nicht massenweise zuweisbar (TEN-01). |
| S1 | Vergebene Adresse | 422 mit Meldung unter `errors.email`; scheitert eine gleichzeitige Registrierung erst am eindeutigen Index, gibt es dieselbe Antwort statt 500. | Abnahme verlangt eine verständliche Meldung; ohne E-Mail-Bestätigung (nicht Teil) lässt sich nicht verbergen, dass die Adresse vergeben ist, das bremst nur das Rate-Limit. |
| S1 | Groß- und Kleinschreibung | Die Adresse wird wie eingegeben gespeichert; die Prüfung auf eine vergebene Adresse vergleicht ohne Rücksicht auf Groß- und Kleinschreibung. | In MySQL (`utf8mb4_unicode_ci`) ist das beim Anmelden und am Index ohnehin so; SQLite in den Tests unterscheidet sonst. |
| S1 | Tippfehler im Passwort | Zweites Feld „Passwort wiederholen“, das übereinstimmen muss. | Fall aus dem Ticket: Carla vertippt sich, ohne es zu merken. |
| S1 | Felder der Anfrage | Nur Name, E-Mail-Adresse, Passwort und Wiederholung werden gelesen; alles andere, auch ein Arbeitsbereich, wird nicht beachtet. | Der Arbeitsbereich kommt nie aus der Anfrage (`CLAUDE.md`). |
| S1 | Oberfläche | Seite `/register` im Layout `guest` mit Seitenwächter `guest`; Verweise zwischen Anmeldeseite und Registrieren; Aktion `register` im Store `auth` wie `login`. | Wie die Anmeldeseite; angemeldete Personen landen über den bestehenden Wächter auf der Startseite. |
| S1 | Meldung bei 429 | Die Seite meldet zu viele Registrierungen ohne genaue Wartezeit. | `Retry-After` ist über CORS nicht freigegeben; eine Minute und ein Tag lassen sich im Browser nicht unterscheiden. |
| S1 | Öffentliche Route | `POST /register` steht wie `POST /login` außerhalb von `auth:sanctum`; der Test für geschützte Routen nimmt beide aus. | Wer sich registriert, hat noch keinen Zugang. |

# Offene Punkte

- S1 · Re-Review R1: Beim ersten Treffer eines Zählers setzt `RateLimiter::hit()` den Wert unter Umständen mit `put` auf 1 zurück, nachdem eine gleichzeitige Anfrage ihn schon erhöht hat. Bei genau gleichzeitigen Anfragen kann so trotz Zählen vor dem Prüfen eine Registrierung mehr durchkommen.
- S1 · Re-Review R7: Zwei wirklich gleichzeitige Registrierungen mit derselben Adresse sind nicht getestet. Dass genau ein Konto mit Arbeitsbereich und Zugang bleibt, ließe sich nur mit zwei Verbindungen auf MySQL prüfen; die Tests laufen gegen SQLite im Speicher.
- S1 · Re-Review R8: Die Seite zum Registrieren ist nicht im Browser abgenommen: Wechsel zur Anmeldeseite, Startseite mit Arbeitsbereich nach Erfolg, Fehler am Feld, doppelter Klick, 429, Serverausfall und die Weiterleitung angemeldeter Personen.
