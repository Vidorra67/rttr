# Manuelle Tests v0.7

## Vorbereitung

1. Update einspielen.
2. Migration ausführen: `php scripts/maintenance/migrate.php`.
3. Neu anmelden.
4. Aktives Lagerjahr und Orden/Zelte müssen vorhanden sein.

## Tests

- `/dienste` ist ohne Login gesperrt.
- `/dienste` lädt mit einem Nutzer mit `duties.view`.
- Nutzer ohne `duties.manage` sieht keinen Button zum Anlegen oder Bearbeiten.
- Admin/Lagerleitung kann einen Dienst anlegen.
- Person kann einem Dienst zugewiesen werden.
- Orden/Zelt kann einem Dienst zugewiesen werden.
- Freitext-Team kann einem Dienst zugewiesen werden.
- Statuswechsel auf erledigt funktioniert.
- Zugewiesener Mitarbeiter kann Dienst auf erledigt setzen.
- Nutzer ohne Zuweisung und ohne `duties.manage` kann Status nicht ändern.
- Platzdienst-Vorschlag zeigt nach vorhandener Platzdienst-Zuweisung den nächsten Orden/Zelt anhand Sortierung.
- CSRF ohne gültiges Token wird blockiert.
- Übersicht zeigt offene Dienste des aktuellen Lagertags.
- Mobile Ansicht zeigt Dienstkarten ohne horizontale Tabellenpflicht.

## Negativtests

- Direkter POST auf `/dienste` ohne Recht wird blockiert.
- Manipulierte fremde Orden/Zelt-IDs aus anderem Lagerjahr werden nicht übernommen.
- XSS-Test in Titel und Beschreibung wird escaped ausgegeben.
