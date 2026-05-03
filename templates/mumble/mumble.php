<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Server-Übersicht (Dashboard-Variante)
 * File: templates/mumble/mumble.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container">'
       . '<div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert. '
       . 'Bitte system/classes.run.user.php prüfen.</div></div></div>';
    return;
}

if (!$mumble->canView()) {
    echo '<div class="content-wrapper"><div class="container full-container">'
       . '<div class="alert alert-warning mt-4">Keine Berechtigung zur Mumble-Verwaltung.</div>'
       . '</div></div>';
    return;
}

$mb_isAdmin = $mumble->canAdminAll();
$mb_servers = $mb_isAdmin
    ? $mumble->listAllServers()
    : $mumble->listServersByOwner((int)$loginsystem->getUser('id'));
$mb_csrf    = (string)$loginsystem->getData('csrfToken');

$mb_statusClass = static fn(string $s): string => match ($s) {
    'running'  => 'success',
    'stopped'  => 'secondary',
    'error'    => 'danger',
    'creating' => 'warning',
    default    => 'info',
};
$mb_statusIcon = static fn(string $s): string => match ($s) {
    'running'  => 'fa-check-circle',
    'stopped'  => 'fa-stop-circle',
    'error'    => 'fa-exclamation-triangle',
    'creating' => 'fa-spinner fa-spin',
    default    => 'fa-question-circle',
};
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3"><i class="fa fa-headphones"></i> Mumble-Server <small>Verwaltung</small></h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item active">Mumble</li>
    </ol>

    <?php echo $error; ?>

    <div class="row mb-4">
        <div class="col-12 mb-3">
            <div class="clearfix">
                <?php if ($mumble->canCreate()) { ?>
                    <a class="btn btn-primary float-right ml-1" href="?p=mumble_new">
                        <i class="fa fa-plus"></i> Neuer Mumble-Server
                    </a>
                <?php } ?>
                <?php if ($mumble->canManageHosts()) { ?>
                    <a class="btn btn-secondary float-right ml-1" href="?p=mumble_hosts">
                        <i class="fa fa-server"></i> Hosts verwalten
                    </a>
                <?php } ?>
                <?php if ($mumble->canManageQuotas()) { ?>
                    <a class="btn btn-secondary float-right ml-1" href="?p=mumble_quota">
                        <i class="fa fa-tachometer"></i> Quotas
                    </a>
                <?php } ?>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-list"></i> Mumble-Server
                </div>
                <div class="card-body">
                    <?php if (empty($mb_servers)) { ?>
                        <div class="text-center text-muted py-5">
                            <i class="fa fa-headphones fa-3x mb-3 d-block"></i>
                            <p>Du hast noch keinen Mumble-Server angelegt.</p>
                            <?php if ($mumble->canCreate()) { ?>
                                <a class="btn btn-primary" href="?p=mumble_new">
                                    <i class="fa fa-plus"></i> Jetzt ersten Server anlegen
                                </a>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="data-table">
                                <thead>
                                    <tr>
                                        <th>Server</th>
                                        <?php if ($mb_isAdmin) { ?><th>Besitzer</th><?php } ?>
                                        <th>Host</th>
                                        <th>Adresse</th>
                                        <th>Status</th>
                                        <th>Online</th>
                                        <th class="text-right" style="min-width:240px;">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($mb_servers as $s) {
                                    $status = (string)$s['status'];
                                    $cls    = $mb_statusClass($status);
                                    $ico    = $mb_statusIcon($status);
                                ?>
                                    <tr data-server-id="<?php echo (int)$s['id']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars((string)$s['name']); ?></strong><br>
                                            <small class="text-muted">ID #<?php echo (int)$s['id']; ?></small>
                                        </td>
                                        <?php if ($mb_isAdmin) { ?>
                                            <td><?php echo htmlspecialchars((string)($s['owner_name'] ?? '–')); ?></td>
                                        <?php } ?>
                                        <td><?php echo htmlspecialchars((string)$s['host_name']); ?></td>
                                        <td>
                                            <code><?php echo htmlspecialchars((string)$s['hostname']); ?>:<?php echo (int)$s['port']; ?></code>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $cls; ?>">
                                                <i class="fa <?php echo $ico; ?>"></i>
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td class="js-online">
                                            <span class="js-online-num"><?php echo (int)$s['stats_online']; ?></span>
                                            / <?php echo (int)$s['max_users']; ?>
                                        </td>
                                        <td class="text-right">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if ($status === 'stopped' || $status === 'error') { ?>
                                                    <form method="post" action="?p=mumble&c=start" class="d-inline">
                                                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                        <button class="btn btn-success" title="Starten">
                                                            <i class="fa fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php } ?>
                                                <?php if ($status === 'running') { ?>
                                                    <form method="post" action="?p=mumble&c=stop" class="d-inline">
                                                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                        <button class="btn btn-warning" title="Stoppen">
                                                            <i class="fa fa-stop"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" action="?p=mumble&c=restart" class="d-inline">
                                                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                        <button class="btn btn-info" title="Neustart">
                                                            <i class="fa fa-refresh"></i>
                                                        </button>
                                                    </form>
                                                <?php } ?>
                                                <a class="btn btn-outline-secondary" title="Logs"
                                                   href="?p=mumble_logs&id=<?php echo (int)$s['id']; ?>">
                                                    <i class="fa fa-file-text-o"></i>
                                                </a>
                                                <a class="btn btn-outline-secondary" title="Details"
                                                   href="?p=mumble_edit&id=<?php echo (int)$s['id']; ?>">
                                                    <i class="fa fa-info-circle"></i>
                                                </a>
                                                <form method="post" action="?p=mumble&c=delete" class="d-inline"
                                                      onsubmit="return confirm('Server &quot;<?php echo htmlspecialchars((string)$s['name'], ENT_QUOTES); ?>&quot; wirklich endgültig löschen?');">
                                                    <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                    <button class="btn btn-danger" title="Löschen">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
(function () {
    'use strict';
    var rows = document.querySelectorAll('tr[data-server-id]');
    if (!rows.length) return;
    function refreshOne(row) {
        var id = row.getAttribute('data-server-id');
        fetch('?p=mumble&c=stats&id=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              if (!j || !j.ok || !j.data) return;
              var n = row.querySelector('.js-online-num');
              if (n && typeof j.data.online !== 'undefined') n.textContent = j.data.online;
          }).catch(function () {});
    }
    setInterval(function () { rows.forEach(refreshOne); }, 15000);
})();
</script>
