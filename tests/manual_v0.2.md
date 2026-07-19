# Manuelle Tests v0.2.0

## Voraussetzung

Migrationen ausführen:

```bash
php scripts/maintenance/migrate.php
```

Ersten Admin anlegen:

```bash
php scripts/maintenance/create_user.php --first=Max --last=Mustermann --pin=123456 --role=admin
```

## Tests

1. `/login` aufrufen.
2. Admin aus Dropdown wählen.
3. Falsche PIN eingeben. Erwartung: neutrale Fehlermeldung.
4. Mehrfach falsche PIN eingeben. Erwartung: Sperrlogik greift nach konfigurierter Anzahl.
5. Korrekte PIN eingeben. Erwartung: Login erfolgreich, Weiterleitung auf `/`.
6. Prüfen, ob nach Login die Session-ID rotiert wurde.
7. Menüpunkt Personen öffnen.
8. Neue Person ohne Login anlegen.
9. Neue Person mit Login und Rolle `mitarbeiter` anlegen.
10. Als Mitarbeiter anmelden. Erwartung: keine Admin-Funktionen sichtbar.
11. Direkten Aufruf `/admin/personen/neu` als Mitarbeiter testen. Erwartung: 403.
12. PIN einer Person ändern.
13. Logout testen.
14. Audit-Log in der Datenbank prüfen.
15. Prüfen, dass keine PIN im technischen Log steht.
