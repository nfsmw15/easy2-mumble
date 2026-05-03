# Changelog — Easy2-Mumble

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

## [v0.2.0] — 2026-05-03

### Hinzugefügt
- **SuperUser-Passwort** in Server-Detailansicht anzeigen (Eye-Toggle, Copy-Button)
- **SuperUser-Passwort zurücksetzen** — Button mit optionalem eigenem Passwort oder Zufallsgenerierung
- **Server-Einstellungen bearbeiten** — Name, Passwort, Max-Users, Begrüßungstext nachträglich ändern
- **Server-Config (INI) bearbeiten** — Textarea-Editor mit Dark-Theme, Tab-Support und Sicherheits-Hinweisen
- DB-Migration: Spalte `superuser_password` in `mumble_server_config`
- DB-Migration: Neue Site `mumble_config` (ID 206)
- Neue PHP-Methoden: `getSuperUserPassword()`, `resetSuperUserPassword()`, `getServerConfig()`, `saveServerConfig()`, `updateServerSettings()`
- Neue Agent-Client-Methoden: `getSuperUser()`, `resetSuperUser()`, `getConfig()`, `saveConfig()`
- Timeout-Override für `updateConfig` (120s) und `createServer` (120s)
- MySQL-gone-away-Fix: `log()`-Methode mit try-catch abgesichert
- Container-ID-Update nach Container-Recreate mit MySQL-Reconnect-Fallback

### Geändert
- Container-Einstellungen werden jetzt über Container-Recreate (neue ENV-Variablen) statt INI-Patch + Restart aktualisiert (Mumble-Docker-Image generiert INI bei jedem Start neu)

### Behoben
- Doppelter `_ml_`-Prefix in SQL-Queries (alle 27 Stellen korrigiert)
- `canCreate()` blockierte Webmaster durch Quota-Check (Admin-Bypass hinzugefügt)
- Doppelte Erfolgsmeldung in Templates (Easy2 verpackt `$success` automatisch in `$error`)
- HTTP 404 beim Server-Start durch zu kurzen cURL-Timeout (8s → 30s/120s)
- MySQL-gone-away nach langem Agent-Call (Container-Restart > 10s)

## [v0.1.0] — 2026-05-01

### Hinzugefügt
- Initiale Erweiterung für Easy2-PHP8 (main-dashboard Branch)
- Server-Übersicht mit Status-Badges und Online-Anzeige
- Server erstellen mit Name, Port, Passwort, Max-Users, Begrüßungstext
- Server starten, stoppen, neustarten, löschen
- Container-Logs anzeigen
- Host-Verwaltung (Mumble-Hosts mit Agent-URL und Token)
- Quota-System pro Rang (max. Server pro User)
- Dashboard-Widget mit SB-Admin Icon-Cards
- AGPLv3-Lizenz
