# PROMPT.md – Arbeitskontext Ritterlager Manager

Diese Datei ist dafür gedacht, in neuen ChatGPT-/Codex-Chats als Startkontext eingefügt zu werden. Sie beschreibt Ziel, Architektur, Fachlogik, aktuelle Version, Regeln und Arbeitsweise des Projekts.

## Rolle des Assistenten

Du unterstützt bei Entwicklung, Debugging, Review und Weiterentwicklung der PHP-Webapp **Ritterlager Manager**.

Arbeite pragmatisch, vorsichtig und bestandsschonend. Die vorhandene Codebasis ist maßgeblich. Ändere keine fachliche Logik, entferne keine Funktionen und strukturiere nichts großflächig um, wenn es für den konkreten Auftrag nicht nötig ist.

Wenn Code geändert wird, liefere am Ende immer:

- Ursache
- Umsetzung
- geänderte Dateien
- DB-Änderungen / Migrationen
- Tests
- bekannte Einschränkungen
- Download-/Artefaktlink oder GitHub-Commit, sofern vorhanden

Sprache: Deutsch. Ton: direkt, sachlich, praxisnah.

## Projektname

**Ritterlager Manager**

Repository: `matzeisda/rttr`

Aktueller bekannter Stand: **v0.14.11**

## Ziel der App

Der Ritterlager Manager ist eine Webapp zur Organisation eines jährlichen Ritterlagers beziehungsweise Zeltlagers. Die App ersetzt mehrere Excel-, Word- und Papierlisten durch eine zentrale mobile und Desktop-fähige Oberfläche.

Die App soll Lagerleitung, Bereichsleitungen und Mitarbeiter im Lageralltag unterstützen. Wichtig sind schnelle mobile Bedienung, klare Tagesübersichten, zuverlässige Personen-/Rangdaten und einfache Punkteerfassung.

Die App ist keine generische Vereinsverwaltung. Sie ist auf den konkreten Lagerkontext zugeschnitten:

- Lagerjahre
- Orden/Zelte
- Teilnehmer
- Mitarbeiter
- Programm
- Essen/Speiseplan
- Dienste
- Punkte
- Rangsystem
- Importe
- Backups und Betrieb

## Technischer Stack

- PHP 8.3
- MySQL/MariaDB
- PDO
- UTF-8
- HTML/CSS/JavaScript ohne großes Frontend-Framework
- Mobile-first Oberfläche
- PWA-Grundlage mit Manifest und Service Worker
- Hosting-Kontext: Netcup/Plesk/Shared Hosting

## Grundlegende Architekturregeln

`public/` ist der einzige Webroot.

Diese Ordner dürfen nicht direkt öffentlich erreichbar sein:

- `app/`
- `config/`
- `database/`
- `docs/`
- `routes/`
- `scripts/`
- `storage/`

Projektstruktur:

```text
httpdocs/
  app/
  config/
  database/
  docs/
  public/
  routes/
  scripts/
  storage/
```

Domain Document Root:

```text
httpdocs/public
```

Nicht korrekt:

```text
httpdocs
httpdocs/public/public
```

## Git- und Repository-Regeln

Das Repository soll nur Quellcode und technische Dokumentation enthalten.

Nicht ins Repository gehören:

```text
config/database.php
config/*.local.php
storage/logs/*
storage/backups/*
storage/temp/*
storage/uploads/*
storage/imports/*
storage/documents/*
storage/import_sources/*
storage/setup.lock
public/setup.php
public/migration.php
```

Ordner können über `.gitkeep` erhalten bleiben.

Produktive Daten, Zugangsdaten, Backups, Uploads, Importdateien und Logs dürfen nicht veröffentlicht werden.

## Deployment-Kontext

Server/Plesk-Pfad aus dem Projektkontext:

```text
/var/www/vhosts/hosting203599.a2e6d.netcup.net/app.ritterlager.com/httpdocs
```

Domain:

```text
https://app.ritterlager.com
```

Richtige Aufrufe:

```text
https://app.ritterlager.com/login
https://app.ritterlager.com/migration.php
```

Nicht korrekt:

```text
https://app.ritterlager.com/public/migration.php
```

`public/setup.php` und `public/migration.php` sind nur temporäre Helfer und müssen nach Nutzung gelöscht werden.

Nach Code-Updates Migrationen ausführen:

```bash
php scripts/maintenance/migrate.php
```

Oder temporär per Browser:

```text
https://app.ritterlager.com/migration.php
```

Danach `public/migration.php` wieder löschen.

## Sicherheit

Wichtige Sicherheitsregeln:

- `public/` ist einziger Webroot.
- `config/database.php` bleibt außerhalb von Git.
- `storage/` enthält produktive Daten und bleibt außerhalb von Git.
- PINs werden gehasht gespeichert.
- Login per Personenauswahl + PIN.
- Session-ID-Rotation beim Login.
- CSRF-Schutz zentral verwenden.
- Uploads prüfen und nicht direkt öffentlich ausliefern.
- Setup- und Migrationsdateien nach Nutzung löschen.
- Fehler loggen, aber keine sensiblen Daten öffentlich ausgeben.
- Keine Secrets, DB-Zugänge oder produktiven Daten in Antworten oder Commits schreiben.

## Designvorgaben

Designstil: modern, mobil optimiert, klar, lagerpraktisch.

Farben:

```css
--blau: #2B49E0;
--blau-tief: #1B2E8C;
--navy: #17204A;
--mint: #0FDFA0;
--mint-tief: #07B383;
--mint-hell: #E4F6F0;
--grau: #717AA0;
--linie: #E7EAF6;
--wolke: #F5F7FF;
--weiss: #FFFFFF;
```

Typografie:

- Montserrat für Überschriften, Kennzahlen und Navigation.
- Inter für UI und Fließtext.

Navigation:

- Desktop: Sidebar.
- Mobil: Bottom Navigation.

Bei CSS-/Asset-Problemen immer an Service-Worker-Cache denken. Häufiger Fix: Browser DevTools → Application → Service Workers → Unregister → hart neu laden.

## Fachliche Grundlogik

### Lagerjahr

Ein Lagerjahr bildet eine konkrete Durchführung ab, zum Beispiel 2025 oder 2026.

Viele Daten hängen am Lagerjahr:

- Orden/Zelte
- Lagerstatus von Personen
- Programm
- Essen
- Dienste
- Punkte
- Auswertungen

Es gibt ein aktives Lagerjahr. Die meisten Ansichten beziehen sich automatisch darauf.

### Orden/Zelt

Orden und Zelt sind fachlich dasselbe Objekt.

Standardorden:

- Johanniter
- Falkner
- Samariter
- Petrusker
- Morgensternritter
- Malteser

Orden/Zelte haben Farben. Freie `color_hex`-Farben müssen in Dashboard, Personenlisten, Punkteansichten und Auswertungen korrekt verwendet werden. `color_key` ist nur Fallback.

### Person

Eine Person kann Teilnehmer, Mitarbeiter oder beides sein.

Personen sind lagerjahrübergreifend. Der Lagerstatus ist lagerjahrbezogen.

Wichtige Personendaten:

- Name / Anzeigename
- Beiname
- Geburtstag
- Adresse
- Telefon
- E-Mail
- Notfallkontakte / Sorgeberechtigte
- Allergien
- Essenshinweise
- medizinische Hinweise
- interne Bemerkungen

Sensible Informationen nur mit passendem Recht anzeigen.

### Lagerstatus

Der Lagerstatus beschreibt pro Lagerjahr:

- aktiv/inaktiv
- Teilnehmer
- Mitarbeiter
- Orden/Zelt-Zuordnung
- aktueller Rang
- Folgerang
- Beförderungsstatus
- Notizen

### Rangsystem

Ränge:

- Knappe
- Ritter
- Freiherr
- Graf
- Markgraf
- Landgraf
- Fürst
- Herzog
- Großherzog

Rangwechsel-Logik:

- Knappe → Ritter: 310 Punkte
- Ritter → Freiherr: 320 Punkte
- Freiherr → Graf: 330 Punkte
- Graf → Markgraf: 340 Punkte
- Markgraf → Landgraf: 345 Punkte
- Landgraf → Fürst: 350 Punkte
- Fürst → Herzog: 280 Punkte
- Großherzog: Ernennung, keine normale Punkteschwelle

Wichtige Regel:

Ein erreichter Rang darf im Folgejahr nicht verloren gehen oder zurückgestuft werden. Das gilt besonders für Herzog und Großherzog.

Importlogik muss Ranghistorien bis zum Quelljahr lesen. Leere spätere Jahre löschen keinen Rang. Niedrigere spätere Werte überschreiben keinen höheren bereits erreichten Rang.

Beispiel Tobias Huber:

- Geburtstag laut Excel: `10.10.2001`
- 2013: Freiherr
- 2015: Graf
- 2016: Markgraf
- 2017: Herzog
- 2025 leer
- erwarteter aktueller Rang: Herzog

Wenn Tobias Huber als 126 Jahre alt oder als Freiherr erscheint, ist die Import-/Anzeige-/Geburtstagslogik fehlerhaft.

## Rollen und Rechte

Rollen:

- `admin`
- `lagerleitung`
- `bereichsleitung`
- `mitarbeiter`
- `lesen`

Typische Rechte:

- `dashboard.view`
- `program.view`
- `program.manage`
- `meals.view`
- `meals.manage`
- `duties.view`
- `duties.manage`
- `persons.view`
- `persons.manage`
- `persons.sensitive.view`
- `points.order.create`
- `points.manage`
- `exams.view`
- `exams.manage`
- `imports.manage`
- `settings.manage`
- `backups.manage`
- `webdav.manage`
- `audit.view`

Normale Mitarbeiter sollen zunächst vor allem operative Funktionen nutzen, zum Beispiel Ordnungspunkte erfassen. Admin und Lagerleitung sehen die vollständige Verwaltung.

## Seiten und Module

### Login

Login per Benutzer-/Personenauswahl und PIN. Kein klassischer E-Mail-Login.

Wichtig:

- Benutzerliste muss aus aktiven Benutzern geladen werden.
- PIN wird gehasht geprüft.
- Fehlversuche werden begrenzt/protokolliert.
- Nach Login Rechte prüfen und Navigation entsprechend einschränken.

### Übersicht / Dashboard

Operative Startseite für das aktive Lagerjahr.

Zeigt:

- Kennzahlen
- aktive Orden/Zelte mit Farben
- nächste Programmpunkte
- Essen des Tages
- offene/aktuelle Dienste
- Geburtstage im Lager
- schnelle Tagesmodule

### Lagerjahre

Verwaltung von Lagerjahren.

Funktionen:

- Lagerjahr anlegen/bearbeiten
- aktives Lagerjahr setzen
- Start-/Enddatum definieren
- Standardorden vorbereiten
- Tagesmodule auf Lagerzeitraum begrenzen

### Orden/Zelte

Verwaltung der fachlichen Einheiten.

Funktionen:

- Orden/Zelt anlegen/bearbeiten
- Kurzzeichen und Farbe pflegen
- aktiv/inaktiv setzen
- Mitarbeiter zuordnen
- in Dashboard, Personen, Punkten und Auswertungen farbig anzeigen

### Personen & Lagerstatus

Zentrale Personenverwaltung.

Funktionen:

- Personen suchen und filtern
- Stammdaten bearbeiten
- Lagerstatus bearbeiten
- Geburtstag und Alter zum Lagerstart anzeigen
- Beiname anzeigen
- Rang anzeigen
- Orden/Zelt-Zuordnung anzeigen
- Notfallkontakte anzeigen/pflegen
- sensible Hinweise schützen

Wichtige Bugs aus Historie:

- Suche darf keine mehrfach verwendeten PDO-Platzhalter verwenden, sonst `HY093`.
- `birthdate = ''` ist bei strict SQL ungültig. DATE-Felder nur mit `IS NULL` oder gültigen Daten vergleichen.
- Excel-Fehldaten wie `1900-01-06` dürfen nicht als echte Geburtstage behalten werden.
- Ranganzeige muss höchsten/letzten plausiblen bekannten Rang berücksichtigen.

### Programm

Tagesprogramm des Lagers.

Funktionen:

- Programmpunkte pro Tag
- Uhrzeit, Titel, Beschreibung, Ort
- Kategorie
- Verantwortliche
- Zuordnung zu Orden/Zelten
- wiederkehrende Programmpunkte
- Timeline
- Import aus DOCX-Programm

### Essen / Speiseplan

Speiseplan und Mahlzeiten.

Funktionen:

- Frühstück/Mittag/Abend und weitere Mahlzeiten
- Uhrzeit/Beschreibung
- Portionen
- Zutaten
- vegetarische/allergierelevante Hinweise
- Küchenteam
- Tagesanzeige auf Dashboard
- Import aus ODS-Speiseplan

### Dienste

Aufgaben und Verantwortlichkeiten.

Dienstarten:

- Küchendienst
- Spüldienst
- Platzdienst
- Nachtwache
- Lagerfeuerdienst
- Materialdienst
- Kiosk
- Feuerwart
- Flaggenwart
- Zeltwart
- Sanitätsdienst
- Lagerwart

Funktionen:

- Diensttypen verwalten
- Dienste pro Tag erfassen
- Personen oder Orden/Zelte zuordnen
- Rotation vorbereiten
- offene Dienste anzeigen
- aus Dienstansicht direkt Punkte erfassen

### Ordnung / Punkte

Punktesystem für Personen und Orden/Zelte.

Punktearten:

- Ordnung Zelt: global pro Orden/Zelt
- Ordnung persönlich: persönlich
- Sauberkeit Geschirr: persönlich
- Prüfung: persönlich
- Spiele: global pro Orden/Zelt
- Platzdienst: global pro Orden/Zelt
- Küchendienst: global pro Orden/Zelt
- Bonus Freizeit: persönlich, begrenzt je Mitarbeiter/Teilnehmer/Tag/Freizeit-Slot

Einträge werden storniert statt hart gelöscht.

### Punkte: Spielwertung

Für Wettbewerbe zwischen Orden/Zelten.

- Platzierungen erfassen
- mehrere Orden/Zelte pro Platz erlauben
- Punkte je Platz vergeben
- globale Ordens-/Zeltpunkte speichern

### Punkte: Zeltbewertung

Bewertung eines Ordens/Zelts, typischerweise Ordnung/Sauberkeit.

- Orden/Zelt auswählen
- voreingestellte Bewertung verwenden
- Plus/Minus anpassen
- Punkte für Orden/Zelt speichern

### Punkte: Geschirrbewertung

Personenbezogene Bewertung innerhalb eines Ordens/Zelts.

- Orden/Zelt auswählen
- Teilnehmer dieses Ordens anzeigen
- Standardwert, zum Beispiel 5 Punkte
- Plus/Minus je Kind
- persönliche Punkte speichern

### Punkte: Dienstpunkte

Punkte für Dienste/Zusatzdienste.

- Dienstart auswählen
- Personen oder Orden/Zelt bewerten
- Standardwert, zum Beispiel 3 Punkte
- Anpassung per Plus/Minus

### Ränge, Lerneinheiten, Prüfungen

Grundlage für Ausbildungs-/Prüfungslogik.

Vorbereitete Lerneinheiten:

- Knappe: Knoten, Natur, Waldläufer
- Ritter: Wappen, Waffen, Feuer
- Freiherr: Küche, Lageraufbau, Erste Hilfe

Prüfungsergebnisse und Ränge sind für Auswertungen und Beförderungen relevant.

### Auswertungen

Auswertungen sollen Personen, Orden/Zelte, Punkte und Ränge zusammenführen.

Funktionen:

- Punktestände
- Rangentwicklung
- CSV-Export-Grundlage
- Ordens-/Zeltwertung
- Personenwertung

### Importe

Importe übernehmen bestehende Lagerdaten.

Quellen aus Projektkontext:

- `Zeltlager 2025 Manager.xlsx`
- `Speiseplan_2026.ods`
- `Ritterlagerprogramm 2026.docx`
- `Aufgabenverteilung 2026.docx`

Importfunktionen:

- Standardorden anlegen
- Zeltlager Manager 2025 importieren
- Dummy-Lagerjahr 2000 aus 2025 erzeugen
- Programm 2026 importieren
- Speiseplan 2026 importieren
- Dienste/Aufgabenverteilung 2026 importieren

Wichtige Importregeln:

- Importdateien nicht in Git speichern.
- Personen robust wiedererkennen.
- Namen normalisieren.
- Bei Geburtsdatum vertauschte Namensreihenfolge erkennen.
- Dubletten vermeiden.
- Sichere Dubletten zusammenführen, nicht hart löschen.
- Geburtstag plausibilisieren.
- Ranghistorie nicht nur Zieljahr lesen.
- Leere Jahre überschreiben nichts.
- Niedrigere Ränge überschreiben keine höheren.
- MySQL strict mode beachten: keine leeren Strings in DATE-Vergleichen.

### Backup

Backup-Modul für Betrieb.

Funktionen:

- lokale Backups unter `storage/backups`
- Backup-Läufe protokollieren
- SQL-Dump beziehungsweise PHP-Fallback vorbereiten
- WebDAV-Sync optional
- Cron-Anbindung vorbereiten

Backups nicht in Git.

### WebDAV

WebDAV-Sync für Backups.

Funktionen:

- aktiv/inaktiv
- URL
- Benutzername
- Passwort
- Zielordner
- Timeout
- Verbindung testen
- letztes Backup senden
- automatischer Sync nach Backup
- Sync-Protokoll

WebDAV-Zugangsdaten nicht in Git.

### Geplante Aufgaben / Cron

HTTP-Cron und CLI-Cron als Ergänzung für Shared Hosting/Plesk.

Funktionen:

- Aufgabenliste
- Status
- empfohlenes Intervall
- letzte Ausführung
- Dauer
- Rückgabecode
- Ausgabe/Fehler
- verarbeitete Einträge
- kopierbare Cron-URLs
- Token je Aufgabe
- Token serverseitig nur gehasht speichern
- Regenerieren von Tokens
- Aufgabe aktivieren/deaktivieren
- DB-Lock gegen parallele Läufe

Keine beliebigen Dateien per Cron ausführen. Nur feste Task-Namen erlauben.

### System / Einstellungen

Adminbereich für:

- Grundeinstellungen
- Lagerjahre
- WebDAV
- Backups
- Importe
- Rollen/Rechte
- Audit/Logs
- Setup/Migration

## Versionen und wichtige Historie

### v0.1

Projektgerüst, Router, Views, Logger, SecurityHeaders, CSRF, `app_versions`, `settings`, `audit_log`.

### v0.2

Personen, Rollen, PIN-Login, Rechte, Benutzer, Login-Attempts.

### v0.3

Designsystem, Sidebar, Mobile Navigation, PWA-Grundlage.

### v0.4

Lagerjahr und Orden/Zelte.

### v0.5

Programmplanung.

### v0.6

Essen/Speiseplan.

### v0.7

Dienste.

### v0.8

Teilnehmerdaten, Notfallkontakte, sensible Hinweise.

### v0.9

Ordnungspunkte.

### v0.10

Auswertungen, Rang, Lerneinheiten, Prüfungen.

### v0.11

Importe.

### v0.12

Backup, Cron, Logs, Setup.

### v0.13.x

Setup-/Migration-/WebDAV-Fixes, Importvorlagen, Standardorden, DOCX/ODS/XLSX-Importkorrekturen, Lagertagbegrenzung.

### v0.14.x

Punkteansichten, freie Ordensfarben, Ränge/Beinamen, Ranghistorie, Personen-Suche, Dubletten-Fix, Geburtstags-/Rang-Importfixes.

Aktueller bekannter Stand: **v0.14.11**.

## Bekannte Fehlerbilder und Ursachen

### `Personen konnten nicht geladen werden`

Häufige Ursache: SQL/PDO-Fehler in der Suche, zum Beispiel doppelte benannte Platzhalter.

Falsch:

```sql
LIKE :query OR other_column LIKE :query
```

Robuster:

```sql
LIKE :query_name OR other_column LIKE :query_email
```

### `SQLSTATE[HY093]`

Häufig bei doppelt verwendeten PDO-Platzhaltern oder nicht passenden Bindings.

### `Incorrect DATE value: ''`

MySQL strict mode. DATE-Spalten niemals mit leerem String vergleichen.

Falsch:

```sql
birthdate = ''
```

Richtig:

```sql
birthdate IS NULL
```

oder gültige DATE-Werte verwenden.

### Falsches Alter, zum Beispiel 126 Jahre

Typisch durch falsch importiertes Excel-Datum, zum Beispiel `1900-01-06`. Geburtstage müssen plausibilisiert und alte 1900-Daten ersetzt werden, wenn korrekte Historienwerte vorhanden sind.

### Falscher Rang nach Import

Ranglogik darf nicht nur das Zieljahr lesen. Historie bis zum Quelljahr prüfen, höchsten/letzten plausiblen Rang übernehmen und keine Rückstufung durch leere oder niedrigere Werte erlauben.

### Farben im Dashboard falsch

Dashboard-Views dürfen nicht nur `color_key` verwenden. `color_hex` muss bevorzugt werden. `color_key` nur Fallback.

### Änderungen sichtbar falsch / altes CSS

Service Worker Cache prüfen. Browser hart neu laden, Service Worker unregister.

## Arbeitsweise bei Änderungen

1. Codebasis prüfen, nicht raten.
2. Ursache identifizieren.
3. Minimal-invasiv ändern.
4. Bestehende Funktionen erhalten.
5. Bei DB-Änderungen neue Migration erstellen.
6. Keine produktiven Daten/Secrets einbauen.
7. PHP-Syntax prüfen.
8. ZIP oder Git-Commit erzeugen.
9. Antwort mit Ursache, Umsetzung, Dateien, DB, Tests, Einschränkungen.

## Tests, die nach Änderungen sinnvoll sind

- `php -l` für geänderte PHP-Dateien.
- Wenn möglich Syntaxprüfung aller PHP-Dateien.
- ZIP-Prüfung bei ausgeliefertem Paket.
- MigrationRunner-Format prüfen.
- Keine Migration darf Spalten verwenden, die in alten Installationen nicht existieren, ohne vorher zu prüfen.
- Strict SQL beachten.
- Import erneut mit vorhandenen Daten testen, wenn Importlogik geändert wurde.
- Service Worker Version bei Frontend-Änderungen erhöhen.

## Antwortformat für neue Entwicklungsstände

Am Ende einer Entwicklungsantwort immer etwa so strukturieren:

```text
Ursache
...

Umsetzung
...

Geänderte Dateien
...

DB-Änderungen
...

Tests
...

Bekannte Einschränkungen
...

Nächster Schritt
...
```

Bei Artefakten Downloadlinks angeben. Bei GitHub-Arbeit Commit-SHA nennen.

## Wichtige Nicht-Ziele

- Keine Mandantenfähigkeit einbauen, solange nicht ausdrücklich verlangt.
- Keine komplette Framework-Migration.
- Keine sensiblen Daten in Git.
- Keine bestehenden Module entfernen.
- Keine öffentliche Exposition von `storage`, `config`, `database`, `scripts`.
- Keine willkürlichen Cron-Dateiaufrufe.
- Keine harten Löschungen bei fachlichen Daten, wenn Storno/Deaktivieren möglich ist.

## Kurzprompt für neue Chats

Wenn nur wenig Platz ist, diesen Kurzprompt verwenden:

```text
Du arbeitest am Projekt Ritterlager Manager, Repository matzeisda/rttr. PHP 8.3, MySQL/MariaDB, PDO, mobile-first PWA, public/ ist einziger Webroot. Die App verwaltet Lagerjahre, Orden/Zelte, Personen, Lagerstatus, Programm, Essen, Dienste, Punkte, Ränge, Importe, Backup, WebDAV und Cron. Orden/Zelt ist fachlich dasselbe. Login per Personenauswahl + PIN. Keine Secrets oder produktiven storage-Daten in Git. Aktueller Stand v0.14.11. Bitte bestandsschonend arbeiten, keine Funktionen entfernen, bestehende Fachlogik beachten. Bei Änderungen immer Ursache, Umsetzung, geänderte Dateien, DB-Änderungen, Tests und bekannte Einschränkungen liefern. Wichtige Bugs beachten: PDO-Platzhalter nicht doppelt verwenden, DATE nie mit leerem String vergleichen, Ranghistorie darf nicht zurückstufen, Geburtstage aus Excel plausibilisieren, color_hex vor color_key nutzen, Service Worker Cache bei Frontend-Problemen beachten.
```
