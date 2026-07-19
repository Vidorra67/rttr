# Manuelle Tests v0.1.0

- Startseite über `/` öffnen.
- Systemstatus über `/system/status` öffnen.
- Health-Endpoint über `/health` prüfen.
- Unbekannte Route öffnen und 404 prüfen.
- Schreibrechte für `storage/logs` prüfen.
- Fehler provozieren und Logeintrag prüfen.
- Direkten Zugriff auf `/storage/logs/app.log` prüfen. Erwartung: nicht erreichbar.
- Direkten Zugriff auf `/config/database.php` prüfen. Erwartung: nicht erreichbar.
- Migration ausführen: `php scripts/maintenance/migrate.php`.
- Migration ein zweites Mal ausführen. Erwartung: keine offenen Migrationen.
