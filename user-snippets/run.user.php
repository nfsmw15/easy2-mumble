<?php
/********************************************
 * Easy2-Mumble Erweiterung
 * Snippet für: system/run.user.php
 *
 * Block in deine bestehende run.user.php einfügen.
 * POST-Aktionen, AJAX-Endpoints, Erfolgsmeldungen für $p='mumble*'.
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

/* --- Öffentlich: Widget-Embed JSON (kein Login, Token-Auth) --- */
if ($p === 'mumble_widget' && $c === 'embed_data') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    $token = trim((string)($_GET['token'] ?? ''));
    if (empty($token)) { echo json_encode(['ok' => false, 'error' => 'Token fehlt']); exit; }
    $srv = $mumble->getServerByWidget($token);
    if (!$srv) { echo json_encode(['ok' => false, 'error' => 'Widget nicht verfügbar']); exit; }
    $agent = new mumble_agent((string)$srv['agent_url'], (string)$srv['agent_token']);
    $res = $agent->getViewer((string)$srv['container_id']);
    if (!$res['ok'] || empty($res['data']['channels'])) {
        echo json_encode(['ok' => false, 'error' => 'Server nicht erreichbar']); exit;
    }
    $ch = $res['data']['channels'];
    $ch['name'] = (string)$srv['name'];
    echo json_encode(['ok' => true, 'server' => (string)$srv['name'], 'channels' => $ch]);
    exit;
}

if (strpos((string)$p, 'mumble') === 0 && $loginsystem->login_session()) {

    $mb_csrf_ok = true;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $posted   = (string)($_POST['csrf'] ?? '');
        $expected = (string)($_SESSION['ml_csrfToken'] ?? '');
        $mb_csrf_ok = ($posted !== '' && hash_equals($expected, $posted));
    }

    /* --- AJAX: Dashboard-Daten --- */
    if ($p === 'mumble_dashboard' && $c === 'dashboard_data') {
        header('Content-Type: application/json');
        echo json_encode($mumble->getDashboardData());
        exit;
    }

    /* --- Server-Aktionen --- */
    if ($p === 'mumble' && in_array($c, ['start','stop','restart','delete'], true)
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_POST['id'] ?? 0);
            if ($sid > 0) {
                $res = $mumble->performAction($sid, $c);
                if ($res['ok']) {
                    header('Location: ?p=mumble&h=action_ok');
                    exit;
                }
                $error .= '<div class="alert alert-danger">Fehler: '
                       .  htmlspecialchars((string)$res['error']).'</div>';
            }
        }
    }

    /* --- Server anlegen --- */
    if ($p === 'mumble_new' && $c === 'create'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            try {
                $mumble->createServer([
                    'host_id'      => (int)($_POST['host_id'] ?? 0),
                    'name'         => (string)($_POST['name'] ?? ''),
                    'password'     => (string)($_POST['password'] ?? ''),
                    'max_users'    => (int)($_POST['max_users'] ?? 10),
                    'welcome_text' => (string)($_POST['welcome_text'] ?? ''),
                ]);
                header('Location: ?p=mumble&h=server_created');
                exit;
            } catch (\Throwable $e) {
                $error .= '<div class="alert alert-danger">'
                       .  htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    }

    /* --- Host-Verwaltung --- */
    if ($p === 'mumble_hosts' && !empty($c)
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            try {
                if ($c === 'save') {
                    $data = [
                        'name'        => $_POST['name']        ?? '',
                        'hostname'    => $_POST['hostname']    ?? '',
                        'agent_url'   => $_POST['agent_url']   ?? '',
                        'agent_token' => $_POST['agent_token'] ?? '',
                        'port_min'    => (int)($_POST['port_min']    ?? 64738),
                        'port_max'    => (int)($_POST['port_max']    ?? 64838),
                        'max_servers' => (int)($_POST['max_servers'] ?? 20),
                        'is_active'   => !empty($_POST['is_active']),
                        'note'        => $_POST['note']        ?? '',
                    ];
                    $hid = isset($_POST['id']) && (int)$_POST['id'] > 0 ? (int)$_POST['id'] : null;
                    $mumble->saveHost($data, $hid);
                    header('Location: ?p=mumble_hosts&h=host_saved');
                    exit;
                } elseif ($c === 'delete') {
                    $mumble->deleteHost((int)($_POST['id'] ?? 0));
                    header('Location: ?p=mumble_hosts&h=host_deleted');
                    exit;
                }
            } catch (\Throwable $e) {
                $error .= '<div class="alert alert-danger">'
                       .  htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    }

    /* --- Quota-Verwaltung --- */
    if ($p === 'mumble_quota' && $c === 'save'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            try {
                $rows = $_POST['q'] ?? [];
                if (is_array($rows)) {
                    foreach ($rows as $rid => $r) {
                        $mumble->saveQuota(
                            (int)$rid,
                            (int)($r['max_servers']   ?? 0),
                            (int)($r['max_users_cap'] ?? 25),
                            !empty($r['can_create']),
                            !empty($r['can_admin_all'])
                        );
                    }
                }
                header('Location: ?p=mumble_quota&h=quota_saved');
                exit;
            } catch (\Throwable $e) {
                $error .= '<div class="alert alert-danger">'
                       .  htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    }

    /* --- SuperUser-Passwort zurücksetzen --- */
    if ($p === 'mumble_edit' && $c === 'superuser_reset'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $res = $mumble->resetSuperUserPassword($sid, (string)($_POST['new_supw'] ?? ''));
            if ($res['ok']) {
                header('Location: ?p=mumble_edit&id='.$sid.'&h=supw_reset');
                exit;
            }
            $error .= '<div class="alert alert-danger">Fehler: '
                   .  htmlspecialchars((string)$res['error']).'</div>';
        }
    }

    /* --- Einstellungen via ICE live speichern (kein Neustart) --- */
    if ($p === 'mumble_edit' && $c === 'settings_save'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        if (!hash_equals((string)($_SESSION['ml_csrfToken'] ?? ''), (string)($raw['csrf'] ?? ''))) {
            echo json_encode(['ok' => false, 'error' => 'CSRF-Token ungültig']); exit;
        }
        $sid = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']); exit; }
        $data = [
            'name'         => (string)($raw['name'] ?? ''),
            'password'     => (string)($raw['password'] ?? ''),
            'max_users'    => (int)($raw['max_users'] ?? 10),
            'welcome_text' => (string)($raw['welcome_text'] ?? ''),
        ];
        echo json_encode($mumble->updateMumbleSettingsLive($sid, $data));
        exit;
    }

    /* --- Server-Einstellungen bearbeiten --- */
    if ($p === 'mumble_edit' && $c === 'update_settings'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $data = [
                'name'         => (string)($_POST['name'] ?? ''),
                'password'     => (string)($_POST['password'] ?? ''),
                'max_users'    => (int)($_POST['max_users'] ?? 10),
                'welcome_text' => (string)($_POST['welcome_text'] ?? ''),
            ];
            $res = $mumble->updateServerSettings($sid, $data);
            if ($res['ok']) {
                header('Location: ?p=mumble_edit&id='.$sid.'&h=settings_saved');
                exit;
            }
            $error .= '<div class="alert alert-danger">Fehler: '
                   .  htmlspecialchars((string)($res['error'] ?? 'Unbekannt')).'</div>';
        }
    }

    /* --- Server-Config (INI) speichern --- */
    if ($p === 'mumble_config' && $c === 'save_config'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $content = (string)($_POST['config_content'] ?? '');
            $res = $mumble->saveServerConfig($sid, $content);
            if ($res['ok']) {
                header('Location: ?p=mumble_config&id='.$sid.'&h=config_saved');
                exit;
            }
            $error .= '<div class="alert alert-danger">Fehler: '
                   .  htmlspecialchars((string)($res['error'] ?? 'Unbekannt')).'</div>';
        }
    }

    /* --- Mumble-Config-Einstellungen speichern --- */
    if ($p === 'mumble_config' && $c === 'save_settings'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $checkboxes = ['allowhtml','rememberchannel','certrequired','suggestpositional','suggestpushtotalk','sendversion','bonjour'];
            $data = [];
            foreach (['bandwidth','timeout','opusthreshold','textmessagelength','imagemessagelength',
                      'usersperchannel','defaultchannel','autoban_attempts','autoban_timeframe','autoban_time'] as $k) {
                if (isset($_POST[$k])) { $data[$k] = (int)$_POST[$k]; }
            }
            foreach (['register_name','register_password','register_url','register_hostname','register_location','suggestversion'] as $k) {
                $data[$k] = (string)($_POST[$k] ?? '');
            }
            foreach ($checkboxes as $k) {
                $data[$k] = !empty($_POST[$k]);
            }
            $res = $mumble->saveServerSettings($sid, $data);
            if ($res['ok']) {
                header('Location: ?p=mumble_config&id='.$sid.'&h=settings_saved');
                exit;
            }
            $error .= '<div class="alert alert-danger">Fehler: '
                   .  htmlspecialchars((string)($res['error'] ?? 'Unbekannt')).'</div>';
        }
    }

    /* --- Zertifikat hochladen --- */
    if ($p === 'mumble_config' && $c === 'cert_upload'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid  = (int)($_GET['id'] ?? 0);
            $cert = trim((string)($_POST['cert_pem'] ?? ''));
            $key  = trim((string)($_POST['key_pem'] ?? ''));
            if ($cert === '' || $key === '') {
                $error .= '<div class="alert alert-danger">Zertifikat und Schlüssel dürfen nicht leer sein.</div>';
            } else {
                $res = $mumble->setCertificate($sid, $cert, $key);
                if ($res['ok']) {
                    header('Location: ?p=mumble_config&id='.$sid.'&h=cert_uploaded');
                    exit;
                }
                $error .= '<div class="alert alert-danger">Fehler: '
                       .  htmlspecialchars((string)($res['error'] ?? 'Unbekannt')).'</div>';
            }
        }
    }

    /* --- Zertifikat entfernen --- */
    if ($p === 'mumble_config' && $c === 'cert_remove'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $res = $mumble->removeCertificate($sid);
            if ($res['ok']) {
                header('Location: ?p=mumble_config&id='.$sid.'&h=cert_removed');
                exit;
            }
            $error .= '<div class="alert alert-danger">Fehler: '
                   .  htmlspecialchars((string)($res['error'] ?? 'Unbekannt')).'</div>';
        }
    }

    /* --- Mitglied hinzufügen --- */
    if ($p === 'mumble_edit' && $c === 'member_add'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid > 0) {
                $mumble->addMember($sid, $uid);
            }
            header('Location: ?p=mumble_edit&id='.$sid.'&h=member_added');
            exit;
        }
    }

    /* --- Mitglied entfernen --- */
    if ($p === 'mumble_edit' && $c === 'member_remove'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid = (int)($_GET['id'] ?? 0);
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid > 0) {
                $mumble->removeMember($sid, $uid);
            }
            header('Location: ?p=mumble_edit&id='.$sid.'&h=member_removed');
            exit;
        }
    }

    /* --- AJAX: User-Suche (für Mitglieder-Autocomplete) --- */
    if ($p === 'mumble_edit' && $c === 'user_search') {
        header('Content-Type: application/json');
        $q   = (string)($_GET['q'] ?? '');
        $sid = (int)($_GET['id'] ?? 0);
        if (strlen($q) < 2 || !$mumble->isOwner($sid)) {
            echo json_encode([]);
        } else {
            echo json_encode($mumble->searchUsers($q));
        }
        exit;
    }

    /* --- AJAX: Live-Stats --- */
    if ($p === 'mumble' && $c === 'stats') {
        header('Content-Type: application/json');
        echo json_encode($mumble->refreshStats((int)($_GET['id'] ?? 0)));
        exit;
    }

    /* --- AJAX: Live-User --- */
    if ($p === 'mumble_edit' && $c === 'live_users') {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $res = $mumble->getLiveUsers($sid);
        echo json_encode($res['ok'] ? ($res['data'] ?? $res) : $res);
        exit;
    }

    /* --- AJAX: Kick --- */
    if ($p === 'mumble_edit' && $c === 'kick_user'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw     = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $posted  = (string)($raw['csrf'] ?? '');
        $expected= (string)($_SESSION['ml_csrfToken'] ?? '');
        if (!hash_equals($expected, $posted)) { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid     = (int)($_GET['id'] ?? 0);
        $session = (int)($raw['session'] ?? 0);
        $reason  = (string)($raw['reason'] ?? '');
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        echo json_encode($mumble->kickMumbleUser($sid, $session, $reason));
        exit;
    }

    /* --- AJAX: User stumm/verschieben --- */
    if ($p === 'mumble_edit' && $c === 'update_user'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw     = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        $posted  = (string)($raw['csrf'] ?? '');
        $expected= (string)($_SESSION['ml_csrfToken'] ?? '');
        if (!hash_equals($expected, $posted)) { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid     = (int)($_GET['id'] ?? 0);
        $session = (int)($raw['session'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $data = [];
        if (isset($raw['mute']))    $data['mute']    = (bool)$raw['mute'];
        if (isset($raw['channel'])) $data['channel'] = (int)$raw['channel'];
        echo json_encode(
            isset($data['mute'])    ? $mumble->muteMumbleUser($sid, $session, $data['mute']) :
           (isset($data['channel']) ? $mumble->moveMumbleUser($sid, $session, $data['channel']) :
            ['ok'=>false,'error'=>'Keine Aktion'])
        );
        exit;
    }

    /* --- AJAX: Channel-Daten --- */
    if ($p === 'mumble_channels' && $c === 'channels_data') {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $res = $mumble->getMumbleChannels($sid);
        echo json_encode($res['ok'] ? ($res['data'] ?? $res) : $res);
        exit;
    }

    /* --- Channel hinzufügen --- */
    if ($p === 'mumble_channels' && $c === 'channel_add'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw  = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        if (!hash_equals((string)($_SESSION['ml_csrfToken'] ?? ''), (string)($raw['csrf'] ?? '')))
            { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid  = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $res  = $mumble->addMumbleChannel($sid, (string)($raw['name'] ?? ''), (int)($raw['parent'] ?? 0));
        echo json_encode($res['ok'] ? ['ok'=>true,'channel_id'=>$res['data']['channel_id']??null] : $res);
        exit;
    }

    /* --- Channel aktualisieren --- */
    if ($p === 'mumble_channels' && $c === 'channel_update'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw  = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        if (!hash_equals((string)($_SESSION['ml_csrfToken'] ?? ''), (string)($raw['csrf'] ?? '')))
            { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid  = (int)($_GET['id'] ?? 0);
        $cid  = (int)($raw['channel_id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $data = [];
        if (isset($raw['name']))        $data['name']        = (string)$raw['name'];
        if (isset($raw['description'])) $data['description'] = (string)$raw['description'];
        if (isset($raw['position']))    $data['position']    = (int)$raw['position'];
        echo json_encode($mumble->updateMumbleChannel($sid, $cid, $data));
        exit;
    }

    /* --- Channel löschen --- */
    if ($p === 'mumble_channels' && $c === 'channel_delete'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw  = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        if (!hash_equals((string)($_SESSION['ml_csrfToken'] ?? ''), (string)($raw['csrf'] ?? '')))
            { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid  = (int)($_GET['id'] ?? 0);
        $cid  = (int)($raw['channel_id'] ?? 0);
        if (!$mumble->canManageServer($sid) || $cid === 0)
            { echo json_encode(['ok'=>false,'error'=>'Nicht erlaubt']); exit; }
        echo json_encode($mumble->removeMumbleChannel($sid, $cid));
        exit;
    }

    /* --- AJAX: Bans laden --- */
    if ($p === 'mumble_bans' && $c === 'bans_data') {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $res = $mumble->getMumbleBans($sid);
        echo json_encode($res['ok'] ? ($res['data'] ?? $res) : $res);
        exit;
    }

    /* --- Bans speichern --- */
    if ($p === 'mumble_bans' && $c === 'bans_save'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $raw  = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
        if (!hash_equals((string)($_SESSION['ml_csrfToken'] ?? ''), (string)($raw['csrf'] ?? '')))
            { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
        $sid  = (int)($_GET['id'] ?? 0);
        if (!$mumble->canManageServer($sid)) { echo json_encode(['ok'=>false,'error'=>'Keine Berechtigung']); exit; }
        $bans = is_array($raw['bans'] ?? null) ? $raw['bans'] : [];
        echo json_encode($mumble->setMumbleBans($sid, $bans));
        exit;
    }

    /* --- AJAX: ACL-Daten lesen --- */
    if ($p === 'mumble_acl' && $c === 'acl_data') {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        $cid = (int)($_GET['channel_id'] ?? 0);
        if (!$mumble->canManageServer($sid)) {
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']);
        } else {
            $res = $mumble->getChannelAcl($sid, $cid);
            echo json_encode($res['ok'] ? $res['data'] ?? $res : $res);
        }
        exit;
    }

    /* --- ACL speichern --- */
    if ($p === 'mumble_acl' && $c === 'save_acl'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '{}', true) ?: [];

        $posted   = (string)($payload['csrf'] ?? '');
        $expected = (string)($_SESSION['ml_csrfToken'] ?? '');
        if ($posted === '' || !hash_equals($expected, $posted)) {
            echo json_encode(['ok' => false, 'error' => 'CSRF-Token ungültig']);
            exit;
        }
        if (!$mumble->canManageServer($sid)) {
            echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']);
            exit;
        }

        $channelId   = (int)($payload['channel_id'] ?? 0);
        $inheritAcl  = !empty($payload['inherit_acl']);
        $aclEntries  = is_array($payload['acl'] ?? null)    ? $payload['acl']    : [];
        $groupData   = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];

        // Eingaben bereinigen
        $aclClean = array_map(function(array $e): array {
            return [
                'user_id'    => isset($e['user_id']) && $e['user_id'] !== null ? (int)$e['user_id'] : null,
                'group'      => isset($e['group'])   && $e['group'] !== null   ? (string)$e['group'] : null,
                'apply_here' => !empty($e['apply_here']),
                'apply_sub'  => !empty($e['apply_sub']),
                'grant'      => (int)($e['grant'] ?? 0),
                'deny'       => (int)($e['deny']  ?? 0),
            ];
        }, $aclEntries);

        $groupClean = array_map(function(array $g): array {
            return [
                'name'           => (string)($g['name'] ?? ''),
                'inherit'        => !empty($g['inherit']),
                'inheritable'    => !empty($g['inheritable']),
                'members_add'    => array_map('intval', (array)($g['members_add']    ?? [])),
                'members_remove' => array_map('intval', (array)($g['members_remove'] ?? [])),
            ];
        }, $groupData);

        $res = $mumble->setChannelAcl($sid, $channelId, $inheritAcl, $aclClean, $groupClean);
        echo json_encode($res['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $res['error'] ?? 'Unbekannter Fehler']);
        exit;
    }

    /* --- AJAX: Channel-Viewer-Daten --- */
    if (in_array($p, ['mumble_edit', 'mumble_acl'], true) && $c === 'viewer_data') {
        header('Content-Type: application/json');
        $sid = (int)($_GET['id'] ?? 0);
        $data = $mumble->getViewer($sid);
        echo json_encode($data ?? ['ok' => false, 'error' => 'unavailable']);
        exit;
    }

    /* --- Widget-Einstellungen speichern --- */
    if ($p === 'mumble_edit' && $c === 'widget_save'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if (!$mb_csrf_ok) {
            $error .= '<div class="alert alert-danger">CSRF-Token ungültig.</div>';
        } else {
            $sid     = (int)($_GET['id'] ?? 0);
            $mode    = (string)($_POST['widget_mode'] ?? 'disabled');
            $refresh = max(0, min(300, (int)($_POST['widget_refresh'] ?? 30)));
            $mumble->saveWidgetSettings($sid, $mode === 'public', $refresh);
            if ($mode === 'token') {
                $mumble->generateWidgetToken($sid);
            } elseif ($mode === 'disabled') {
                $mumble->disableWidget($sid);
            }
            header('Location: ?p=mumble_edit&id='.$sid.'&h=widget_saved');
            exit;
        }
    }

    /* --- Widget-Token neu generieren --- */
    if ($p === 'mumble_edit' && $c === 'widget_regen'
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
    {
        if ($mb_csrf_ok) {
            $sid = (int)($_GET['id'] ?? 0);
            $mumble->generateWidgetToken($sid);
        }
        header('Location: ?p=mumble_edit&id='.(int)($_GET['id'] ?? 0).'&h=widget_saved');
        exit;
    }

    /* --- GET-Erfolgsmeldungen nach Redirect --- */
    $mb_h = (string)($_GET['h'] ?? '');
    if ($mb_h !== '' && empty($success)) {
        $success = match (true) {
            $p === 'mumble'       && $mb_h === 'server_created' => 'Mumble-Server wurde erfolgreich erstellt.',
            $p === 'mumble'       && $mb_h === 'action_ok'      => 'Aktion erfolgreich ausgeführt.',
            $p === 'mumble_edit'  && $mb_h === 'supw_reset'     => 'SuperUser-Passwort wurde zurückgesetzt.',
            $p === 'mumble_edit'  && $mb_h === 'settings_saved' => 'Einstellungen gespeichert. Server wurde neugestartet.',
            $p === 'mumble_edit'  && $mb_h === 'widget_saved'   => 'Widget-Einstellungen gespeichert.',
            $p === 'mumble_edit'  && $mb_h === 'member_added'   => 'Mitglied hinzugefügt.',
            $p === 'mumble_edit'  && $mb_h === 'member_removed' => 'Mitglied entfernt.',
            $p === 'mumble_config'&& $mb_h === 'config_saved'   => 'Config gespeichert. Server wurde neugestartet.',
            $p === 'mumble_config'&& $mb_h === 'settings_saved' => 'Einstellungen gespeichert. Server wurde neugestartet.',
            $p === 'mumble_config'&& $mb_h === 'cert_uploaded'  => 'Zertifikat hochgeladen und aktiviert. Server wurde neugestartet.',
            $p === 'mumble_config'&& $mb_h === 'cert_removed'   => 'Zertifikat entfernt. Server verwendet wieder ein selbst-signiertes Zertifikat.',
            $p === 'mumble_hosts' && $mb_h === 'host_saved'     => 'Host gespeichert.',
            $p === 'mumble_hosts' && $mb_h === 'host_deleted'   => 'Host gelöscht.',
            $p === 'mumble_quota' && $mb_h === 'quota_saved'    => 'Quotas gespeichert.',
            default => '',
        };
    }

    unset($mb_csrf_ok, $mb_h);
}

/* --- Öffentlicher Widget-Endpoint (kein Login nötig) --- */
// Muss VOR dem Layout-HTML laufen, damit die Seite standalone ohne Sidebar gerendert wird.
if ($p === 'mumble_widget') {
    // X-Frame-Options entfernen damit das Widget in externen iFrames eingebettet werden kann
    header_remove('X-Frame-Options');
    header('X-Frame-Options: ALLOWALL');
    $mumble = new mumble();
    include dirname(__FILE__).'/../templates/mumble/mumble_widget.php';
    exit;
}
