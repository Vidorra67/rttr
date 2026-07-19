# Datenbank

Migrationen liegen in `database/migrations` und werden über folgendes Script ausgeführt:

```bash
php scripts/maintenance/migrate.php
```

v0.1.0 enthält technische Basistabellen:

- `schema_migrations`
- `app_versions`
- `settings`
- `audit_log`

## v0.2.0 Personen, Rollen und PIN-Login

Neue Migration:

```text
database/migrations/2026_06_25_000004_create_persons_users_roles.php
```

Neue Tabellen:

- `persons`
- `users`
- `roles`
- `role_permissions`
- `person_roles`
- `login_attempts`

Nach den Migrationen kann der erste Admin per CLI angelegt werden:

```bash
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```


## v0.4.0

Die Migration `2026_06_25_000005_create_camp_years_orders.php` ergänzt `camp_years`, `orders` und `order_staff_assignments`. Orden und Zelt sind fachlich dasselbe Objekt.


## v0.5.0

Neue Migration `2026_06_25_000006_create_program_items.php` mit `program_items` und `program_item_orders`.

## v0.6.0

Neue Migration `2026_06_25_000007_create_meal_items.php` mit `meal_items` und `meal_ingredients`.

`meal_items` speichert Frühstück, Mittagessen und Abendessen je Lagertag. `meal_ingredients` bereitet eine spätere Einkaufsliste vor, ohne diese schon vollständig zu automatisieren.

- `2026_06_25_000008_create_duties.php`: Tabellen für Dienstarten, Tagesdienste, Zuweisungen und vorbereitete Rotationsregeln.

## v0.8.0 Personenstatus und sensible Teilnehmerdaten

Migration:

```text
2026_06_25_000009_extend_persons_camp_statuses.php
```

Erweitert:

```text
persons
```

Neue Tabellen:

```text
camp_person_statuses
person_guardians
```

`camp_person_statuses` speichert den lagerjahrbezogenen Status einer Person als Teilnehmer, Mitarbeiter oder beides inklusive Orden/Zelt und Rang. `person_guardians` speichert zusätzliche Kontaktpersonen.

## v0.9.0

Neue Migration:

- `2026_06_25_000010_create_point_entries.php`

Neue Tabellen:

- `point_categories`
- `point_entries`

Die Kategorie `ordnung` wird als staff-selectable angelegt. Weitere Kategorien sind vorbereitet, aber im Mitarbeiterflow nicht auswählbar.

## v0.11.0 Auswertung

Migration:

```text
2026_06_25_000011_create_rank_learning_exam_results.php
```

Neue Tabellen:

- `rank_levels`
- `learning_units`
- `exam_results`

Erweiterung:

- `camp_person_statuses.rank_level_id`, falls noch nicht vorhanden

Neue Rechte:

- `exams.view`
- `exams.manage`

Die Auswertung ist bewusst als Zwischenstand umgesetzt. Eine fachlich geprüfte Endwertung wird dadurch nicht ersetzt.


## v0.11 Importtabellen

- `uploaded_files` speichert Metadaten zu Importdateien.
- `import_runs` speichert Upload, Vorschau und Ausführung.
- `import_run_errors` speichert Fehler und übersprungene Zeilen.

## v0.12.0 Betrieb

Neue Migration:

```text
2026_06_25_000013_create_operations.php
```

Neue Tabellen:

- `backup_runs`
- `scheduled_tasks`
- `scheduled_task_runs`
- `webdav_sync_runs`

Neue Rechte:

- `backups.manage`
- `backups.download`
- `cron.manage`
- `logs.view`

Nach dem Update ausführen:

```bash
php scripts/maintenance/migrate.php
```
