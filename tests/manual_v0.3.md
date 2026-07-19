# Manuelle Tests v0.3.0

## Design und Layout

- [ ] Loginseite auf Desktop prüfen.
- [ ] Loginseite auf Mobilbreite prüfen.
- [ ] PIN-Touchpad bedienen.
- [ ] Übersicht auf Desktop prüfen.
- [ ] Übersicht auf Mobilbreite prüfen.
- [ ] Sidebar erscheint ab Desktopbreite.
- [ ] Bottom-Navigation erscheint mobil.
- [ ] Aktiver Navigationspunkt ist erkennbar.
- [ ] Noch nicht umgesetzte Bereiche sind deaktiviert.
- [ ] Karten, Hero-Box, Chips und Day-Tabs nutzen die vorgegebenen Farben.

## PWA

- [ ] `/manifest.webmanifest` lädt mit HTTP 200.
- [ ] `/sw.js` lädt mit HTTP 200.
- [ ] `/offline.html` lädt mit HTTP 200.
- [ ] Service Worker registriert sich im Browser.
- [ ] App kann auf unterstützten Geräten installiert werden.
- [ ] Bei fehlender Verbindung erscheint der Offline-Hinweis.

## Sicherheit

- [ ] Login funktioniert weiterhin mit gültiger PIN.
- [ ] Login mit falscher PIN bleibt neutral.
- [ ] Logout funktioniert.
- [ ] Zugriff ohne Login leitet auf `/login`.
- [ ] Direkte Admin-Routen bleiben serverseitig geschützt.
- [ ] CSP blockiert kein notwendiges eigenes JavaScript.
- [ ] Inline-JavaScript ist nicht mehr in der Loginseite enthalten.
- [ ] Tastaturfokus ist sichtbar.

## Regression

- [ ] Personenliste lädt für berechtigte Rollen.
- [ ] Person anlegen funktioniert weiterhin.
- [ ] PIN ändern funktioniert weiterhin.
- [ ] Systemstatus lädt für berechtigte Rollen.
- [ ] `/health` liefert Status und Version 0.3.0.
