# Easy2-Mumble

Mumble-Server-Verwaltung als Erweiterung für [Easy2-PHP8](https://github.com/nfsmw15/Easy2-PHP8) Branch `main-dashboard`.

Mitglieder können in einem Webinterface eigene Mumble-Server (Voice-Chat) anlegen, starten, stoppen und konfigurieren. Administratoren verwalten mehrere Mumble-Hosts und legen Quotas pro Rang fest.

## Architektur

```
┌─────────────────────────┐
│ Easy2-PHP8 (main-dash.) │
│  + Easy2-Mumble         │      Webinterface, Datenbank, Auth
└────────────┬────────────┘
             │ HTTPS + Bearer-Token
   ┌─────────┼──────────────┐
┌──▼───┐ ┌───▼───┐ ┌────────▼───┐
│Host 1│ │Host 2 │ │   Host N   │  Proxmox VMs / Root-Server
│ + mumble-agent (Python/FastAPI)│  https://github.com/nfsmw15/mumble-agent
│ + Docker → mumble-server       │
└──────┘ └───────┘ └────────────┘
```

- **Webserver-Erweiterung** (dieses Repo): PHP-Klassen + SB-Admin-Templates, Drop-in in die EASY-2.0-Verzeichnisstruktur
- **Python-Agent** ([mumble-agent](https://github.com/nfsmw15/mumble-agent)): eigenständiger Service auf jedem Mumble-Host

## Features

### Server-Verwaltung
- Server anlegen, starten, stoppen, neu starten (Löschen nur für Admins)
- **Einstellungen live ändern** (Name, Passwort, Max-Nutzer, Begrüßungstext) via ZeroC ICE — **kein Neustart** nötig
- Server-Config (INI) direkt im Browser bearbeiten (erfordert Neustart)
- Live-Statistik (online User, Uptime) mit automatischer Aktualisierung
- Container-Logs einsehen (100 / 300 / 1000 Zeilen)

### Dashboard
- Echtzeit-Übersicht aller Server auf einer Seite (`?p=mumble_dashboard`)
- Zusammenfassungskarten: laufende Server, Nutzer online, Gesamtbandbreite, Ø-Ping
- Pro-Server: Status, Nutzer X/Y, Uptime, Bandbreite, CPU/RAM, Channels, Bans
- Globale Nutzertabelle mit Ping-Ampel, Idle-Zeit, OS und Bandwidth pro User
- Auto-Refresh alle 30 Sekunden — Admin sieht alle, Nutzer nur ihre eigenen

### ZeroC ICE Integration
Ab v0.5.0 kommuniziert das Webinterface direkt mit dem laufenden Mumble-Prozess via **ZeroC ICE** — kein Parsing von Logs oder SQLite-Dateien mehr. Alle Änderungen werden sofort aktiv ohne Server-Neustart.

### Channel-Viewer
- Zeigt die Channel-Struktur und aktuell verbundene Nutzer in Echtzeit via ICE
- Mute/Deaf-Status pro User sichtbar
- User direkt aus dem Viewer **kicken** oder **stummschalten** (ohne Neustart)
- Manuell aktualisierbar per Button

### ACL-Verwaltung
- Vollständiger ACL-Editor für jeden Channel (`?p=mumble_acl`)
- Einträge pro Gruppe oder registrierten User mit Grant/Deny pro Permission
- Flags: "Gilt für diesen Channel" / "Gilt für Unter-Channels"
- Alle 14 Mumble-Permissions (Betreten, Sprechen, Kicken, Bannen, …)
- Gruppen verwalten (erstellen, Mitglieder hinzufügen/entfernen, Vererbung)
- Änderungen werden **sofort live** übernommen — kein Neustart

### Channel-Verwaltung
- Channel-Baum mit visueller Hierarchie (`├─` / `└─` / `│`) (`?p=mumble_channels`)
- Channels erstellen (Root und Sub-Channels), umbenennen, löschen
- Sortierungs-Position und Beschreibung bearbeitbar
- Änderungen **sofort live** via ICE

### Ban-Verwaltung
- Aktive IP-Bans auflisten, neue Bans setzen (`?p=mumble_bans`)
- IP-Adresse, Subnetz-Bits, Dauer (Minuten, 0 = permanent), Grund, Username
- Bans einzeln aufheben
- Änderungen **sofort live** via ICE

### SuperUser-Zugang
- SuperUser-Passwort anzeigen (Ein-Klick-Reveal) und kopieren
- Passwort jederzeit zurücksetzen (zufällig oder eigenes)

### Einbettbares Widget
- Öffentliche Seite (`?p=mumble_widget`) für jeden Server, einbettbar per `<iframe>`
- Drei Zugriffsmodi: **Öffentlich**, **Token-geschützt** oder **Deaktiviert**
- Konfigurierbares Auto-Refresh-Intervall
- Fertiger iframe-Code und Widget-URL im Dashboard

### Administration
- Quota-System pro Rang (max. Server, max. Nutzer)
- Mehrere Mumble-Hosts unterstützt
- Audit-Log aller Aktionen
- Server löschen nur für Admins möglich
- SB-Admin-konformes Dashboard-Widget

## Voraussetzungen

- Easy2-PHP8 Branch `main-dashboard`
- PHP 8.1+ mit PDO/MySQL und cURL
- MySQL/MariaDB (utf8mb4, InnoDB)
- Mindestens ein Linux-Server mit Docker und [mumble-agent](https://github.com/nfsmw15/mumble-agent) **≥ v2.0.0** (mit ZeroC ICE)
- Mumble-Server-Container mit aktiviertem ICE-Endpoint (`MUMBLE_CONFIG_ICE=tcp -h 127.0.0.1 -p 6502`)
- Bei mehreren Containern auf demselben Host (`--network host`): **jeder Container braucht einen eigenen ICE-Port** (z.B. 6502, 6503, 6504…) via `MUMBLE_CONFIG_ICE`

## Verzeichnisstruktur

```
easy2-mumble/
├── system/
│   ├── classes/
│   │   ├── mumble.php             →  /system/classes/mumble.php
│   │   └── mumble_agent.php       →  /system/classes/mumble_agent.php
│   └── js/
│       └── mumble-edit.js         →  /system/js/mumble-edit.js
├── templates/mumble/
│   ├── mumble.php                 →  Übersicht aller Server
│   ├── mumble_new.php             →  Server anlegen
│   ├── mumble_edit.php            →  Server-Details, Channel-Viewer, Einstellungen
│   ├── mumble_acl.php             →  ACL-Verwaltung (ICE, live)
│   ├── mumble_channels.php        →  Channel-Verwaltung (ICE, live)
│   ├── mumble_bans.php            →  Ban-Verwaltung (ICE, live)
│   ├── mumble_logs.php            →  Container-Logs
│   ├── mumble_config.php          →  INI-Editor
│   ├── mumble_widget.php          →  Öffentlich einbettbarer Channel-Viewer
│   ├── mumble_hosts.php           →  Host-Verwaltung (Admin)
│   ├── mumble_quota.php           →  Quota-Verwaltung (Admin)
│   └── widget.php                 →  Dashboard-Widget
├── sql/
│   ├── install.sql
│   ├── migrate_v0.3.0.sql
│   ├── migrate_v0.5.0.sql         →  ACL-Seite, Regel, Menüeintrag
│   ├── migrate_v0.6.0.sql         →  Channels- und Bans-Seiten
│   └── uninstall.sql
├── user-snippets/
│   ├── classes.run.user.php
│   └── run.user.php
└── tests/
    └── integration_test.php
```

## Schnellinstallation

1. **DB:** `sql/install.sql` mit deinem Tabellen-Prefix einspielen
2. **Dateien:** alle Inhalte aus `system/` und `templates/` in den Webroot kopieren
3. **Snippets:** Inhalt aus `user-snippets/*.user.php` in die bestehenden `system/*.user.php` kopieren
4. **Rechte:** in der EASY-Oberfläche neue Regeln/Sites pro Rang freischalten
5. **Hosts:** [mumble-agent](https://github.com/nfsmw15/mumble-agent) installieren und unter "Hosts verwalten" eintragen

Ausführlich: siehe [INSTALL.md](INSTALL.md).

## Lizenz

AGPLv3 — siehe [LICENSE.md](LICENSE.md)

## Autor

Andreas P. — [https://nfsmw15.de](https://nfsmw15.de)
