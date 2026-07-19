# Web-Setup v0.12.1

Diese Datei beschreibt das einmalige Web-Setup über `public/setup.php`.

## Zweck

Das Setup ist für Hosting-Umgebungen gedacht, in denen CLI-Zugriff auf `php scripts/maintenance/migrate.php` nicht komfortabel möglich ist. Es erstellt die Datenbankstruktur bis v0.12, schreibt `config/database.php` und kann den ersten Adminbenutzer anlegen.

## Ablauf

1. Projektdateien hochladen.
2. Webroot auf `public/` setzen.
3. Im Browser `/setup.php` öffnen.
4. Datenbankdaten eintragen.
5. Optional „Datenbank erstellen“ aktivieren, wenn der Datenbankbenutzer entsprechende Rechte hat.
6. Ersten Admin mit 4 bis 6 stelliger PIN anlegen.
7. Setup ausführen.
8. Danach sofort `public/setup.php` vom Server löschen.

## Sicherheit

Das Setup ist öffentlich erreichbar, solange die Datei existiert. Nach erfolgreichem Lauf wird `storage/setup.lock` erstellt. Trotzdem muss `public/setup.php` gelöscht werden.

Die Admin-PIN wird mit `password_hash()` gespeichert. Datenbankpasswörter werden nicht geloggt, aber in `config/database.php` gespeichert, damit die App die Verbindung aufbauen kann.

## Alternative

Statt Web-Setup können die Migrationen weiter per CLI ausgeführt werden:

```bash
php scripts/maintenance/migrate.php
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```

## Einschränkungen

Das Setup ersetzt kein Restore-System und keine produktive Updateplanung. Es ist nur für die Erstinstallation oder kontrollierte Testinstallation gedacht.
