# Plesk / Netcup: open_basedir richtig setzen

## Problem

Der Ritterlager Manager nutzt `public/` als einzigen Webroot. Die eigentliche Anwendung liegt bewusst außerhalb von `public/`, zum Beispiel in:

```text
app/
config/
database/
routes/
storage/
```

Wenn Plesk `open_basedir` nur auf `httpdocs/public/` begrenzt, kann PHP diese Dateien nicht laden. Dann erscheinen Fehler wie:

```text
open_basedir restriction in effect
Failed opening required .../app/Support/bootstrap.php
```

## Richtige Einstellung

Der Document Root bleibt:

```text
.../httpdocs/public
```

`open_basedir` muss aber mindestens das Projektverzeichnis enthalten:

```text
.../httpdocs/:/tmp/:/var/lib/php/sessions:.../tmp
```

In Plesk kann dafür oft diese Variante genutzt werden:

```text
{WEBSPACEROOT}{/}{:}{TMP}{/}
```

statt:

```text
{DOCROOT}{/}{:}{TMP}{/}
```

## Konkreter Pfad aus dem gemeldeten Fehler

Bei der gemeldeten Umgebung muss `open_basedir` mindestens diesen Pfad enthalten:

```text
/var/www/vhosts/hosting203599.a2e6d.netcup.net/app.ritterlager.com/httpdocs/
```

Nicht ausreichend ist nur:

```text
/var/www/vhosts/hosting203599.a2e6d.netcup.net/app.ritterlager.com/httpdocs/public/
```

## Danach prüfen

1. PHP-FPM oder FCGI-Prozess kurz neu starten, falls Plesk das anbietet.
2. `https://deine-domain/setup.php` erneut öffnen.
3. Nach erfolgreichem Setup `public/setup.php` löschen.
4. App öffnen und Login testen.

## Sicherheit

Das ist keine Lockerung auf den gesamten Server. Erlaubt wird nur das Projektverzeichnis. Direkter Webzugriff bleibt weiterhin durch den Webroot `public/` und die `.htaccess`-Dateien geschützt.
