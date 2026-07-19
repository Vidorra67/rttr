# Manuelle Tests v0.5.0

## Vorbereitung

1. Update einspielen.
2. Migration ausführen: `php scripts/maintenance/migrate.php`.
3. Neu anmelden, damit Rechte in der Session aktuell sind.
4. Aktives Lagerjahr anlegen, falls noch keines existiert.

## Programm

- `/programm` ohne Login aufrufen. Erwartung: Weiterleitung zu `/login`.
- `/programm` mit Benutzer mit `program.view` aufrufen. Erwartung: Programmansicht lädt.
- Tag über Day-Tabs wechseln. Erwartung: URL enthält `?tag=YYYY-MM-DD`, aktive Pille wechselt.
- Programmpunkt anlegen. Erwartung: Eintrag erscheint in der Timeline.
- Programmpunkt mit Kategorie Mahlzeit anlegen. Erwartung: Timeline-Punkt ist mint markiert.
- Programmpunkt bearbeiten. Erwartung: Änderung wird gespeichert und angezeigt.
- Orden/Zelte auswählen. Erwartung: Kürzel erscheinen am Programmpunkt.
- Programmpunkt entfernen. Erwartung: Eintrag ist in der Tagesansicht nicht mehr sichtbar.

## Rechte und Sicherheit

- Benutzer ohne `program.manage` darf keinen Bearbeiten-Button sehen.
- Direkter Aufruf von `/programm/neu` ohne `program.manage` liefert 403.
- POST ohne gültigen CSRF-Token liefert 403.
- XSS-Test im Titel und in der Beschreibung eingeben. Erwartung: Ausgabe wird escaped.
- Audit-Log auf Anlage, Änderung und Deaktivierung prüfen.

## Übersicht

- Wenn ein Programmpunkt für den aktuellen Lagertag existiert, zeigt die Übersicht den nächsten Programmpunkt.
- Ohne Programmpunkt zeigt die Übersicht einen sauberen Empty-State.
