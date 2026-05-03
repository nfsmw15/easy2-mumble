# Easy2-Mumble

Mumble-Server-Verwaltung als Erweiterung für [Easy2-PHP8](https://github.com/nfsmw15/Easy2-PHP8) Branch `main-dashboard`.

Mitglieder können in einem Webinterface eigene Mumble-Server (Voice-Chat) anlegen, starten, stoppen und löschen. Administratoren verwalten mehrere Mumble-Hosts und legen Quotas pro Rang fest.

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

* **Webserver-Erweiterung** (dieses Repo): zwei PHP-Klassen + sechs SB-Admin-Templates, integriert sich als Drop-in in die bestehende EASY-2.0-Verzeichnisstruktur
* **Python-Agent** ([mumble-agent](https://github.com/nfsmw15/mumble-agent)): eigenständiger Service auf jedem Mumble-Host

## Features

* Server anlegen, löschen, starten, stoppen, neu starten
* Passwort und maximale Nutzerzahl konfigurierbar
* Live-Statistik (online User, Uptime), automatische Aktualisierung alle 15s
* Container-Logs einsehen (100/300/1000 Zeilen)
* Quota-System pro Rang
* Mehrere Mumble-Hosts unterstützt
* Audit-Log aller Aktionen
* SB-Admin-konformes Dashboard-Widget

## Voraussetzungen

* Easy2-PHP8 Branch `main-dashboard`
* PHP 8.0–8.3 mit PDO/MySQL und cURL
* MySQL/MariaDB (utf8mb4, InnoDB)
* Mindestens ein Linux-Server mit Docker und [mumble-agent](https://github.com/nfsmw15/mumble-agent)

## Verzeichnisstruktur

Beim Auspacken werden die Dateien direkt in den Webroot kopiert. Die Struktur ist 1:1 zur Easy2-Konvention:

```
Easy2-Mumble/
├── system/classes/
│   ├── mumble.php             →  /system/classes/mumble.php
│   └── mumble_agent.php       →  /system/classes/mumble_agent.php
├── templates/mumble/
│   ├── mumble.php             →  /templates/mumble/mumble.php
│   ├── mumble_new.php         →  /templates/mumble/mumble_new.php
│   ├── mumble_edit.php        →  /templates/mumble/mumble_edit.php
│   ├── mumble_logs.php        →  /templates/mumble/mumble_logs.php
│   ├── mumble_hosts.php       →  /templates/mumble/mumble_hosts.php
│   ├── mumble_quota.php       →  /templates/mumble/mumble_quota.php
│   └── widget.php             →  /templates/mumble/widget.php   (optional)
├── sql/
│   ├── install.sql
│   └── uninstall.sql
├── user-snippets/
│   ├── classes.run.user.php   (Inhalt nach /system/classes.run.user.php)
│   └── run.user.php           (Inhalt nach /system/run.user.php)
└── tests/
    └── integration_test.php
```

## Schnellinstallation

1. **DB:** `sql/install.sql` mit deinem Tabellen-Prefix einspielen
2. **Dateien:** `system/classes/*` und `templates/mumble/` in den Webroot
3. **Snippets:** Inhalt aus `user-snippets/*.user.php` in die existierenden `system/*.user.php` kopieren
4. **Rechte:** in EASY-Oberfläche neue Regeln/Sites pro Rang freischalten
5. **Hosts:** [mumble-agent](https://github.com/nfsmw15/mumble-agent) installieren und unter "Hosts verwalten" eintragen

Ausführlich: siehe [INSTALL.md](INSTALL.md).

## Lizenz

AGPLv3 — siehe [LICENSE](LICENSE).

## Autor

Andreas P. — [https://nfsmw15.de](https://nfsmw15.de)
