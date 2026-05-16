# Changelog — Easy2-Mumble

Alle nennenswerten Änderungen an diesem Projekt werden hier dokumentiert.

## [v0.7.0] — 2026-05-16

### Hinzugefügt
- **Mumble Dashboard** (`mumble_dashboard`) — Echtzeit-Übersicht aller Server auf einer Seite
  - 4 Zusammenfassungskarten: Server (laufend/gesamt), Nutzer online, Gesamtbandbreite, Ø-Ping
  - Pro-Server-Karten: Status-Badge, Nutzer X/Y mit Fortschrittsbalken, Uptime, Bandbreite, CPU/RAM, Channels/Bans, Netz RX/TX
  - Globale Nutzertabelle: User, Server, Channel, KB/s, Ping (farbig), Idle, OS
  - Automatische Aktualisierung alle 30 Sekunden
  - Admin sieht alle Server, Nutzer sehen nur ihre eigenen
- Neue Agent-Methode: `getDashboard()` (GET `/v1/servers/{cid}/dashboard`) — ICE + Docker-Stats in einem Aufruf
- `_user_to_dict` erweitert: `bytespersec`, `udp_ping`, `tcp_ping`, `tcp_only`, `online_secs`, `os`, `os_version`, `version`
- DB-Migration `migrate_v0.7.0.sql`: Site `mumble_dashboard` (212), Menüeintrag Dashboard

### Behoben
- ACL-Verwaltung aus dem Mumble-Menü entfernt — ACL ist nur über die Server-Detailseite erreichbar

---

## [v0.6.0] — 2026-05-16

### Hinzugefügt
- **Channel-Verwaltung** (`mumble_channels`) — Channel-Baum mit visueller Hierarchie (`├─` / `└─` / `│`), Channel erstellen/umbenennen/löschen, Sub-Channels, alles live via ICE ohne Neustart
- **Ban-Verwaltung** (`mumble_bans`) — aktive IP-Bans anzeigen, neue Bans setzen (IP, Subnetz-Bits, Dauer, Grund, Username), Bans aufheben — alles live via ICE
- **Einstellungen live speichern** — Name, Passwort, Max-Nutzer und Begrüßungstext werden via ICE `setConf()` sofort übernommen, kein Server-Neustart mehr nötig; der Button heißt jetzt "Speichern" statt "Speichern & Neustart"
- **Kick & Mute im Channel-Viewer** — User direkt aus dem Viewer per Klick kicken oder stummschalten (ICE `kickUser()` / `setState()`)
- Neue Agent-Methoden: `updateSettingsLive()` (PATCH `/v1/servers/{cid}/live`)
- Neue PHP-Methoden: `updateMumbleSettingsLive()`, `addMumbleChannel()`, `updateMumbleChannel()`, `removeMumbleChannel()`, `getMumbleBans()`, `setMumbleBans()`
- DB-Migration `migrate_v0.6.0.sql`: Sites für `mumble_channels` (207) und `mumble_bans` (208)

### Geändert
- `mumble_edit.php`: "Einstellungen bearbeiten" nutzt jetzt AJAX statt Form-POST, kein Neustart
- `mumble_edit.php`: "Channels verwalten" und "Bans verwalten" als Aktions-Buttons
- `mumble-edit.js`: Settings-Handler ergänzt (live save via `fetch()`)
- `mumble_widget.php`: `htmlspecialchars()` erwartet String — ICE-User-Objekte werden jetzt korrekt zu Namen extrahiert

### Behoben
- **Löschen-Berechtigung** — "Server löschen" ist jetzt nur noch für Admins (`canAdminAll()`) möglich, nicht mehr für zugewiesene Nutzer; Button wird in Übersicht und Detailseite entsprechend ausgeblendet
- **ICE-Port-Konflikt** bei mehreren Containern im `--network host` Modus — jeder Container muss einen eigenen ICE-Port bekommen (`MUMBLE_CONFIG_ICE` Env-Variable beim Erstellen setzen)

---

## [v0.5.0] — 2026-05-16

### Hinzugefügt
- **ZeroC ICE Integration** — ersetzt das bisherige Log-/SQLite-Parsing vollständig durch die native Mumble-ICE-Schnittstelle
  - Live Channel-Viewer: exakte Channel-Struktur, User-Positionen und Mute/Deaf-Status direkt aus Mumble
  - ICE-Port wird automatisch aus der Container-Konfiguration gelesen (Grep auf `/data/mumble_server_config.ini`)
  - Fallback auf Port 6502 wenn kein ICE-Port konfiguriert
- **ACL-Verwaltung** (`mumble_acl`) — vollständiger ACL-Editor mit Channel-Baum, ACL-Einträge (Gruppe/User, Grant/Deny, Hier/Sub), Gruppen-Verwaltung (Mitglieder hinzufügen/entfernen), Berechtigungs-Modal mit allen 14 Mumble-Permissions — alles live via ICE ohne Neustart
- Neue Agent-Methoden: `getChannelAcl()`, `setChannelAcl()`, `getLiveUsers()`, `kickUser()`, `updateUser()`, `getChannels()`, `addChannel()`, `updateChannel()`, `removeChannel()`, `getBans()`, `setBans()`
- Neue PHP-Methoden in `mumble.php`: `getLiveUsers()`, `kickMumbleUser()`, `muteMumbleUser()`, `getMumbleChannels()`, `getChannelAcl()`, `setChannelAcl()`
- DB-Migration `migrate_v0.5.0.sql`: Site `mumble_acl` (206), Regel `mumble_acl` (205), Menüeintrag

### Geändert
- `mumble-edit.js`: Channel-Viewer rendert jetzt ICE-User-Objekte (`{session, name, mute, deaf, …}`) statt einfacher Strings
- `mumble_edit.php`: Kick- und Mute-Buttons im Channel-Viewer, neue Aktions-Buttons (ACL, Channels, Bans)
- `run.user.php`: `viewer_data`-Handler funktioniert jetzt für `mumble_edit` **und** `mumble_acl`

### Behoben
- ICE `loadSlice()` erwartet Liste, nicht String (zeroc-ice 3.8 API)
- ICE-Port-Berechnung via `mumble_port + 10000` kann `> 65535` sein — ersetzt durch Config-Grep mit Fallback 6502

---

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
