<?php
declare(strict_types=1);

/**
 * integration_test.php
 *
 * Testet die Mumble-Klassen als Drop-in unter system/classes/.
 * Aufruf: php tests/integration_test.php
 */

$tests = 0; $passed = 0; $fails = [];

function check(string $label, bool $cond, string $info = ''): void {
    global $tests, $passed, $fails;
    $tests++;
    if ($cond) { $passed++; echo "  ✓ $label\n"; }
    else { $fails[] = "  ✗ $label".($info ? " [$info]" : ''); echo "  ✗ $label".($info ? " [$info]" : '')."\n"; }
}

function section(string $s): void { echo "\n=== $s ===\n"; }

section("1. Core-System-Mock");

if (!defined('Prefix')) define('Prefix', 'test');

try {
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    if (method_exists($pdo, 'sqliteCreateFunction')) {
        $pdo->sqliteCreateFunction('NOW',
            static fn(): string => date('Y-m-d H:i:s'));
    }
    check("PDO (sqlite:memory) initialisiert", true);
} catch (\Throwable $e) {
    check("PDO init", false, $e->getMessage()); exit(1);
}

$schema = "
CREATE TABLE test_ml_ranks (id INTEGER PRIMARY KEY, name TEXT, position INTEGER);
INSERT INTO test_ml_ranks VALUES (1, 'Webadmin', 0), (2, 'Mitglied', 1);

CREATE TABLE test_ml_user (id INTEGER PRIMARY KEY, username TEXT);
INSERT INTO test_ml_user VALUES (1, 'andreas');

CREATE TABLE test_ml_mumble_host (
  id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, hostname TEXT,
  agent_url TEXT, agent_token TEXT, port_min INTEGER, port_max INTEGER,
  max_servers INTEGER, is_active INTEGER, last_seen TEXT, note TEXT, created_at TEXT
);
CREATE TABLE test_ml_mumble_server (
  id INTEGER PRIMARY KEY AUTOINCREMENT, host_id INTEGER, owner_user_id INTEGER,
  container_id TEXT, name TEXT, port INTEGER, password TEXT, max_users INTEGER,
  welcome_text TEXT, status TEXT DEFAULT 'creating', last_status TEXT,
  stats_online INTEGER DEFAULT 0, stats_uptime INTEGER DEFAULT 0,
  created_at TEXT, updated_at TEXT
);
CREATE TABLE test_ml_mumble_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, server_id INTEGER, user_id INTEGER,
  action TEXT, details TEXT, success INTEGER, created_at TEXT
);
CREATE TABLE test_ml_mumble_quota (
  id INTEGER PRIMARY KEY AUTOINCREMENT, rank_id INTEGER UNIQUE,
  max_servers INTEGER, max_users_cap INTEGER, can_create INTEGER, can_admin_all INTEGER
);
INSERT INTO test_ml_mumble_quota (rank_id,max_servers,max_users_cap,can_create,can_admin_all)
  VALUES (1,999,250,1,1), (2,1,25,1,0);
";
foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
    $pdo->exec($stmt);
}
check("Schema angelegt", true);

if (!function_exists('length')) {
    function length($item, int $len = 64, int $start = 0, string $type = 'none'): string {
        return substr((string)($item ?? ''), $start, $len);
    }
}

if (!class_exists('database')) {
    eval('class database {
        protected \PDO $pdo;
        public function __construct() { global $pdo; $this->pdo = $pdo; }
    }');
}

if (!class_exists('loginsystem')) {
    eval('class loginsystem extends database {
        public array $__user  = ["id" => 1, "rank" => 1, "username" => "andreas"];
        public array $__rules = ["mumble_view","mumble_create","mumble_admin","mumble_hosts","mumble_quota"];
        public function __construct() { parent::__construct(); }
        public function getUser(string $k): mixed { return $this->__user[$k] ?? null; }
        public function auditRight(string $r): bool { return in_array($r, $this->__rules, true); }
        public function login_session(): bool { return true; }
        public function getData(string $k): string { return "test-csrf-token"; }
    }');
}
check("Core-Klassen-Mocks", true);

section("2. Mumble-Klassen aus system/classes/");

$classDir = __DIR__ . '/../system/classes';
require_once $classDir . '/mumble_agent.php';
require_once $classDir . '/mumble.php';
check("system/classes/mumble_agent.php geladen", class_exists('mumble_agent'));
check("system/classes/mumble.php geladen",       class_exists('mumble'));

$mumble = new mumble();
check("\$mumble instanziiert", $mumble instanceof mumble);

section("3. Geschäftslogik");

$q = $mumble->getQuotaForRank(1);
check("getQuotaForRank(1): Admin",
    (int)$q['max_servers'] === 999 && (int)$q['can_admin_all'] === 1);

$q = $mumble->getQuotaForRank(2);
check("getQuotaForRank(2): Member",
    (int)$q['max_servers'] === 1 && (int)$q['can_admin_all'] === 0);

$q = $mumble->getQuotaForRank(99);
check("getQuotaForRank(99): Default", (int)$q['max_servers'] === 0);

check("canView()",         $mumble->canView()         === true);
check("canCreate()",       $mumble->canCreate()       === true);
check("canAdminAll()",     $mumble->canAdminAll()     === true);
check("canManageHosts()",  $mumble->canManageHosts()  === true);
check("canManageQuotas()", $mumble->canManageQuotas() === true);

$hid = $mumble->saveHost([
    'name' => 'proxmox-vps-01', 'hostname' => 'vps1.example.com',
    'agent_url' => 'https://vps1.example.com:8443', 'agent_token' => 'test',
    'port_min' => 64738, 'port_max' => 64748, 'max_servers' => 10,
    'is_active' => true, 'note' => '',
]);
check("saveHost() liefert ID > 0", $hid > 0);

$h = $mumble->getHost($hid);
check("getHost() findet Host", $h !== null && $h['name'] === 'proxmox-vps-01');

check("listHosts(true) findet 1 aktiven Host",
    count($mumble->listHosts(true)) === 1);

$port = $mumble->findFreePort($hid, 64738, 64748);
check("findFreePort() = 64738", $port === 64738);

check("countServersByOwner(1) = 0", $mumble->countServersByOwner(1) === 0);

$w = $mumble->getWidgetSummary();
check("getWidgetSummary() liefert own + all", isset($w['own'], $w['all']));
check("getWidgetSummary() own.total = 0",     $w['own']['total'] === 0);

$mumble->log(null, 1, 'test', 'smoke', true);
$cnt = $pdo->query("SELECT COUNT(*) FROM test_ml_mumble_log")->fetchColumn();
check("log() schreibt Audit-Eintrag", (int)$cnt === 1);

echo "  ⊘ saveQuota() (MySQL ON DUPLICATE KEY – nur Syntax-Check)\n";

section("4. Syntax-Check aller Dateien");

$files = [
    __DIR__ . '/../system/classes/mumble.php',
    __DIR__ . '/../system/classes/mumble_agent.php',
    __DIR__ . '/../templates/mumble/mumble.php',
    __DIR__ . '/../templates/mumble/mumble_new.php',
    __DIR__ . '/../templates/mumble/mumble_edit.php',
    __DIR__ . '/../templates/mumble/mumble_logs.php',
    __DIR__ . '/../templates/mumble/mumble_hosts.php',
    __DIR__ . '/../templates/mumble/mumble_quota.php',
    __DIR__ . '/../templates/mumble/widget.php',
    __DIR__ . '/../user-snippets/classes.run.user.php',
    __DIR__ . '/../user-snippets/run.user.php',
];
foreach ($files as $f) {
    $rel = str_replace(realpath(__DIR__.'/..').'/', '', realpath($f) ?: $f);
    $out = []; $rc = 0;
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
    check("Syntax OK: $rel", $rc === 0, implode(' ', $out));
}

section("Ergebnis");
echo "  $passed / $tests bestanden\n";
if (count($fails) > 0) {
    echo "\nFehler:\n".implode("\n", $fails)."\n";
    exit(1);
}
echo "\n✓ Alle Tests erfolgreich.\n";
echo "  PHP-Version: ".PHP_VERSION."\n";
exit(0);
