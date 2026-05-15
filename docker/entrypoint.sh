#!/bin/bash
set -uo pipefail

# ── Konfiguration ────────────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-easy2mumble}"
DB_USER="${DB_USER:-easy2}"
DB_PASS="${DB_PASS:-changeme}"
DB_PREFIX="${DB_PREFIX:-ml}"           # kurzes Prefix  → "ml"
CMS_PREFIX="${DB_PREFIX}_ml"           # CMS Prefix     → "ml_ml"  (= Prefix-Konstante in PHP)
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-changeme}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
SITE_TITLE="${SITE_TITLE:-Mumble WebUI}"
SITE_TITLE_SHORT="${SITE_TITLE_SHORT:-Mumble}"

WEBROOT=/var/www/html
PERSIST=/var/easy2-data            # persistentes Volume
CONFIG="$WEBROOT/system/config.inc.php"
INIT_MARKER="$PERSIST/.installed"

mysql_cmd() { mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" "$@"; }

# ── Warten bis der App-User verbinden kann ───────────────────────────────────
echo "[entrypoint] Warte auf MariaDB ($DB_HOST:$DB_PORT)..."
until mysql_cmd -e "SELECT 1" &>/dev/null; do
    sleep 2
done
echo "[entrypoint] MariaDB erreichbar."

# ── Persistenz-Verzeichnis sicherstellen ─────────────────────────────────────
mkdir -p "$PERSIST"

# ENCRYPT_KEY persistent generieren (einmalig, nie mehr ändern)
KEY_FILE="$PERSIST/.encrypt_key"
if [[ ! -f "$KEY_FILE" ]]; then
    openssl rand -hex 32 > "$KEY_FILE"
fi
ENCRYPT_KEY=$(cat "$KEY_FILE")

# ── Erstinstallation ─────────────────────────────────────────────────────────
if [[ ! -f "$INIT_MARKER" ]]; then
    echo "[entrypoint] Erstinstallation wird durchgeführt..."

    # Alle SQL-Dateien nutzen [prefix]_ml_* Format → kurzes Prefix einsetzen
    for sql in /docker-init/easy2-sql/*.sql; do
        echo "  → $(basename $sql)"
        sed "s/\[prefix\]/${DB_PREFIX}/g" "$sql" | mysql_cmd 2>/dev/null || true
    done

    echo "  → easy2-mumble install.sql"
    sed "s/\[prefix\]/${DB_PREFIX}/g" /docker-init/sql/install.sql | mysql_cmd

    # config.inc.php schreiben
    cat > "$CONFIG" << PHPEOF
<?php
\$db_config = [
    'host'     => '$DB_HOST',
    'database' => '$DB_NAME',
    'prefix'   => '$CMS_PREFIX',
    'user'     => '$DB_USER',
    'passwd'   => '$DB_PASS',
];
define('Prefix',       \$db_config['prefix']);
define('ENCRYPT_KEY',  '$ENCRYPT_KEY');
define('COOKIE_SECRET','$ENCRYPT_KEY');
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

    # Admin-User anlegen und Site-Titel setzen
    PASS_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")

    mysql_cmd << SQL
UPDATE \`${CMS_PREFIX}_main\` SET value='${SITE_TITLE}'       WHERE tag='site_title';
UPDATE \`${CMS_PREFIX}_main\` SET value='${SITE_TITLE_SHORT}' WHERE tag='short_site_title';
UPDATE \`${CMS_PREFIX}_main\` SET value='0'                   WHERE tag='regist_active';

INSERT IGNORE INTO \`${CMS_PREFIX}_user\`
  (username, password, email, active, rank)
SELECT '${ADMIN_USER}', '${PASS_HASH}', '${ADMIN_EMAIL}', 1,
       (SELECT id FROM \`${CMS_PREFIX}_ranks\` WHERE special='bold' LIMIT 1)
WHERE NOT EXISTS (
  SELECT 1 FROM \`${CMS_PREFIX}_user\` WHERE username='${ADMIN_USER}'
);
SQL

    # install/-Ordner entfernen (Sicherheit)
    rm -rf "$WEBROOT/install"

    # Snippets einspielen — <?php überspringen da Datei es schon hat
    if ! grep -q 'Easy2-Mumble' "$WEBROOT/system/classes.run.user.php" 2>/dev/null; then
        grep -v '^<?php' /docker-init/snippets/classes.run.user.php >> "$WEBROOT/system/classes.run.user.php"
    fi
    if ! grep -q 'Easy2-Mumble' "$WEBROOT/system/run.user.php" 2>/dev/null; then
        grep -v '^<?php' /docker-init/snippets/run.user.php >> "$WEBROOT/system/run.user.php"
    fi

    touch "$INIT_MARKER"
    echo "[entrypoint] Installation abgeschlossen."
fi

# ── Migrationen ausführen ────────────────────────────────────────────────────
for mig in /docker-init/sql/migrate_v*.sql; do
    marker="$PERSIST/.migrated_$(basename "$mig" .sql)"
    if [[ ! -f "$marker" ]]; then
        echo "[entrypoint] Migration: $(basename "$mig")"
        sed "s/\[prefix\]/${DB_PREFIX}/g" "$mig" | mysql_cmd
        touch "$marker"
    fi
done

# ── Apache starten ───────────────────────────────────────────────────────────
echo "[entrypoint] Starte Apache..."
exec apache2-foreground
