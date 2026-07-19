# Manuelle Tests v0.4.0

## Voraussetzungen

- Migrationen sind ausgeführt.
- Mindestens ein Adminbenutzer ist vorhanden.
- Anmeldung über `/login` funktioniert.

## Tests

1. Als Admin anmelden.
2. `/admin/lagerjahre` öffnen.
3. Neues Lagerjahr mit Start- und Enddatum anlegen.
4. Lagerjahr aktiv setzen.
5. Prüfen, ob die Übersicht den Tageschip und die Day-Tabs zeigt.
6. `/admin/orden` öffnen.
7. Neues Orden/Zelt anlegen.
8. Leiter und Helfer aus der Personenliste zuweisen.
9. Orden/Zelt speichern und wieder bearbeiten.
10. Übersicht öffnen und prüfen, ob die Anzahl Orden/Zelte steigt.
11. Mit Nutzer ohne `camp_years.manage` prüfen, ob Lagerjahränderungen blockiert werden.
12. Mit Nutzer ohne `orders.manage` prüfen, ob Orden/Zelt-Schreibaktionen blockiert werden.
13. POST ohne gültigen CSRF-Token testen und 403 erwarten.
14. Audit-Log in der Datenbank auf Einträge für Lagerjahr und Orden/Zelt prüfen.

## Erwartung

Alle geschützten Schreibaktionen sind serverseitig geschützt. Die Übersicht lädt auch dann sauber, wenn Programm, Essen und Dienste noch keine Fachmodule besitzen.
