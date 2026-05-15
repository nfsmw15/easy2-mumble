# Changelog — Easy2-Mumble

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

## [v0.4.0] — 2026-05-15

### Hinzugefügt
- **Konfigurationsformular** — 4-Tab-Formular (Basis, Registrierung, Auto-Ban, Erweitert) ersetzt den raw INI-Editor
  - Basis: Bandbreite, Timeout, Opus-Schwellwert, Textnachrichten-Limits, HTML, Channel-Einstellungen
  - Registrierung: öffentliche Mumble-Serverliste komplett konfigurierbar
  - Auto-Ban: Brute-Force-Schutz (Versuche, Zeitfenster, Bann-Dauer)
  - Erweitert: suggestVersion/Positional/PushToTalk, Bonjour, Serverversion senden
  - Standard-Channel als Dropdown mit Channel-Namen (aus Channel-Viewer)
- **TLS-Zertifikat-Upload** — PEM-Zertifikat und Schlüssel per Web-UI hochladen oder entfernen
- **Server-Mitglieder** — Benutzer einem Server zuweisen mit eingeschränkten Rechten (kein Löschen, kein max_users-Ändern)
  - Live-Autocomplete-Suche beim Hinzufügen
  - Mitglieder sehen zugewiesene Server in der Übersicht
- **Channel-Viewer Verbesserungen**
  - Korrekte User-Platzierung via Docker-Log-Parsing (Move-Events, Temp-Channel-Erkennung)
  - Temporäre Channels (nicht in SQLite) aus `Added channel`-Logs
  - Beim Erstellen eines Temp-Channels implizit eintreten (kein separater Move geloggt)
- **Widget**: X-Frame-Options entfernt für externe iFrame-Einbettung
- `system/js/mumble-edit.js` — alle Interaktionen als externe JS-Datei (CSP-konform)
- DB-Migration `migrate_v0.4.0.sql`: Tabelle `mumble_server_members`, Spalten `ssl_cert_path`/`ssl_key_path`

### Geändert
- Online-Zählung in Server-Übersicht via Log-Parsing statt TCP-Verbindungszählung (externe Scanner werden ignoriert)
- `mumble_edit.php`: Zugriffsprüfung auf `canManageServer()` — Mitglieder können Details öffnen

### Behoben
- `getAllRanks()`: Spalten `title`/`pos` statt `name`/`position` (Quota-Seite war leer)
- Widget-Include-Pfad: `/../templates/` statt `/../../templates/`
- DB-Migration: korrekter Tabellenpräfix `[prefix]_mumble_*` statt `[prefix]_ml_mumble_*`

## [v0.3.0] — 2026-05-08

### Hinzugefügt
- **Channel-Viewer** — zeigt Channel-Struktur und Online-User ohne Mumble-Client-Verbindung (SQLite + Docker-Log-Parsing)
- **Einbettbares Widget** (`?p=mumble_widget`) — standalone ohne CMS-Layout, Token- oder öffentlicher Zugriff, konfigurierbarer Auto-Refresh
- Server-Name als klickbarer `mumble://`-Link im Widget und Channel-Viewer
- Widget-Einstellungen: Modus (öffentlich/Token/deaktiviert), Refresh-Intervall, Token-Regenerierung
- AJAX-Endpoints: `viewer_data`, `widget_save`, `widget_regen`

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
