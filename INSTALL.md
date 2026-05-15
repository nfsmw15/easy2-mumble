# Installation

Diese Anleitung beschreibt die vollständige Installation von Easy2-Mumble auf einem laufenden Easy2-PHP8 (`main-dashboard`).

## Voraussetzungen prüfen

```bash
php -v                            # 8.0 - 8.3
php -m | grep -E 'pdo|curl'       # pdo, pdo_mysql, curl müssen vorhanden sein
```

## Schritt 1: Datenbank-Migration

Die Datei `sql/install.sql` enthält den Platzhalter `[prefix]` (Konvention von Easy2). Vor dem Import durch deinen Tabellen-Prefix ersetzen:

```bash
sed 's/\[prefix\]/easy/g' sql/install.sql > /tmp/mumble.sql
mysql -u <dbuser> -p <dbname> < /tmp/mumble.sql
```

Das Script:

* legt 4 Tabellen an (`*_ml_mumble_host`, `*_ml_mumble_server`, `*_ml_mumble_log`, `*_ml_mumble_quota`)
* registriert 6 Seiten in `*_ml_sites` (IDs 200–205)
* registriert 5 Regeln in `*_ml_rules` (IDs 200–204)
* legt 5 Menüpunkte in `*_ml_menu` an (IDs 200–204)

Falls du IDs 200–205 schon belegt hast, in `install.sql` vorher anpassen.

## Schritt 2: PHP-Klassen kopieren

```bash
# Annahme: dein Webroot ist /var/www/easy
cp system/classes/mumble.php       /var/www/easy/system/classes/
cp system/classes/mumble_agent.php /var/www/easy/system/classes/

chown www-data:www-data /var/www/easy/system/classes/mumble*.php
chmod 0644              /var/www/easy/system/classes/mumble*.php
```

Der Autoloader in `system/classes.run.php` findet diese Klassen automatisch.

## Schritt 3: Templates kopieren

```bash
cp -r templates/mumble /var/www/easy/templates/

chown -R www-data:www-data /var/www/easy/templates/mumble
chmod 0644 /var/www/easy/templates/mumble/*.php
```

## Schritt 4: Snippets in `.user.php` einfügen

In `system/classes.run.user.php` am Ende einfügen:

```php
// Mumble-Erweiterung initialisieren
$mumble = new mumble();
```

Den vollständigen Inhalt aus `user-snippets/run.user.php` in `system/run.user.php` einfügen.

Bei leeren `.user.php`-Dateien kannst du sie auch komplett mit dem Inhalt aus `user-snippets/` ersetzen (achte auf `<?php`).

## Schritt 5: Berechtigungen vergeben

In der EASY-Oberfläche → Einstellungen → Ränge:

| Rang | Empfohlene Regeln | Empfohlene Sites |
|------|-------------------|------------------|
| **Webadmin** (1) | hat `all` | hat `all` |
| **Mitglied** (2) | `mumble_view`, `mumble_create` | `mumble`, `mumble_new`, `mumble_edit`, `mumble_logs` |
| **Moderator** | zusätzlich `mumble_admin` | dieselben |
| **Hosts-Admin** | zusätzlich `mumble_hosts`, `mumble_quota` | zusätzlich `mumble_hosts`, `mumble_quota` |

## Schritt 6: Mumble-Host einrichten

Auf einem Linux-Server (Debian/Ubuntu, Proxmox-VM oder Root-Server):

```bash
git clone https://github.com/nfsmw15/mumble-agent.git
cd mumble-agent
sudo bash setup.sh
```

Den am Ende ausgegebenen Token notieren. Details: `mumble-agent/README.md`.

## Schritt 7: Reverse-Proxy mit TLS

Der Agent lauscht intern auf `127.0.0.1:8000`. Davor muss ein TLS-Reverse-Proxy stehen.

Beispiel mit Caddy:
```caddyfile
mumble1.example.com:8443 {
    reverse_proxy 127.0.0.1:8000
}
```

Mit Traefik (über `dns-mgr`):
```bash
dns-mgr add-service mumble-agent-host1 \
  --backend 127.0.0.1:8000 \
  --domain mumble1.example.com \
  --internal
```

## Schritt 8: Firewall

* TCP **8443** (oder Port deiner Wahl): Webserver → Mumble-Host
* TCP+UDP **64738–64838**: Internet → Mumble-Host

## Schritt 9: Host in der Weboberfläche eintragen

Anmelden als Admin → "Mumble" → "Hosts verwalten" → "Neuer Host":

| Feld | Wert |
|------|------|
| Name | `proxmox-vps-01` |
| Hostname | `mumble1.example.com` |
| Agent-URL | `https://mumble1.example.com:8443` |
| Agent-Token | (aus Setup) |
| Port-Min | `64738` |
| Port-Max | `64838` |
| Max. Server | `20` |
| Aktiv | ✓ |

In der Host-Liste sollte direkt `online` mit grünem Badge erscheinen.

## Schritt 10: Ersten Server anlegen

"Mumble" → "Neuer Server" → ausfüllen → Speichern.

## Optional: Dashboard-Widget einbauen

In `templates/dashboard-home.php` in der Icon-Cards Row (sucht nach `<!-- Icon Cards-->`) einfügen:

```php
<?php include __DIR__ . '/mumble/widget.php'; ?>
```

Das Widget rendert sich passend als 1–3 SB-Admin-Kacheln (eigene Server, Online-User, ggf. Admin-Übersicht).

## Smoke-Test ausführen

```bash
cd <repo>
php tests/integration_test.php
```

Erwartete Ausgabe: `33 / 33 bestanden`.

## Deinstallation

```bash
sed 's/\[prefix\]/easy/g' sql/uninstall.sql | mysql -u <user> -p <db>

rm /var/www/easy/system/classes/mumble.php
rm /var/www/easy/system/classes/mumble_agent.php
rm -rf /var/www/easy/templates/mumble
```

Snippet-Blöcke aus den `.user.php` Dateien manuell entfernen (die "Easy2-Mumble"-Header markieren die Stellen).

Auf den Mumble-Hosts: siehe [mumble-agent README](https://github.com/nfsmw15/mumble-agent).

## Update von einer älteren Version

Jede `sql/migrate_vX.Y.Z.sql` muss einmalig eingespielt werden:

```bash
sed 's/\[prefix\]/easy/g' sql/migrate_v0.4.0.sql | mysql -u <user> -p <db>
```

`migrate_v0.4.0.sql` ergänzt:
- Tabelle `*_mumble_server_members` (Mitglieder-Zuweisung)
- Spalten `ssl_cert_path`, `ssl_key_path` in `*_mumble_host`

Außerdem `system/js/mumble-edit.js` kopieren (neu in v0.4.0):

```bash
cp system/js/mumble-edit.js /var/www/easy/system/js/
```

## Fehlersuche

| Symptom | Ursache |
|---------|---------|
| "Class 'mumble' not found" | Snippet in `classes.run.user.php` fehlt |
| Seite leer / Site nicht aufrufbar | Site-Eintrag in `_ml_sites` fehlt oder Rang hat Site nicht freigegeben |
| "Keine Berechtigung" | Regel `mumble_view` etc. im Rang nicht vergeben |
| 401 vom Agent | Agent-Token im Host-Eintrag falsch |
| Connection refused | Reverse-Proxy nicht erreichbar oder Agent läuft nicht |
| Server bleibt auf "creating" | Agent-Aufruf fehlgeschlagen — `journalctl -u mumble-agent` |
