# Ritterlager Manager per Git deployen

Dieses Repository enthält den Code der App, aber keine produktiven Zugangsdaten und keine personenbezogenen Import-/Uploaddaten.

## Nicht im Repository enthalten

- `config/database.php`
- `public/setup.php`
- `public/migration.php`
- `storage/logs/`
- `storage/backups/`
- `storage/uploads/`
- `storage/imports/`
- `storage/documents/`
- `storage/import_sources/`
- `storage/setup.lock`

## Erstes Pushen zu GitHub

```bash
git init
git add .
git commit -m "Initial Ritterlager Manager v0.14.11"
git branch -M main
git remote add origin https://github.com/matzeisda/rttr.git
git push -u origin main
```

Falls GitHub meldet, dass der Remote-Branch schon existiert:

```bash
git pull origin main --allow-unrelated-histories
git push -u origin main
```

## Plesk-Struktur

Der Code gehört nach:

```text
/var/www/vhosts/hosting203599.a2e6d.netcup.net/app.ritterlager.com/httpdocs
```

Der Document Root der Domain bleibt:

```text
httpdocs/public
```

Nicht den Document Root auf `httpdocs` setzen.

## Produktive Dateien auf dem Server behalten

Beim Deploy dürfen diese Dateien/Ordner auf dem Server nicht gelöscht oder überschrieben werden:

```text
config/database.php
storage/
```

## Migrationen

Nach einem Deploy temporär `public/migration.php` hochladen oder aus dem Serverbestand nutzen, Migration ausführen und danach wieder löschen:

```text
https://app.ritterlager.com/migration.php
```

Danach `public/migration.php` wieder entfernen.
