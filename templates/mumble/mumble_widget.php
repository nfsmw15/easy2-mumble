<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Channel-Viewer Widget
 * File: templates/mumble/mumble_widget.php
 *
 * Öffentlich einbettbare Seite (iframe).
 * Zugriff: ?p=mumble_widget&token=XYZ  (token-geschützt)
 *           ?p=mumble_widget&id=X      (öffentlich, wenn widget_public=1)
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) { http_response_code(503); exit; }

$token  = trim((string)($_GET['token'] ?? ''));
$sid    = (int)($_GET['id'] ?? 0);

if ($token !== '') {
    $srv = $mumble->getServerByWidget($token);
} elseif ($sid > 0) {
    $srv = $mumble->getPublicServer($sid);
} else {
    $srv = null;
}

if (!$srv) { http_response_code(404); ?>
<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">
<style>body{margin:0;font-family:sans-serif;background:#1a1a2e;color:#e0e0e0;display:flex;align-items:center;justify-content:center;height:100vh;font-size:14px;}</style>
</head><body>Widget nicht verfügbar.</body></html>
<?php exit; }

$refresh = (int)$srv['widget_refresh'];
$agent   = new mumble_agent($srv['agent_url'], $srv['agent_token']);
$res     = $agent->getViewer((string)$srv['container_id']);
$data    = ($res['ok'] && isset($res['data']['channels'])) ? $res['data'] : null;

function render_channel(array $ch, int $depth = 0): void {
    $indent = $depth * 14;
    $icon = $depth === 0 ? '🔊' : '📁';
    echo '<div class="channel" style="padding-left:' . $indent . 'px">';
    echo '<span class="ch-icon">' . $icon . '</span> ';
    echo '<span class="ch-name">' . htmlspecialchars($ch['name']) . '</span>';
    echo '</div>';
    foreach ($ch['users'] as $user) {
        echo '<div class="user" style="padding-left:' . ($indent + 16) . 'px">';
        echo '<span class="u-icon">🎧</span> ' . htmlspecialchars($user);
        echo '</div>';
    }
    foreach ($ch['children'] as $child) {
        render_channel($child, $depth + 1);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if ($refresh > 0): ?>
<meta http-equiv="refresh" content="<?php echo $refresh; ?>">
<?php endif; ?>
<title><?php echo htmlspecialchars((string)$srv['name']); ?> – Channel-Viewer</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', sans-serif;
    font-size: 13px;
    background: #1e1e2e;
    color: #cdd6f4;
    padding: 8px;
    min-height: 100vh;
  }
  .header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    background: #313244;
    border-radius: 6px;
    margin-bottom: 8px;
  }
  .header .srv-name { font-weight: 600; font-size: 14px; flex: 1; }
  .header .online {
    font-size: 11px;
    background: #a6e3a1;
    color: #1e1e2e;
    border-radius: 10px;
    padding: 2px 7px;
    font-weight: 600;
  }
  .channel {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 4px;
    border-radius: 4px;
    font-weight: 600;
    color: #89b4fa;
    margin-top: 2px;
  }
  .user {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 2px 4px;
    border-radius: 4px;
    color: #cdd6f4;
    font-weight: 400;
  }
  .user:hover, .channel:hover { background: #313244; }
  .ch-icon, .u-icon { font-size: 12px; flex-shrink: 0; }
  .offline {
    text-align: center;
    padding: 20px;
    color: #585b70;
  }
  .footer {
    margin-top: 8px;
    font-size: 10px;
    color: #585b70;
    text-align: right;
  }
</style>
</head>
<body>
<div class="header">
  <span class="srv-name">🎮 <?php echo htmlspecialchars((string)$srv['name']); ?></span>
  <?php if ($data): ?>
  <span class="online"><?php echo (int)$data['user_count']; ?> online</span>
  <?php endif; ?>
</div>

<?php if ($data): ?>
  <?php render_channel($data['channels']); ?>
<?php else: ?>
  <div class="offline">Server nicht erreichbar</div>
<?php endif; ?>

<div class="footer">
  <?php echo htmlspecialchars((string)$srv['hostname']); ?>:<?php echo (int)$srv['port']; ?>
  <?php if ($refresh > 0): ?> · Refresh: <?php echo $refresh; ?>s<?php endif; ?>
</div>
</body>
</html>
