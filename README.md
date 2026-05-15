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
- Server anlegen, löschen, starten, stoppen, neu starten
- Server-Passwort, maximale Nutzerzahl und Begrüßungstext konfigurierbar
- Rohe INI-Konfiguration direkt im Browser bearbeiten
- Live-Statistik (online User, Uptime) mit automatischer Aktualisierung
- Container-Logs einsehen (100 / 300 / 1000 Zeilen)

### SuperUser-Zugang
- SuperUser-Passwort wird beim Server-Start automatisch aus den Logs ausgelesen und gespeichert
- Passwort im Webinterface anzeigen (Ein-Klick-Reveal) und kopieren
- Passwort jederzeit zurücksetzen (zufällig oder eigenes)

### Channel-Viewer
- Zeigt die Channel-Struktur und aktuell verbundene Nutzer in Echtzeit
- Liest Daten direkt aus der SQLite-Datenbank und den Container-Logs — **kein Mumble-Client-Connect**, keine Unterbrechung für verbundene Nutzer
- Manuell aktualisierbar per Button

### Einbettbares Widget
- Öffentliche Seite (`?p=mumble_widget`) für jeden Server, einbettbar per `<iframe>`
- Drei Zugriffsmodi pro Server: **Öffentlich** (nur Server-ID), **Token-geschützt** (eigener Zugangsschlüssel) oder **Deaktiviert**
- Konfigurierbares Auto-Refresh-Intervall (0 = kein Auto-Refresh)
- Fertiger iframe-Code und Widget-URL zum Kopieren direkt im Dashboard
- Token kann jederzeit neu generiert werden (invalidiert bestehende Einbettungen)

### Administration
- Quota-System pro Rang (max. Server, max. Nutzer)
- Mehrere Mumble-Hosts unterstützt
- Audit-Log aller Aktionen
- SB-Admin-konformes Dashboard-Widget (Übersicht aller Server)

## Voraussetzungen

- Easy2-PHP8 Branch `main-dashboard`
- PHP 8.1+ mit PDO/MySQL und cURL
- MySQL/MariaDB (utf8mb4, InnoDB)
- Mindestens ein Linux-Server mit Docker und [mumble-agent](https://github.com/nfsmw15/mumble-agent)

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
│   ├── mumble_edit.php            →  Server-Details, Channel-Viewer, Widget-Einstellungen
│   ├── mumble_logs.php            →  Container-Logs
│   ├── mumble_config.php          →  INI-Editor
│   ├── mumble_widget.php          →  Öffentlich einbettbarer Channel-Viewer
│   ├── mumble_hosts.php           →  Host-Verwaltung (Admin)
│   ├── mumble_quota.php           →  Quota-Verwaltung (Admin)
│   └── widget.php                 →  Dashboard-Widget
├── sql/
│   ├── install.sql
│   ├── migrate_v0.3.0.sql
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
