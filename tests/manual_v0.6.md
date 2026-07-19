# Manuelle Tests v0.6

## Vorbereitung

1. Updatepaket einspielen.
2. Migrationen ausführen:

```bash
php scripts/maintenance/migrate.php
```

3. Neu anmelden, damit neue Rechte in der Session stehen.
4. Ein aktives Lagerjahr muss vorhanden sein.

## Testfälle

### Speiseplan anzeigen

- `/essen` öffnen.
- Erwartung: Tagesansicht mit Day-Tabs und Karten für Frühstück, Mittagessen und Abendessen.

### Mahlzeit anlegen

- Mit Rolle `admin`, `lagerleitung` oder `bereichsleitung` `/essen/neu` öffnen.
- Frühstück oder andere Mahlzeit auswählen.
- Gericht, Uhrzeit, Portionen, vegetarische Portionen, Allergiehinweis und Küchenteam erfassen.
- Speichern.
- Erwartung: Rückkehr zur Tagesansicht, Mahlzeit sichtbar.

### Mahlzeit bearbeiten

- Bestehende Mahlzeit bearbeiten.
- Titel oder Portionen ändern.
- Speichern.
- Erwartung: Änderung sichtbar.

### Mahlzeit deaktivieren

- Mahlzeit über „Entfernen“ deaktivieren.
- Erwartung: Karte ist wieder offen oder leer.

### Übersicht

- `/` öffnen.
- Erwartung: Bereich „Essen heute“ zeigt eingetragene Mahlzeiten oder offene Einträge.

### Rechte

- Mit Rolle `mitarbeiter` anmelden.
- `/essen` öffnen.
- Erwartung: Speiseplan sichtbar, Bearbeiten- und Entfernen-Aktionen nicht sichtbar.
- Direkter POST auf `/essen/speichern` ohne Recht muss blockiert werden.

### CSRF

- Schreibaktion ohne gültigen CSRF-Token senden.
- Erwartung: 403.

### XSS

- In Gericht, Notizen und Allergiehinweisen HTML oder JavaScript eintragen.
- Erwartung: Ausgabe wird escaped, kein Script wird ausgeführt.

## Bekannte Einschränkungen

- ODS-Import ist noch nicht vorhanden.
- Einkaufsliste ist nur vorbereitet.
- Allergiehinweise sind allgemeine Küchenhinweise und noch nicht personenbezogen berechtigt.
