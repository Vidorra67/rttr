# Ritterlager Manager v0.14.11

Dieses Paket enthält den Hotfix für die fehlgeschlagene Migration 000026 (`installed_at` → `applied_at`).

Nach dem Hochladen `https://app.ritterlager.com/migration.php` ausführen und danach `public/migration.php` wieder löschen.

# Ritterlager Manager

Aktuelle Version: v0.14.5

Hinweis v0.14.5: Die Personensuche wurde repariert und Ränge werden aus dem letzten bekannten Lagerstatus als dauerhafter Rang angezeigt, wenn im aktiven Lagerjahr noch kein Rang gesetzt ist.

Hinweis v0.14.2: Ränge sind dauerhaft und können im Folgejahr nicht zurückgestuft werden. Küchendienst ist jetzt eine globale Ordens-/Zeltwertung. Bonus Freizeit ist eine persönliche Wertung mit max. 5 Punkten je Mitarbeiter, Teilnehmer, Tag und Freizeit-Slot.

Der Ritterlager Manager ist eine mobile-first PWA zur Verwaltung des Ritterlagers. Die Anwendung soll Programm, Essen, Dienste, Personen, Orden/Zelte und später Ordnungspunkte abbilden.

## Zweck

Die Software unterstützt den Lageralltag. Sie ist keine allgemeine Vereinsverwaltung und kein generisches Admin-Tool.

## Technik

- PHP 8.3
- MySQL oder MariaDB
- PDO
- UTF-8 durchgehend
- eigener schlanker Router
- mobile-first PWA-Grundlage mit Manifest und Service Worker
- geschützter Storage außerhalb von `public/`

## Grundentscheidungen

- Keine Mandantenfähigkeit.
- Stattdessen gibt es Lagerjahre mit aktivem Lagerjahr.
- Orden und Zelt sind fachlich dasselbe Objekt.
- Eine Person kann Teilnehmer, Mitarbeiter oder beides sein.
- Alle Nutzer melden sich über Dropdown und 4 bis 6 stellige PIN an.
- Adminrechte sind Rollenrechte, kein separater Login.
- PWA mit Online-Pflicht. Es wird keine vollständige Offline-Synchronisation gebaut.

## Ordnerstruktur

```text
app/        Anwendungscode
config/     Konfiguration außerhalb des Webroots
database/   Migrationen und Seeds
docs/       Dokumentation
public/     einziger Webroot
routes/     Web- und Cron-Routen
scripts/    CLI- und Wartungsskripte
storage/    Uploads, Backups, Logs, Imports und Temp-Dateien
tests/      manuelle Testlisten und spätere Smoke-Tests
```

## Installation

1. Dateien auf den Server hochladen.
2. Webroot in Plesk oder Hosting auf `public/` setzen.
3. Datenbank anlegen.
4. `config/database.php` aus `config/example.database.php` erstellen oder Umgebungsvariablen setzen.
5. Schreibrechte für `storage/` und Unterordner prüfen.
6. Migrationen ausführen:

```bash
php scripts/maintenance/migrate.php
```

7. Ersten Adminbenutzer anlegen, falls noch kein Benutzer existiert:

```bash
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```

8. Im Browser `/login` öffnen und anmelden.
9. Die initiale PIN direkt ändern.


### Optionales Web-Setup

Ab v0.12.1 kann die Erstinstallation alternativ einmalig über den Browser erfolgen:

```text
/setup.php
```

Das Setup schreibt `config/database.php`, führt alle Migrationen aus und kann den ersten Admin anlegen. Danach muss `public/setup.php` sofort vom Server gelöscht werden. Details stehen in `docs/deployment/SETUP.md`.

## Konfiguration

Beispielkonfigurationen liegen in:

```text
config/example.app.php
config/example.database.php
```

Für lokale Tests kann `config/app.php` und `config/database.php` angepasst werden. Produktive Secrets dürfen nicht in öffentliche Repositories.

## Login und Rollen

Alle Nutzer verwenden denselben Loginweg. Eine Person wird im Dropdown ausgewählt und meldet sich mit einer 4 bis 6 stelligen PIN an. Adminrechte entstehen ausschließlich durch Rollen und Berechtigungen. Es gibt keinen separaten Adminlogin.

Startrollen:

- `admin`
- `lagerleitung`
- `bereichsleitung`
- `mitarbeiter`
- `lesen`

Ersten Benutzer per CLI anlegen:

```bash
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```

## PWA und Design

Ab v0.3.0 enthält die App das Ritterlager-Designsystem mit Royalblau, Mint-Aktionen, Montserrat-Überschriften, Inter-UI-Text, Kartenlayout, Desktop-Sidebar, mobiler Bottom-Navigation, Day-Tabs, Status-Chips und PWA-Manifest.

Die PWA ist onlinepflichtig. Der Service Worker speichert nur Shell-Assets und zeigt bei fehlender Verbindung eine Offline-Hinweisseite. Es werden keine Fachseiten und keine personenbezogenen Daten offline gespeichert.

## Lagerjahre und Orden/Zelte

Ab v0.4.0 gibt es Lagerjahre mit Startdatum, Enddatum, Ort und aktivem Status. Alle Tagesansichten beziehen sich später auf das aktive Lagerjahr.

Orden und Zelt sind im Datenmodell dasselbe Objekt. Beispiele sind Johanniter, Falkner, Samariter, Petrusker, Morgensternritter und Malteser. Ein Orden/Zelt kann Leiter, Helfer, Kürzel, Farbmarke und Sortierung haben.

Neue Verwaltungsbereiche:

```text
/admin/lagerjahre
/admin/orden
```

Neue Rechte:

```text
camp_years.view
camp_years.manage
orders.view
orders.manage
```

Die Übersicht zeigt ab v0.4.0 echte Grunddaten aus aktivem Lagerjahr, Personen und Orden/Zelten. Programm, Essen und Dienste bleiben bis zu den nächsten Fachphasen bewusst als Empty-State sichtbar.

## Programm

Ab v0.5.0 gibt es das Programmmodul unter:

```text
/programm
```

Programmpunkte werden je Lagertag als Timeline angezeigt. Bearbeitung ist nur mit `program.manage` möglich. Normale eingeloggte Nutzer mit `program.view` können das Programm lesen.

Gespeichert werden Datum, Startzeit, Endzeit, Titel, Kategorie, Ort, Verantwortliche, Beschreibung und betroffene Orden/Zelte.


## Essen und Speiseplan

Ab v0.6.0 gibt es das Essen-Modul unter:

```text
/essen
```

Der Speiseplan wird je Lagertag in Karten für Frühstück, Mittagessen und Abendessen angezeigt. Bearbeitung ist nur mit `meals.manage` möglich. Nutzer mit `meals.view` können den Speiseplan lesen.

Gespeichert werden Datum, Mahlzeit-Typ, Uhrzeit, Gericht oder Beschreibung, Portionen gesamt, vegetarische Portionen, Allergiehinweise, Küchenteam, Notizen und optional erste Zutaten als Vorbereitung für eine spätere Einkaufsliste.

Der ODS-Import und die automatische Einkaufsliste sind noch nicht umgesetzt.

## Cronjobs und geplante Aufgaben

Seit v0.12.0 gibt es geplante Aufgaben unter:

```text
/system/tasks
```

Startaufgaben:

- `backup_daily`
- `backup_weekly`
- `cleanup_logs`

Jede Aufgabe hat Aktivstatus, empfohlenes Intervall, Lock gegen parallele Läufe, Laufprotokoll und optionalen HTTP-Cron-Token. HTTP-Cron führt nur feste Task-Keys aus. Tokens werden nur gehasht gespeichert und nach Regeneration einmalig als vollständige URL angezeigt.

CLI-Beispiel:

```bash
php scripts/cron/run_task.php backup_daily
```

HTTP-Beispiel nach Token-Regeneration:

```text
/cron/run?task=backup_daily&token=EINMALIG_ANGEZEIGTER_TOKEN
```

## Backups

Seit v0.12.0 gibt es eine Backupfunktion unter:

```text
/system/backups
```

Backups enthalten Datenbank-Dump, Projektdateien und ausgewählte Storage-Bereiche wie Uploads, Importe und Dokumente. Backup-Dateien liegen in `storage/backups` und werden nicht direkt öffentlich ausgeliefert. Download läuft ausschließlich über den Controller mit `backups.download`.

`mysqldump` wird bevorzugt. Wenn `mysqldump` fehlt, nutzt die App einen PHP-Fallback. Falls `ZipArchive` fehlt, versucht die App einen `PharData`-Archiv-Fallback.

Nach dem Update auf v0.12.0 ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach neu anmelden, damit Rechte wie `backups.manage`, `backups.download`, `cron.manage` und `logs.view` in der Session vorhanden sind.

## Security

- `public/` ist der einzige Webroot.
- Geschützte Ordner enthalten `.htaccess`-Sperren.
- Fehler werden geloggt.
- CSRF-Helper ist vorbereitet.
- Login per Dropdown und PIN ist ab v0.2 vorhanden.
- Rollenrechte werden serverseitig geprüft.
- Content-Security-Policy ist ab v0.3.0 aktiv.
- Inline-JavaScript wurde in eigene Asset-Dateien ausgelagert.
- PINs werden ausschließlich als Hash gespeichert.
- Loginereignisse und Rollenänderungen werden im Audit-Log protokolliert.

## Updates

Jede Version aktualisiert:

- `VERSION`
- `CHANGELOG.md`
- passende Datei in `docs/releases/`
- Migrationen, wenn Datenbankänderungen nötig sind

## Troubleshooting

Zentrales App-Log:

```text
storage/logs/app-YYYY-MM-DD.log
```

Wenn eine weiße Seite erscheint, zuerst dieses Log prüfen.



## Personen, Teilnehmerstatus und Geburtstage

Seit v0.8.0 speichert die Personenverwaltung vollständige Teilnehmerdaten mit Geburtsdatum, Adresse, Kontakt, Essenshinweisen, Allergien, medizinischen Hinweisen, Notfallkontakt und internen Bemerkungen.

Personen können je Lagerjahr Teilnehmer, Mitarbeiter oder beides sein. Die Zuordnung zu einem Orden/Zelt erfolgt ebenfalls lagerjahrbezogen. Geburtstage während des aktiven Lagerzeitraums werden automatisch markiert.

Sensible Angaben wie Notfallkontakte, Allergien, medizinische Hinweise und interne Bemerkungen sind in der Detailansicht nur für Rollen mit `persons.sensitive.view` oder `persons.manage` sichtbar. Änderungen an diesen Feldern werden im Audit-Log protokolliert, aber ohne Klartextinhalte.

Nach dem Update auf v0.8 ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach einmal neu anmelden, damit das neue Recht `persons.sensitive.view` in der Session geladen wird.

## Dienste

Seit v0.7 gibt es eine tägliche Dienstliste für Küchendienst, Spüldienst, Platzdienst, Nachtwache und weitere Lageraufgaben. Dienste können Personen, Orden/Zelten oder Freitext-Teams zugewiesen werden. Der Platzdienst nutzt einen Vorschlag anhand der zuletzt gespeicherten Zuweisung und der Sortierung der Orden/Zelte. Bestehende manuelle Zuweisungen werden nicht überschrieben.

Nach dem Update auf v0.7 ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach einmal neu anmelden, damit Rollenrechte neu in die Session geladen werden.

## Ordnungspunkte ab v0.9.0

Normale Mitarbeiter können über `/ordnung` einem Kind Punkte abziehen. Dieser Flow ist fest auf die Kategorie Ordnung begrenzt. Serverseitig wird keine andere Kategorie akzeptiert.

Erlaubte Standardwerte für Mitarbeiter:

- -1
- -2
- -3

Admins und Lagerleitung können über `/admin/ordnungspunkte` Einträge prüfen, stornieren und Korrekturen buchen. Einträge werden nicht hart gelöscht.

Nach dem Update auf v0.9.0 ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach neu anmelden, damit Rechte wie `points.order.create` und `points.manage` in der Session vorhanden sind.

## Auswertung, Rangordnung und Prüfungen ab v0.10.0

Seit v0.10.0 gibt es einen Adminbereich für Auswertungen, Rangstufen, Lerneinheiten und Prüfungsergebnisse.

Bereiche:

- `/admin/auswertung` für den Zwischenstand je Orden/Zelt und Teilnehmer
- `/admin/rangstufen` für Rangstufen
- `/admin/lerneinheiten` für Lerneinheiten
- `/admin/pruefungen` für Prüfungsergebnisse und Rangzuordnung

Die Auswertung ist ein Zwischenstand. Sie ersetzt noch keine fachlich geprüfte Endwertung und übernimmt keine Excel-Formeln unkontrolliert.

Nach dem Update auf v0.10.0 ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach neu anmelden, damit Rechte wie `exams.view` und `exams.manage` in der Session vorhanden sind.



## Importe

Der Adminbereich `Importe` verarbeitet XLSX, ODS und DOCX kontrolliert. Dateien werden in `storage/imports` gespeichert und nicht direkt öffentlich ausgeliefert. Jeder Import erzeugt eine Vorschau, einen Importlauf und ein Fehlerprotokoll. Bestehende Daten werden nicht still überschrieben.

Für die inhaltliche Vorschau von XLSX, ODS und DOCX braucht PHP die Erweiterung `ZipArchive`. Ohne diese Erweiterung wird der Upload sicher gespeichert, aber die Datei kann nicht automatisch gelesen werden.

## Logs und Betrieb

Seit v0.12.0 gibt es eine Logansicht unter:

```text
/system/logs
```

Sie zeigt die letzten Einträge aus `storage/logs`. Die Logdateien bleiben im geschützten Storage. Der Logger filtert Tokens, PINs, Passwörter und Secrets aus dem Kontext.

Restore-Hinweise stehen in:

```text
docs/deployment/RESTORE.md
```


## Plesk / open_basedir

Wenn die Anwendung mit `open_basedir restriction in effect` startet, ist der Webroot zwar korrekt auf `public/` gesetzt, PHP darf aber nicht auf das Projektverzeichnis oberhalb von `public/` zugreifen. In Plesk muss `open_basedir` das Projektverzeichnis enthalten, nicht nur den Document Root. Details stehen in `docs/deployment/PLESK_OPEN_BASEDIR.md`.

## Hinweis zu v0.12.4 Setup-Hotfix

Falls beim Web-Setup die Meldung `There is no active transaction` erscheint, liegt dies an MySQL/MariaDB-DDL mit implizitem Commit. Ab v0.12.4 führt der MigrationRunner MySQL-Migrationen ohne äußere PDO-Transaktion aus. Danach `public/setup.php` erneut aufrufen. Bereits angelegte Tabellen müssen nicht gelöscht werden.


## Hinweis v0.12.4

Hotfix für die Setup-Migration der Importtabellen: `row_number` wurde in `source_row_number` geändert, damit MySQL/MariaDB die Migration ohne Syntaxfehler ausführt.


## v0.13.0 Importvorlagen

Unter `Admin → Importe` stehen zusätzlich mitgelieferte Importvorlagen bereit. Sie nutzen Dateien aus `storage/import_sources/` und schreiben Daten kontrolliert in die bestehenden Module. Vorhandene Datensätze werden nicht blind überschrieben.

Enthalten sind Importvorlagen für Zeltlager Manager 2025, ein Dummy-Lagerjahr 2000, Programm 2026, Speiseplan 2026 sowie Dienste und Aufgabenverteilung 2026.

Neue Lagerjahre bekommen automatisch die sechs festen Orden/Zelte: Johanniter, Falkner, Samariter, Petrusker, Morgensternritter und Malteser.


## Migration per Browser

Für einfache Hosting-Umgebungen gibt es ab v0.13.1 die Datei `public/migration.php`.

Aufruf bei korrekt gesetztem Webroot:

```text
https://deine-domain.de/migration.php
```

Die Datei führt alle noch offenen Migrationen aus. Sie hat bewusst keinen Login und keinen Token. Deshalb nach erfolgreicher Ausführung sofort vom Server löschen.

## WebDAV für Backups

Ab v0.13.2 kann WebDAV unter `System → WebDAV` eingerichtet werden.

WebDAV ist nur eine zusätzliche Ablage. Die lokal erzeugte Backupdatei unter `storage/backups` bleibt die technische Wahrheit. Nach einem erfolgreichen Backup versucht die App, die Datei per WebDAV zu übertragen, wenn WebDAV aktiv und vollständig konfiguriert ist.

Wichtige Felder:

- WebDAV-URL, zum Beispiel `https://cloud.example.de/remote.php/dav/files/benutzer`
- Benutzername
- Passwort oder App-Passwort
- Zielordner, zum Beispiel `ritterlager/backups`
- Timeout

Für Nextcloud sollte ein App-Passwort genutzt werden.

## Browser-Migration und Datenbankzugang

`public/migration.php` zeigt ab v0.13.2 bei Fehlern wie `SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'` ein Formular für die echten Datenbankdaten an. Nach erfolgreichem Verbindungstest wird `config/database.php` geschrieben und die Migration kann erneut laufen.

Nach erfolgreicher Nutzung muss `public/migration.php` vom Server gelöscht werden.


## Version 0.13.4

Die Ordnungspunkte wurden auf ein positives Bewertungsmodell umgestellt. Tägliche Bewertungen erfassen Ordnung persönlich, Sauberkeit Geschirr, Zelt, Disziplin/Pünktlichkeit und Ordnung Prüfung. Zusätzlich gibt es einmalige Lagerwertungen für Platzdienst, drei Prüfungsfächer und Zusatz Küchendienst.

Nach dem Update `public/migration.php` aufrufen oder `php scripts/maintenance/migrate.php` ausführen. Danach einmal neu anmelden.


## Hinweis v0.13.4

`public/migration.php` wurde an `setup.php` angeglichen und lädt den Bootstrap direkt. Dadurch entsteht kein falscher open_basedir-Hinweis mehr, wenn das Setup auf demselben Hosting funktioniert. Nach der Nutzung muss die Datei wieder gelöscht werden.


## Hinweis v0.13.5: migration.php und Projektpfad

`public/migration.php` sucht das Projektverzeichnis jetzt robuster. Wenn `app/Support/bootstrap.php` nicht gefunden wird, zeigt die Datei eine Diagnose mit den geprüften Pfaden.

Die korrekte Serverstruktur ist weiterhin:

```text
httpdocs/
  app/
  config/
  database/
  routes/
  storage/
  public/
    index.php
    migration.php
```

Wenn nach dem Entpacken ein zusätzlicher Ordner wie `httpdocs/ritterlager-manager-v0.13.x/app/` existiert, muss der Inhalt dieses Ordners nach `httpdocs/` verschoben werden.


## Hinweis v0.13.6

Behebt den HY093-Fehler beim Import durch eindeutige PDO-Platzhalter in der Orden/Zelt-Suche.


## Update v0.13.7

Dieses Update behebt die Suche nach gebündelten Import-Quelldateien. Die bevorzugte Ablage bleibt:

```text
storage/import_sources/
  Zeltlager 2025 Manager.xlsx
  Speiseplan_2026.ods
  Ritterlagerprogramm 2026.docx
  Aufgabenverteilung 2026.docx
```

Falls die Dateien versehentlich unter `storage/imports`, im Projektroot oder in `public` liegen, versucht der Import sie jetzt ebenfalls zu finden. Dauerhaft sollten die Dateien aber nicht in `public` liegen.


## Update v0.13.8

Dieses Update korrigiert die gebündelten Importe für Programm, Essen und Dienste. Nach dem Hochladen muss `https://app.ritterlager.com/migration.php` ausgeführt werden. Danach sollten die Importvorlagen für Programm 2026, Speiseplan 2026 und Dienste/Aufgabenverteilung 2026 erneut gestartet werden.

Wichtig: Die Aufgabenverteilung wird jetzt nur noch als Dienstarten-Grundlage importiert. Tagesdienste entstehen aus Platzdienst und Nachtwache im Programm.

## Update v0.13.9

Der Speiseplan ist jetzt strikt auf die Lagertage des jeweiligen Lagerjahres begrenzt.

Nach dem Hochladen bitte ausführen:

```text
https://app.ritterlager.com/migration.php
```

Die Migration deaktiviert vorhandene Mahlzeiten außerhalb des Lagerzeitraums. Neue manuelle Speiseplan-Einträge außerhalb des Start- und Enddatums werden blockiert. Dateiimporte für Speiseplan überspringen Mahlzeiten außerhalb des Lagerzeitraums.

Bei Full-ZIPs weiterhin nicht überschreiben:

```text
config/database.php
storage/uploads/
storage/imports/
storage/documents/
storage/backups/
storage/logs/
```



## Hinweis v0.13.10

Tagesgebundene Module sind auf die Lagertage des aktiven Lagerjahres begrenzt. Betroffen sind Programm, Essen, Dienste und Ordnung. Nach dem Update `public/migration.php` ausführen und danach wieder löschen.
## Hinweis v0.13.11

Diese Version korrigiert die Migration `2026_06_25_000019_limit_all_day_modules_to_camp_days.php`. Wenn zuvor `Invalid migration file` angezeigt wurde, bitte die Datei ersetzen und `public/migration.php` erneut aufrufen.


## v0.14.0 Hinweise

Nach dem Update auf v0.14.0 bitte `public/migration.php` einmal ausführen und danach löschen.

Neue Funktionen:

- Wiederkehrende Programmpunkte markieren.
- Ordensfarben per Colorpicker setzen.
- Punkte erfassen über Spielwertung, Zeltbewertung, Geschirrbewertung und Dienstpunkte.

## v0.14.1: Ränge und Beinamen

Die App führt jetzt die feste Ritterlager-Rangfolge: Knappe, Ritter, Freiherr, Graf, Markgraf, Landgraf, Fürst, Herzog und Großherzog.

Beinamen werden in `persons.nickname` gespeichert und im Personenbereich, in Prüfungsergebnissen, in Auswertungen und im CSV-Export angezeigt.

Der Import `Zeltlager Manager 2025 importieren` übernimmt Beinamen aus der Excel-Datei und normalisiert Rangangaben wie `1 Knappe` auf die festen Rangstufen.

Nach dem Update ist `public/migration.php` auszuführen und anschließend wieder zu löschen.



## v0.14.3

Freie Ordensfarben aus dem Colorpicker werden jetzt konsequent in Karten, Badges, Teilnehmerlisten, Punkteansicht und Auswertung angezeigt.

## Update v0.14.6

Dieses Update korrigiert die fehlgeschlagene Migration `2026_06_25_000023_person_search_and_rank_backfill.php`. Die Tabelle `app_versions` besitzt die Spalte `notes`, nicht `description`. Nach dem Hochladen erneut `public/migration.php` ausführen.

## Update v0.14.7

Die Ranghistorie wurde korrigiert. Beim Import aus dem Zeltlager Manager wird pro Person der letzte bekannte Rang bis zum Quelljahr genutzt. Ein bereits erreichter Rang kann nicht durch spätere leere oder niedrigere Einträge verloren gehen.

Nach dem Update: Migration ausführen und den Import „Zeltlager Manager 2025 importieren“ erneut starten.

### Update v0.14.8

Behebt doppelte Personen nach wiederholtem Import. Nach dem Hochladen `public/migration.php` ausführen. Danach den Zeltlager-Manager-Import erneut starten.


## v0.14.11

Behebt falsche Geburtstage aus dem Import und schützt erreichte Ränge gegen Rückstufung. Nach dem Update Migration ausführen und den Zeltlager-Manager-Import erneut starten.


## Hinweis v0.14.11

Der Import vermeidet DATE-Vergleiche mit leeren Strings. Dadurch wird der Fehler `SQLSTATE[HY000]: General error: 1525 Incorrect DATE value: ''` beim erneuten Import behoben.

## v0.14.12

Küchendienst (3x täglich, an Frühstück/Mittagessen/Abendessen orientiert) und Platzdienst (1x täglich) werden jetzt automatisch für jeden Lagertag angelegt, sobald ein Lagerjahr angelegt oder aktiviert wird. Kein manuelles Anlegen mehr nötig.

Außerdem behoben: Tages-Reiter auf der Übersicht und auf allen Ordnungspunkte-Seiten wechselten das Datum nicht zuverlässig; automatisch abschickende Auswahlfelder (Orden/Zelt-Filter) reagierten wegen der aktiven Content-Security-Policy nicht; Kartenreihen in mehrspaltigen Grids waren uneinheitlich ausgerichtet.

Nach dem Update Migration ausführen (`public/migration.php` oder `php scripts/maintenance/migrate.php`), damit Küchendienst/Platzdienst auch für bereits bestehende Lagerjahre rückwirkend angelegt werden.

## v0.14.13

Die Auswertung zeigt jetzt eine echte Gesamtpunktzahl je Teilnehmer: persönliche Punkte, die vollen Orden/Zelt-Punkte des zugeordneten Ordens und Prüfungspunkte aus den Lerneinheiten-Ergebnissen wurden bislang getrennt geführt und teils gar nicht angezeigt. Rangaufstiegs-Schwellen prüfen jetzt gegen diese Gesamtsumme. Die Zwischenstand-Tabelle zeigt eine Aufschlüsselung, der CSV-Export eigene Spalten je Anteil.

Keine Migration nötig.
