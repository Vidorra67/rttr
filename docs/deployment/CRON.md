# Cronjobs

## Geplante Aufgaben

Seit v0.12.0 gibt es feste geplante Aufgaben:

- `backup_daily`
- `backup_weekly`
- `cleanup_logs`

Die Aufgaben werden in `scheduled_tasks` verwaltet und unter `/system/tasks` angezeigt.

## CLI-Cron

Bevorzugt auf Servern mit CLI-Zugriff:

```bash
php /pfad/zum/projekt/scripts/cron/run_task.php backup_daily
php /pfad/zum/projekt/scripts/cron/run_task.php backup_weekly
php /pfad/zum/projekt/scripts/cron/run_task.php cleanup_logs
```

## HTTP-Cron

Für Plesk oder Shared Hosting:

1. Im Admin unter `/system/tasks` Token regenerieren.
2. Die einmalig angezeigte URL kopieren.
3. Diese URL als geplanten Abruf eintragen.

Beispiel:

```text
https://example.org/cron/run?task=backup_daily&token=...
```

## Sicherheit

- Es werden nur feste Task-Keys ausgeführt.
- Es werden keine beliebigen PHP-Dateien per URL gestartet.
- Token werden nur gehasht gespeichert.
- Jeder Lauf wird in `scheduled_task_runs` protokolliert.
- Parallele Läufe werden über `locked_until` verhindert.

## Prüfung

Nach Einrichtung prüfen:

- Task läuft mit Status `ok`.
- Lauf steht im Protokoll.
- Bei parallelem Start erscheint `skipped`.
- Fehler stehen in `scheduled_task_runs.error_text` und im App-Log.
