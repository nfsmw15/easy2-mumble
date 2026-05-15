#!/bin/bash
set -uo pipefail

# ── Konfiguration aus Umgebungsvariablen ────────────────────────────────────
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-easy2mumble}"
DB_USER="${DB_USER:-easy2}"
DB_PASS="${DB_PASS:-changeme}"
DB_PREFIX="${DB_PREFIX:-ml}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-changeme}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
SITE_TITLE="${SITE_TITLE:-Mumble WebUI}"
SITE_TITLE_SHORT="${SITE_TITLE_SHORT:-Mumble}"

WEBROOT=/var/www/html
CONFIG="$WEBROOT/system/config.inc.php"
INIT_MARKER="$WEBROOT/system/.installed"

mysql_cmd() { mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" "$@"; }

# ── Warten bis der App-User verbinden kann ───────────────────────────────────
# Wir warten nicht nur auf den Ping, sondern auf eine echte Verbindung mit dem
# App-User — dadurch ist sicher dass MariaDB die Benutzer-Grants fertig hat.
echo "[entrypoint] Warte auf MariaDB ($DB_HOST:$DB_PORT)..."
until mysql_cmd -e "SELECT 1" &>/dev/null; do
    sleep 2
done
echo "[entrypoint] MariaDB erreichbar."

# ── Erstinstallation ─────────────────────────────────────────────────────────
if [[ ! -f "$INIT_MARKER" ]]; then
    echo "[entrypoint] Erstinstallation wird durchgeführt..."

    # Easy2 Basistabellen anlegen
    # Easy2 SQL nutzt [prefix]_ml_* → kurzes Prefix einsetzen (z.B. "ml")
    for sql in /docker-init/easy2-sql/*.sql; do
        echo "  → $(basename $sql)"
        sed "s/\[prefix\]/${DB_PREFIX}/g" "$sql" | mysql_cmd 2>/dev/null || true
    done

    # easy2-mumble Tabellen anlegen
    # install.sql nutzt [prefix]_* → volles Prefix einsetzen (z.B. "ml_ml")
    echo "  → easy2-mumble install.sql"
    sed "s/\[prefix\]/${DB_PREFIX}_ml/g" /docker-init/sql/install.sql | mysql_cmd

    # config.inc.php schreiben
    cat > "$CONFIG" << PHPEOF
<?php
\$db_config = [
    'host'     => '${DB_HOST}',
    'database' => '${DB_NAME}',
    'prefix'   => '${DB_PREFIX}_ml',
    'user'     => '${DB_USER}',
    'passwd'   => '${DB_PASS}',
];
define('Prefix', \$db_config['prefix']);
try {
    \$dsn = 'mysql:host=' . \$db_config['host'] . ';dbname=' . \$db_config['database'] . ';charset=utf8mb4';
    \$pdo = new \PDO(\$dsn, \$db_config['user'], \$db_config['passwd'], [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);
    \$dberror = false;
} catch (\PDOException \$e) {
    \$dberror = true;
}
PHPEOF

    # Admin-User anlegen
    PASS_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")

    mysql_cmd << SQL
INSERT IGNORE INTO \`${DB_PREFIX}_ml_main\` (name, value) VALUES
  ('site_title',       '${SITE_TITLE}'),
  ('short_site_title', '${SITE_TITLE_SHORT}'),
  ('regist_active',    '0'),
  ('pwv_active',       '1');

INSERT IGNORE INTO \`${DB_PREFIX}_ml_user\`
  (username, password, email, active, rank)
SELECT '${ADMIN_USER}', '${PASS_HASH}', '${ADMIN_EMAIL}', 1,
       (SELECT id FROM \`${DB_PREFIX}_ml_ranks\` WHERE special='bold' LIMIT 1)
WHERE NOT EXISTS (
  SELECT 1 FROM \`${DB_PREFIX}_ml_user\` WHERE username='${ADMIN_USER}'
);
SQL

    # install/-Ordner entfernen (Sicherheit)
    rm -rf "$WEBROOT/install"

    # Snippets einspielen
    if ! grep -q 'Easy2-Mumble' "$WEBROOT/system/classes.run.user.php" 2>/dev/null; then
        cat /docker-init/snippets/classes.run.user.php >> "$WEBROOT/system/classes.run.user.php"
    fi
    if ! grep -q 'Easy2-Mumble' "$WEBROOT/system/run.user.php" 2>/dev/null; then
        cat /docker-init/snippets/run.user.php >> "$WEBROOT/system/run.user.php"
    fi

    touch "$INIT_MARKER"
    echo "[entrypoint] Installation abgeschlossen."
fi

# ── Migrationen ausführen ────────────────────────────────────────────────────
for mig in /docker-init/sql/migrate_v*.sql; do
    marker="$WEBROOT/system/.migrated_$(basename "$mig" .sql)"
    if [[ ! -f "$marker" ]]; then
        echo "[entrypoint] Migration: $(basename "$mig")"
        sed "s/\[prefix\]/${DB_PREFIX}_ml/g" "$mig" | mysql_cmd  # volles Prefix
        touch "$marker"
    fi
done

# ── Apache starten ───────────────────────────────────────────────────────────
echo "[entrypoint] Starte Apache..."
exec apache2-foreground
