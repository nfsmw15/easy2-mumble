<?php
declare(strict_types=1);

/********************************************
 * Easy2-Mumble Erweiterung
 * Class: mumble
 * File: system/classes/mumble.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

class mumble extends loginsystem
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ========== Quota / Berechtigungen ========== */

    public function getQuotaForRank(int $rankId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `".Prefix."_mumble_quota` WHERE rank_id = :r LIMIT 1"
        );
        $stmt->execute([':r' => $rankId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return [
                'rank_id'       => $rankId,
                'max_servers'   => 0,
                'max_users_cap' => 25,
                'can_create'    => 0,
                'can_admin_all' => 0,
            ];
        }
        return $row;
    }

    public function canView(): bool         { return (bool)parent::auditRight('mumble_view'); }
    public function canAdminAll(): bool     { return (bool)parent::auditRight('mumble_admin'); }
    public function canManageHosts(): bool  { return (bool)parent::auditRight('mumble_hosts'); }
    public function canManageQuotas(): bool { return (bool)parent::auditRight('mumble_quota'); }

    public function canManageServer(int $serverId): bool
    {
        if ($this->canAdminAll()) return true;
        $uid = (int)parent::getUser('id');
        $srv = $this->getServer($serverId);
        if (!$srv) return false;
        if ((int)$srv['owner_user_id'] === $uid) return true;
        return $this->isMember($serverId, $uid);
    }

    public function isOwner(int $serverId): bool
    {
        if ($this->canAdminAll()) return true;
        $uid = (int)parent::getUser('id');
        $srv = $this->getServer($serverId);
        return $srv && (int)$srv['owner_user_id'] === $uid;
    }

    public function canCreate(): bool
    {
        // Admin (mumble_admin) darf immer anlegen, unabhängig von Quota
        if ($this->canAdminAll()) return true;
        if (!parent::auditRight('mumble_create')) return false;
        $quota = $this->getQuotaForRank((int)parent::getUser('rank'));
        return (bool)$quota['can_create'];
    }

    /* ========== Hosts ========== */

    public function listHosts(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM `".Prefix."_mumble_host`";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getHost(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `".Prefix."_mumble_host` WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function saveHost(array $data, ?int $id = null): int
    {
        if (!$this->canManageHosts()) {
            throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        }
        $params = [
            ':name'        => length($data['name'] ?? '', 64, 0, 'none'),
            ':hostname'    => length($data['hostname'] ?? '', 255, 0, 'none'),
            ':agent_url'   => length($data['agent_url'] ?? '', 255, 0, 'none'),
            ':agent_token' => length($data['agent_token'] ?? '', 128, 0, 'none'),
            ':port_min'    => (int)($data['port_min'] ?? 64738),
            ':port_max'    => (int)($data['port_max'] ?? 64838),
            ':max_servers' => (int)($data['max_servers'] ?? 20),
            ':is_active'   => !empty($data['is_active']) ? 1 : 0,
            ':note'        => (string)($data['note'] ?? ''),
        ];

        if ($id === null) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `".Prefix."_mumble_host`
                    (name, hostname, agent_url, agent_token, port_min, port_max,
                     max_servers, is_active, note, created_at)
                 VALUES
                    (:name, :hostname, :agent_url, :agent_token, :port_min, :port_max,
                     :max_servers, :is_active, :note, NOW())"
            );
            $stmt->execute($params);
            return (int)$this->pdo->lastInsertId();
        }

        $params[':id'] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_host`
                SET name=:name, hostname=:hostname, agent_url=:agent_url,
                    agent_token=:agent_token, port_min=:port_min, port_max=:port_max,
                    max_servers=:max_servers, is_active=:is_active, note=:note
              WHERE id=:id"
        );
        $stmt->execute($params);
        return $id;
    }

    public function deleteHost(int $id): bool
    {
        if (!$this->canManageHosts()) {
            throw new \RuntimeException('Keine Berechtigung zur Host-Verwaltung');
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `".Prefix."_mumble_server` WHERE host_id = :id"
        );
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Host hat noch aktive Server und kann nicht gelöscht werden.');
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM `".Prefix."_mumble_host` WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function touchHostLastSeen(int $hostId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_host` SET last_seen = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $hostId]);
    }

    /* ========== Server ========== */

    public function listServersByOwner(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, h.name AS host_name, h.hostname
               FROM `".Prefix."_mumble_server` s
               JOIN `".Prefix."_mumble_host`   h ON h.id = s.host_id
              WHERE s.owner_user_id = :uid
                 OR s.id IN (
                      SELECT server_id FROM `".Prefix."_mumble_server_members`
                       WHERE user_id = :uid2
                    )
              ORDER BY s.owner_user_id = :uid3 DESC, s.created_at DESC"
        );
        $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listAllServers(): array
    {
        $sql = "SELECT s.*, h.name AS host_name, h.hostname,
                       u.username AS owner_name
                  FROM `".Prefix."_mumble_server` s
                  JOIN `".Prefix."_mumble_host`   h ON h.id = s.host_id
             LEFT JOIN `".Prefix."_user`          u ON u.id = s.owner_user_id
              ORDER BY s.created_at DESC";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getServer(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, h.agent_url, h.agent_token, h.name AS host_name, h.hostname
               FROM `".Prefix."_mumble_server` s
               JOIN `".Prefix."_mumble_host`   h ON h.id = s.host_id
              WHERE s.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function countServersByOwner(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `".Prefix."_mumble_server` WHERE owner_user_id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function findFreePort(int $hostId, int $min, int $max): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT port FROM `".Prefix."_mumble_server`
              WHERE host_id = :h AND port BETWEEN :a AND :b"
        );
        $stmt->execute([':h' => $hostId, ':a' => $min, ':b' => $max]);
        $used = array_flip(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
        for ($p = $min; $p <= $max; $p++) {
            if (!isset($used[$p])) return $p;
        }
        return null;
    }

    public function createServer(array $data): int
    {
        if (!$this->canCreate()) {
            throw new \RuntimeException('Keine Berechtigung zum Erstellen von Servern');
        }

        $uid   = (int)parent::getUser('id');
        $rank  = (int)parent::getUser('rank');
        $quota = $this->getQuotaForRank($rank);

        if (!$this->canAdminAll()) {
            if ($this->countServersByOwner($uid) >= (int)$quota['max_servers']) {
                throw new \RuntimeException('Server-Kontingent für deinen Rang erreicht.');
            }
        }

        $hostId = (int)($data['host_id'] ?? 0);
        $host   = $this->getHost($hostId);
        if (!$host || !$host['is_active']) {
            throw new \RuntimeException('Host ist nicht verfügbar.');
        }

        $rawMax   = (int)($data['max_users'] ?? 10);
        $maxUsers = $this->canAdminAll()
            ? max(1, $rawMax)
            : max(1, min($rawMax, (int)$quota['max_users_cap']));
        $name = trim((string)preg_replace('/[^A-Za-z0-9 _\-.]/u', '', (string)($data['name'] ?? '')));
        if ($name === '') {
            throw new \RuntimeException('Ungültiger Server-Name.');
        }

        $port = $this->findFreePort($hostId, (int)$host['port_min'], (int)$host['port_max']);
        if ($port === null) {
            throw new \RuntimeException('Kein freier Port auf diesem Host verfügbar.');
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO `".Prefix."_mumble_server`
                (host_id, owner_user_id, name, port, password, max_users,
                 welcome_text, status, created_at)
             VALUES (:h, :uid, :n, :p, :pw, :m, :w, 'creating', NOW())"
        );
        $stmt->execute([
            ':h' => $hostId, ':uid' => $uid, ':n' => $name, ':p' => $port,
            ':pw' => (string)($data['password'] ?? ''), ':m' => $maxUsers,
            ':w' => (string)($data['welcome_text'] ?? ''),
        ]);
        $serverId = (int)$this->pdo->lastInsertId();

        $agent = new mumble_agent($host['agent_url'], $host['agent_token']);
        $res = $agent->createServer([
            'name'         => $name,
            'port'         => $port,
            'password'     => (string)($data['password'] ?? ''),
            'max_users'    => $maxUsers,
            'welcome_text' => (string)($data['welcome_text'] ?? ''),
            'external_id'  => $serverId,
        ]);

        if (!$res['ok']) {
            $this->setStatus($serverId, 'error');
            $this->log($serverId, $uid, 'create', 'agent: '.(string)$res['error'], false);
            throw new \RuntimeException('Agent-Fehler: '.$res['error']);
        }

        $cid = (string)($res['data']['container_id'] ?? '');
        $supw = (string)($res['data']['superuser_password'] ?? '');
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server`
                SET container_id = :c, status = 'running',
                    superuser_password = :supw,
                    updated_at = NOW(), last_status = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':c' => $cid, ':supw' => $supw ?: null, ':id' => $serverId]);

        $this->touchHostLastSeen($hostId);
        $this->log($serverId, $uid, 'create', 'port='.$port);
        return $serverId;
    }

    public function setStatus(int $serverId, string $status): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server`
                SET status = :s, last_status = NOW(), updated_at = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':s' => $status, ':id' => $serverId]);
    }

    public function updateStats(int $serverId, int $online, int $uptime): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server`
                SET stats_online = :o, stats_uptime = :u, last_status = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':o' => $online, ':u' => $uptime, ':id' => $serverId]);
    }

    public function performAction(int $serverId, string $action): array
    {
        if (!in_array($action, ['start','stop','restart','delete'], true)) {
            return ['ok' => false, 'error' => 'Ungültige Aktion'];
        }
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if ($action === 'delete') {
            if (!$this->canAdminAll()) {
                return ['ok' => false, 'error' => 'Nur Administratoren können Server löschen'];
            }
        } elseif (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $cid = (string)$srv['container_id'];

        $res = match ($action) {
            'start'   => $agent->startServer($cid),
            'stop'    => $agent->stopServer($cid),
            'restart' => $agent->restartServer($cid),
            'delete'  => $agent->deleteServer($cid),
        };

        if ($res['ok']) {
            match ($action) {
                'start'   => $this->setStatus($serverId, 'running'),
                'stop'    => $this->setStatus($serverId, 'stopped'),
                'restart' => $this->setStatus($serverId, 'running'),
                'delete'  => $this->deleteServerRow($serverId),
            };
        }

        $this->log($serverId, $uid, $action,
            $res['ok'] ? 'ok' : (string)$res['error'], (bool)$res['ok']);
        return $res;
    }

    private function deleteServerRow(int $serverId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `".Prefix."_mumble_server` WHERE id = :id"
        );
        $stmt->execute([':id' => $serverId]);
    }

    public function refreshStats(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->getStats((string)$srv['container_id']);

        if ($res['ok'] && isset($res['data']['online'], $res['data']['uptime'])) {
            $this->updateStats($serverId,
                (int)$res['data']['online'],
                (int)$res['data']['uptime']);
        }
        return $res;
    }

    public function fetchLogs(int $serverId, int $tail = 300): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->getLogs((string)$srv['container_id'], $tail);
    }

    /* ========== Quota-Management ========== */

    public function saveQuota(int $rankId, int $maxServers, int $maxUsersCap,
                              bool $canCreate, bool $canAdminAll): void
    {
        if (!$this->canManageQuotas()) {
            throw new \RuntimeException('Keine Berechtigung');
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO `".Prefix."_mumble_quota`
                (rank_id, max_servers, max_users_cap, can_create, can_admin_all)
             VALUES (:r, :ms, :mu, :cc, :ca)
             ON DUPLICATE KEY UPDATE
                max_servers=VALUES(max_servers),
                max_users_cap=VALUES(max_users_cap),
                can_create=VALUES(can_create),
                can_admin_all=VALUES(can_admin_all)"
        );
        $stmt->execute([
            ':r' => $rankId, ':ms' => $maxServers, ':mu' => $maxUsersCap,
            ':cc' => $canCreate ? 1 : 0, ':ca' => $canAdminAll ? 1 : 0,
        ]);
    }

    public function getAllRanks(): array
    {
        return $this->pdo->query(
            "SELECT id, title AS name FROM `".Prefix."_ranks` ORDER BY pos ASC"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /* ========== Audit-Log ========== */

    public function log(?int $serverId, int $userId, string $action,
                        string $details = '', bool $success = true): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `".Prefix."_mumble_log`
                    (server_id, user_id, action, details, success, created_at)
                 VALUES (:s, :u, :a, :d, :ok, NOW())"
            );
            $stmt->execute([
                ':s' => $serverId, ':u' => $userId, ':a' => $action,
                ':d' => $details, ':ok' => $success ? 1 : 0,
            ]);
        } catch (\PDOException $e) {
            // MySQL gone away nach langem Agent-Call — Verbindung
            // ist abgelaufen. Log-Eintrag geht verloren, aber der
            // eigentliche Vorgang (Server-Start, Config-Update etc.)
            // war erfolgreich und darf nicht abbrechen.
            error_log('Easy2-Mumble: log() fehlgeschlagen: '.$e->getMessage());
        }
    }

    /* ========== Widget-Daten ========== */

    public function getWidgetSummary(): array
    {
        $uid = (int)parent::getUser('id');

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) AS running,
                    SUM(stats_online) AS online
               FROM `".Prefix."_mumble_server`
              WHERE owner_user_id = :uid"
        );
        $stmt->execute([':uid' => $uid]);
        $own = $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['total'=>0,'running'=>0,'online'=>0];

        $all = null;
        if ($this->canAdminAll()) {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) AS running,
                        SUM(stats_online) AS online
                   FROM `".Prefix."_mumble_server`"
            );
            $all = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        return [
            'own' => [
                'total'   => (int)$own['total'],
                'running' => (int)$own['running'],
                'online'  => (int)$own['online'],
            ],
            'all' => $all ? [
                'total'   => (int)$all['total'],
                'running' => (int)$all['running'],
                'online'  => (int)$all['online'],
            ] : null,
        ];
    }

    /* ========== SuperUser-Passwort ========== */

    public function getSuperUserPassword(int $serverId): ?string
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return null;

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) return null;

        // Erst lokal in DB schauen
        if (!empty($srv['superuser_password'])) {
            return (string)$srv['superuser_password'];
        }

        // Sonst vom Agent holen
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->getSuperUser((string)$srv['container_id']);
        if ($res['ok'] && !empty($res['data']['superuser_password'])) {
            $pw = (string)$res['data']['superuser_password'];
            $this->saveSuperUserPassword($serverId, $pw);
            return $pw;
        }

        return null;
    }

    public function saveSuperUserPassword(int $serverId, string $pw): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server` SET superuser_password = :pw WHERE id = :id"
        );
        $stmt->execute([':pw' => $pw, ':id' => $serverId]);
    }

    public function resetSuperUserPassword(int $serverId, string $newPw = ''): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->resetSuperUser((string)$srv['container_id'], $newPw);
        if ($res['ok'] && !empty($res['data']['superuser_password'])) {
            $pw = (string)$res['data']['superuser_password'];
            $this->saveSuperUserPassword($serverId, $pw);
            $this->log($serverId, $uid, 'superuser_reset', 'ok');
            return ['ok' => true, 'password' => $pw];
        }

        return ['ok' => false, 'error' => $res['error'] ?? 'Agent-Fehler'];
    }

    /* ========== Server-Config (INI) ========== */

    public function getServerConfig(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->getConfig((string)$srv['container_id']);
    }

    public function saveServerConfig(int $serverId, string $content): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->saveConfig((string)$srv['container_id'], $content);

        // Agent gibt bei Recreate eine neue Container-ID zurueck
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $stmt = $this->pdo->prepare(
                "UPDATE `".Prefix."_mumble_server`
                    SET container_id = :cid, updated_at = NOW()
                  WHERE id = :id"
            );
            $stmt->execute([
                ':cid' => (string)$res['data']['container_id'],
                ':id'  => $serverId,
            ]);
        }

        if ($res['ok']) {
            $this->log($serverId, $uid, 'config_update', 'ok');
        }
        return $res;
    }

    /* ========== Server-Eckdaten ändern ========== */

    public function updateMumbleSettingsLive(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        if (!$this->canManageServer($serverId)) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $payload = [];
        if (isset($data['name']))         $payload['name']         = substr((string)$data['name'], 0, 64);
        if (isset($data['password']))     $payload['password']     = (string)$data['password'];
        if (isset($data['max_users']))    $payload['max_users']    = max(1, (int)$data['max_users']);
        if (isset($data['welcome_text'])) $payload['welcome_text'] = (string)$data['welcome_text'];

        $res = $agent->updateSettingsLive((string)$srv['container_id'], $payload);
        if (!$res['ok']) return $res;

        // DB aktualisieren
        $fields = ['updated_at = NOW()'];
        $params = [':id' => $serverId];
        if (isset($payload['name']))         { $fields[] = 'name = :name';         $params[':name'] = $payload['name']; }
        if (isset($payload['password']))     { $fields[] = 'password = :pw';       $params[':pw']   = $payload['password']; }
        if (isset($payload['max_users']))    { $fields[] = 'max_users = :mu';      $params[':mu']   = $payload['max_users']; }
        if (isset($payload['welcome_text'])) { $fields[] = 'welcome_text = :wt';   $params[':wt']   = $payload['welcome_text']; }
        $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server` SET ".implode(', ', $fields)." WHERE id = :id"
        )->execute($params);

        return ['ok' => true];
    }

    public function updateServerSettings(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];

        $uid = (int)parent::getUser('id');
        if (!$this->canAdminAll() && (int)$srv['owner_user_id'] !== $uid) {
            return ['ok' => false, 'error' => 'Keine Berechtigung'];
        }

        // DB-Update
        $fields = [];
        $params = [':id' => $serverId];
        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = length($data['name'], 64, 0, 'none');
        }
        if (isset($data['welcome_text'])) {
            $fields[] = 'welcome_text = :wt';
            $params[':wt'] = (string)$data['welcome_text'];
        }
        if (isset($data['max_users'])) {
            $fields[] = 'max_users = :mu';
            $params[':mu'] = max(1, (int)$data['max_users']);
        }
        if (array_key_exists('password', $data)) {
            $fields[] = 'password = :pw';
            $params[':pw'] = (string)$data['password'];
        }
        if (!empty($fields)) {
            $fields[] = 'updated_at = NOW()';
            $stmt = $this->pdo->prepare(
                "UPDATE `".Prefix."_mumble_server` SET ".implode(', ', $fields)." WHERE id = :id"
            );
            $stmt->execute($params);
        }

        // Agent-Update (Container-Recreate mit neuen ENV-Variablen)
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $agentData = [];
        if (isset($data['name']))         $agentData['name'] = $data['name'];
        if (isset($data['welcome_text'])) $agentData['welcome_text'] = $data['welcome_text'];
        if (isset($data['max_users']))    $agentData['max_users'] = (int)$data['max_users'];
        if (array_key_exists('password', $data)) $agentData['password'] = $data['password'];

        $res = $agent->updateConfig((string)$srv['container_id'], $agentData);

        // Agent gibt bei Recreate eine neue Container-ID zurueck
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            try {
                $stmt = $this->pdo->prepare(
                    "UPDATE `".Prefix."_mumble_server`
                        SET container_id = :cid, updated_at = NOW()
                      WHERE id = :id"
                );
                $stmt->execute([
                    ':cid' => (string)$res['data']['container_id'],
                    ':id'  => $serverId,
                ]);
            } catch (\PDOException $e) {
                // MySQL gone away — neuen Versuch mit frischer Verbindung
                error_log('Easy2-Mumble: container_id update fehlgeschlagen (1. Versuch): '.$e->getMessage());
                try {
                    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
                    $pdo2 = new \PDO($dsn, DB_USER, DB_PASS, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $stmt = $pdo2->prepare(
                        "UPDATE `".Prefix."_mumble_server`
                            SET container_id = :cid, updated_at = NOW()
                          WHERE id = :id"
                    );
                    $stmt->execute([
                        ':cid' => (string)$res['data']['container_id'],
                        ':id'  => $serverId,
                    ]);
                    error_log('Easy2-Mumble: container_id update OK (2. Versuch)');
                } catch (\PDOException $e2) {
                    error_log('Easy2-Mumble: container_id update fehlgeschlagen (2. Versuch): '.$e2->getMessage());
                }
            }
        }

        $this->log($serverId, $uid, 'settings_update',
            $res['ok'] ? 'ok' : (string)($res['error'] ?? 'unknown'), (bool)$res['ok']);
        return $res;
    }

    /* ========== Channel-Viewer ========== */

    public function getViewer(int $serverId): ?array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return null;
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->getViewer((string)$srv['container_id']);
        return $res['ok'] ? $res['data'] : null;
    }

    public function getWidgetSettings(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, widget_token, widget_public, widget_refresh
               FROM `".Prefix."_mumble_server` WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $serverId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveWidgetSettings(int $serverId, bool $public, int $refresh): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server`
                SET widget_public = :p, widget_refresh = :r, updated_at = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':p' => $public ? 1 : 0, ':r' => max(0, $refresh), ':id' => $serverId]);
    }

    public function generateWidgetToken(int $serverId): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server` SET widget_token = :t WHERE id = :id"
        );
        $stmt->execute([':t' => $token, ':id' => $serverId]);
        return $token;
    }

    public function disableWidget(int $serverId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `".Prefix."_mumble_server` SET widget_token = NULL WHERE id = :id"
        );
        $stmt->execute([':id' => $serverId]);
    }

    public function getServerByWidget(string $token): ?array
    {
        if ($token === '') return null;
        $stmt = $this->pdo->prepare(
            "SELECT s.*, h.agent_url, h.agent_token, h.hostname
               FROM `".Prefix."_mumble_server` s
               JOIN `".Prefix."_mumble_host` h ON h.id = s.host_id
              WHERE s.widget_token = :t AND s.status = 'running' LIMIT 1"
        );
        $stmt->execute([':t' => $token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getPublicServer(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, h.agent_url, h.agent_token, h.hostname
               FROM `".Prefix."_mumble_server` s
               JOIN `".Prefix."_mumble_host` h ON h.id = s.host_id
              WHERE s.id = :id AND s.widget_public = 1 AND s.status = 'running' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /* ========== Server-Members ========== */

    public function getMembers(int $serverId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.user_id, m.added_at, u.username
               FROM `".Prefix."_mumble_server_members` m
               JOIN `".Prefix."_user` u ON u.id = m.user_id
              WHERE m.server_id = :sid ORDER BY u.username"
        );
        $stmt->execute([':sid' => $serverId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function isMember(int $serverId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM `".Prefix."_mumble_server_members`
              WHERE server_id = :sid AND user_id = :uid LIMIT 1"
        );
        $stmt->execute([':sid' => $serverId, ':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function addMember(int $serverId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO `".Prefix."_mumble_server_members`
             (server_id, user_id, added_at) VALUES (:sid, :uid, NOW())"
        );
        $stmt->execute([':sid' => $serverId, ':uid' => $userId]);
    }

    public function removeMember(int $serverId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `".Prefix."_mumble_server_members`
              WHERE server_id = :sid AND user_id = :uid"
        );
        $stmt->execute([':sid' => $serverId, ':uid' => $userId]);
    }

    public function searchUsers(string $term, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username FROM `".Prefix."_user`
              WHERE username LIKE :t AND active = 1
              ORDER BY username LIMIT :l"
        );
        $stmt->execute([':t' => '%'.str_replace(['%','_'],['\\%','\\_'],$term).'%', ':l' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ========== Server-Einstellungen (Config-Tabs) ========== */

    public function getServerSettings(int $serverId): ?array
    {
        $srv = $this->getServer($serverId);
        if (!$srv || !$this->canManageServer($serverId)) return null;
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->getSettings((string)$srv['container_id']);
        return $res['ok'] ? ($res['data']['settings'] ?? []) : [];
    }

    public function saveServerSettings(int $serverId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid = (int)parent::getUser('id');
        // max_users nur Owner/Admin
        if (isset($data['max_users']) && !$this->isOwner($serverId)) {
            unset($data['max_users']);
        }
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->saveSettings((string)$srv['container_id'], $data);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'config_update', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    /* ========== Zertifikat ========== */

    public function setCertificate(int $serverId, string $cert, string $key): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->setCertificate((string)$srv['container_id'], $cert, $key);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'cert_upload', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    public function removeCertificate(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->removeCertificate((string)$srv['container_id']);
        if ($res['ok'] && !empty($res['data']['container_id'])) {
            $this->updateContainerId($serverId, (string)$res['data']['container_id']);
        }
        $this->log($serverId, $uid, 'cert_remove', $res['ok'] ? 'ok' : ($res['error'] ?? ''), (bool)$res['ok']);
        return $res;
    }

    private function updateContainerId(int $serverId, string $newCid): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `".Prefix."_mumble_server`
                    SET container_id = :c, updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':c' => $newCid, ':id' => $serverId]);
        } catch (\Throwable) {}
    }

    /* ========== Live-User-Verwaltung (ICE) ========== */

    public function getLiveUsers(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->getLiveUsers((string)$srv['container_id']);
    }

    public function kickMumbleUser(int $serverId, int $session, string $reason = ''): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->kickUser((string)$srv['container_id'], $session, $reason);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'kick', "session={$session} reason={$reason}");
        }
        return $res;
    }

    public function muteMumbleUser(int $serverId, int $session, bool $mute): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->updateUser((string)$srv['container_id'], $session, ['mute' => $mute]);
    }

    public function moveMumbleUser(int $serverId, int $session, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->updateUser((string)$srv['container_id'], $session, ['channel' => $channelId]);
    }

    /* ========== Channel-Verwaltung (ICE) ========== */

    public function getMumbleChannels(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->getChannels((string)$srv['container_id']);
    }

    public function addMumbleChannel(int $serverId, string $name, int $parent = 0): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->addChannel((string)$srv['container_id'], $name, $parent);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'channel_add', "name={$name} parent={$parent}");
        }
        return $res;
    }

    public function updateMumbleChannel(int $serverId, int $channelId, array $data): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->updateChannel((string)$srv['container_id'], $channelId, $data);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'channel_update', "id={$channelId}");
        }
        return $res;
    }

    public function removeMumbleChannel(int $serverId, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->removeChannel((string)$srv['container_id'], $channelId);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'channel_remove', "id={$channelId}");
        }
        return $res;
    }

    /* ========== Ban-Verwaltung (ICE) ========== */

    public function getMumbleBans(int $serverId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        return $agent->getBans((string)$srv['container_id']);
    }

    public function setMumbleBans(int $serverId, array $bans): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid   = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res   = $agent->setBans((string)$srv['container_id'], $bans);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'bans_update', 'count='.count($bans));
        }
        return $res;
    }

    /* ========== ACL-Verwaltung ========== */

    public function getChannelAcl(int $serverId, int $channelId): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->getChannelAcl((string)$srv['container_id'], $channelId);
        return $res;
    }

    public function setChannelAcl(int $serverId, int $channelId, bool $inheritAcl, array $aclEntries, array $groups): array
    {
        $srv = $this->getServer($serverId);
        if (!$srv) return ['ok' => false, 'error' => 'Server nicht gefunden'];
        if (!$this->canManageServer($serverId)) return ['ok' => false, 'error' => 'Keine Berechtigung'];
        $uid = (int)parent::getUser('id');
        $agent = new mumble_agent($srv['agent_url'], $srv['agent_token']);
        $res = $agent->setChannelAcl((string)$srv['container_id'], [
            'channel_id'  => $channelId,
            'inherit_acl' => $inheritAcl,
            'acl'         => $aclEntries,
            'groups'      => $groups,
        ]);
        if ($res['ok']) {
            $this->log($serverId, $uid, 'acl_update', "channel={$channelId}");
        }
        return $res;
    }
}
