# Manuelle Tests v0.10.0

## Vorbereitung

```bash
php scripts/maintenance/migrate.php
```

Danach neu anmelden.

## Tests

1. Als Admin `/admin/auswertung` öffnen.
2. Rangstufe unter `/admin/rangstufen/neu` anlegen.
3. Rangstufe bearbeiten.
4. Lerneinheit unter `/admin/lerneinheiten/neu` anlegen.
5. Lerneinheit bearbeiten.
6. Lerneinheit deaktivieren.
7. Prüfungsergebnis unter `/admin/pruefungen` erfassen.
8. Prüfungsergebnis für dieselbe Person und Lerneinheit erneut speichern und Aktualisierung prüfen.
9. Rang zuweisen.
10. Auswertung je Orden/Zelt filtern.
11. CSV-Export über `/admin/auswertung/export` prüfen.
12. Nutzer ohne `exams.manage` darf nicht schreiben.
13. Ungültiger CSRF-Token wird mit 403 blockiert.
14. XSS-Test in Lerneinheitentitel und Notiz durchführen.
15. Prüfen, dass medizinische Hinweise und interne Notizen in der Auswertung nicht erscheinen.
