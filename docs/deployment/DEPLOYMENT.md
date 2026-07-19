# Deployment

## Umgebung

- PHP 8.3
- MySQL oder MariaDB
- PDO MySQL Extension
- Apache mit `.htaccess` oder passende Nginx-Regeln

## Programmmodul

Das Programmmodul ist ab v0.5.0 unter `/programm` erreichbar. Bearbeitung benötigt `program.manage`, Anzeige benötigt `program.view`. Nach dem Update muss die Migration `2026_06_25_000006_create_program_items` ausgeführt werden.

## PWA

Die PWA-Dateien liegen unter `public/`:

```text
manifest.webmanifest
sw.js
offline.html
assets/icons/
```

Der Service Worker cached nur Shell-Assets und keine personenbezogenen Fachseiten. Die App bleibt onlinepflichtig.

## Webroot

Der Webroot muss auf `public/` zeigen.

Direkte Aufrufe auf diese Ordner dürfen nicht möglich sein:

```text
/config/
/database/
/docs/
/scripts/
/storage/
```

## Schreibrechte

Diese Ordner müssen für PHP schreibbar sein:

```text
storage/logs
storage/temp
storage/uploads
storage/imports
storage/backups
storage/documents
```

## Installation

1. Dateien hochladen.
2. Webroot auf `public/` setzen.
3. Datenbank anlegen.
4. Konfiguration in `config/database.php` setzen.
5. Migration ausführen:

```bash
php scripts/maintenance/migrate.php
```

6. Startseite öffnen.
7. Systemstatus prüfen unter `/system/status`.

## Updates

1. Backup erstellen.
2. Wartungsmodus aktivieren, falls vorhanden.
3. Dateien übertragen, aber produktive Konfiguration und Storage nicht überschreiben.
4. Migrationen ausführen.
5. Login und Kernfunktionen testen.
6. Wartungsmodus deaktivieren.

## Nicht überschreiben

```text
config/app.php
config/database.php
storage/
```

## Fehleranalyse

App-Logs liegen in:

```text
storage/logs/app-YYYY-MM-DD.log
```

## Login ab v0.2.0

Nach dem Ausführen der Migrationen muss mindestens ein Adminbenutzer angelegt werden:

```bash
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```

Der Login erfolgt über `/login` per Personenauswahl und 4 bis 6 stelliger PIN. Die initiale PIN muss nach der ersten Anmeldung geändert werden.

## Rollen ab v0.2.0

Adminrechte sind Rollenrechte. Es gibt keinen separaten Adminlogin. Der Zugriff auf Adminseiten wird serverseitig geprüft. Menüpunkte sind nur Bedienkomfort und ersetzen keine Rechteprüfung.


## Hinweise ab v0.4.0

Nach dem Update auf v0.4.0 müssen Migrationen ausgeführt werden. Danach sollten Adminnutzer sich neu anmelden, damit die neuen Rechte für Lagerjahre und Orden/Zelte in der Session geladen werden.

## Hinweise ab v0.7.0

Nach dem Update auf v0.7.0 müssen Migrationen ausgeführt werden. Danach sollten Nutzer sich neu anmelden, damit die Rechte `meals.view` und `meals.manage` in der Session geladen werden.

Das Essen-Modul ist unter `/essen` erreichbar. Der Bereich nutzt das aktive Lagerjahr und die Day-Tabs.


## Dienstmodul v0.7

Nach dem Einspielen von v0.7 muss die Migration ausgeführt und anschließend eine neue Anmeldung durchgeführt werden. Die Dienstliste nutzt `duties.view` und `duties.manage`.

## Update auf v0.8.0

Nach dem Einspielen der Dateien müssen die Migrationen ausgeführt werden:

```bash
php scripts/maintenance/migrate.php
```

Danach sollten angemeldete Benutzer sich einmal ab- und wieder anmelden, damit das neue Recht `persons.sensitive.view` in der Session vorhanden ist.

Die Migration erweitert die Tabelle `persons` und legt `camp_person_statuses` sowie `person_guardians` an. Vor dem Update ist ein Backup empfohlen, weil personenbezogene Daten erweitert werden.


## v0.9.0 Ordnungspunkte

Die Route `/ordnung` ist durch `points.order.create` geschützt. Die Adminliste `/admin/ordnungspunkte` und Korrekturen/Storno sind durch `points.manage` geschützt. Nach dem Update muss die Migration `2026_06_25_000010_create_point_entries.php` ausgeführt werden. Einträge werden nicht hart gelöscht, sondern über `voided_at`, `voided_by` und `void_reason` storniert.

## Update auf v0.11.0

Nach dem Dateiupdate ausführen:

```bash
php scripts/maintenance/migrate.php
```

Danach neu anmelden, damit `exams.view` und `exams.manage` in der Session geladen werden.

Neue Bereiche:

- `/admin/auswertung`
- `/admin/rangstufen`
- `/admin/lerneinheiten`
- `/admin/pruefungen`

Die Auswertung enthält keine sensiblen Teilnehmernotizen und dient nur als Zwischenstand.


## Importe

Für Importvorschau von XLSX, ODS und DOCX sollte die PHP-Erweiterung ZipArchive aktiv sein. Der Ordner `storage/imports` braucht Schreibrechte und darf nicht öffentlich erreichbar sein.

## Betrieb ab v0.12.0

Backups liegen in `storage/backups` und dürfen nicht direkt öffentlich erreichbar sein. Download erfolgt über `/system/backups/download` mit Rechteprüfung.

Geplante Aufgaben werden unter `/system/tasks` verwaltet. CLI-Cron ist bevorzugt. HTTP-Cron ist für Shared Hosting vorgesehen und nutzt gehashte Token.

Nach Deployment prüfen:

```bash
php scripts/maintenance/migrate.php
php scripts/cron/run_task.php cleanup_logs
```

Danach im Browser prüfen:

- `/system/backups`
- `/system/tasks`
- `/system/logs`
- direkter Zugriff auf `/storage/backups/` muss blockiert sein
