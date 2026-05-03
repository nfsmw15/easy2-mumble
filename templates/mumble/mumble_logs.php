<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Logs (Dashboard)
 * File: templates/mumble/mumble_logs.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert.</div></div></div>';
    return;
}

$mb_sid = (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);
$mb_uid = (int)$loginsystem->getUser('id');
if (!$mb_srv || (!$mumble->canAdminAll() && (int)$mb_srv['owner_user_id'] !== $mb_uid)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Server nicht gefunden oder keine Berechtigung.</div></div></div>';
    return;
}

$mb_tail = max(50, min(1000, (int)($_GET['tail'] ?? 300)));
$mb_res  = $mumble->fetchLogs($mb_sid, $mb_tail);
$mb_log  = $mb_res['ok']
    ? (string)($mb_res['data']['log'] ?? '(keine Logzeilen)')
    : 'Fehler beim Abruf: '.htmlspecialchars((string)$mb_res['error']);
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3">
        <i class="fa fa-file-text-o"></i>
        Logs <small><?php echo htmlspecialchars((string)$mb_srv['name']); ?></small>
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>">
            <?php echo htmlspecialchars((string)$mb_srv['name']); ?>
        </a></li>
        <li class="breadcrumb-item active">Logs</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa fa-list"></i> Letzte <?php echo $mb_tail; ?> Zeilen</span>
            <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary" href="?p=mumble_logs&id=<?php echo (int)$mb_srv['id']; ?>&tail=100">100</a>
                <a class="btn btn-outline-secondary" href="?p=mumble_logs&id=<?php echo (int)$mb_srv['id']; ?>&tail=300">300</a>
                <a class="btn btn-outline-secondary" href="?p=mumble_logs&id=<?php echo (int)$mb_srv['id']; ?>&tail=1000">1000</a>
                <a class="btn btn-outline-primary"
                   href="?p=mumble_logs&id=<?php echo (int)$mb_srv['id']; ?>&tail=<?php echo $mb_tail; ?>"
                   title="Aktualisieren">
                    <i class="fa fa-refresh"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <pre class="mb-0 p-3" style="max-height:600px;overflow:auto;background:#1e1e1e;color:#dcdcdc;font-size:13px;line-height:1.4;"><?php echo htmlspecialchars($mb_log); ?></pre>
        </div>
    </div>

    <a href="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> zurück zum Server
    </a>
</div>
</div>
