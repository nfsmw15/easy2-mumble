<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Host-Verwaltung (Dashboard)
 * File: templates/mumble/mumble_hosts.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert.</div></div></div>';
    return;
}
if (!$mumble->canManageHosts()) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Keine Berechtigung zur Host-Verwaltung.</div></div></div>';
    return;
}

$mb_csrf  = (string)$loginsystem->getData('csrfToken');
$mb_hosts = $mumble->listHosts(false);

$mb_edit = null;
if (!empty($_GET['edit'])) {
    $mb_edit = $mumble->getHost((int)$_GET['edit']);
}
$mb_isNew = isset($_GET['new']);

$mb_pings = [];
foreach ($mb_hosts as $h) {
    if ((int)$h['is_active'] !== 1) continue;
    $a = new mumble_agent((string)$h['agent_url'], (string)$h['agent_token'], 3);
    $r = $a->ping();
    $mb_pings[(int)$h['id']] = $r['ok'];
    if ($r['ok']) $mumble->touchHostLastSeen((int)$h['id']);
}
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3"><i class="fa fa-server"></i> Mumble-Hosts <small>verwalten</small></h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item active">Hosts</li>
    </ol>

    <?php echo $error; ?>

    <div class="row mb-4">
        <div class="col-lg-7">
            <div class="clearfix mb-3">
                <a class="btn btn-primary float-right" href="?p=mumble_hosts&new=1">
                    <i class="fa fa-plus"></i> Neuer Host
                </a>
            </div>
            <div class="card">
                <div class="card-header"><i class="fa fa-list"></i> Bekannte Hosts</div>
                <div class="card-body p-0">
                    <?php if (empty($mb_hosts)) { ?>
                        <p class="text-muted text-center py-4 mb-0">
                            Noch kein Host konfiguriert.
                            <a href="?p=mumble_hosts&new=1">Jetzt anlegen</a>.
                        </p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead><tr>
                                    <th>Name</th><th>Hostname</th><th>Ports</th>
                                    <th>Status</th><th class="text-right">Aktionen</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($mb_hosts as $h) {
                                    $online = $mb_pings[(int)$h['id']] ?? null;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars((string)$h['name']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars((string)$h['hostname']); ?></code></td>
                                        <td><small><?php echo (int)$h['port_min']; ?>–<?php echo (int)$h['port_max']; ?></small></td>
                                        <td>
                                            <?php if ((int)$h['is_active'] !== 1) { ?>
                                                <span class="badge badge-secondary">inaktiv</span>
                                            <?php } elseif ($online === true) { ?>
                                                <span class="badge badge-success"><i class="fa fa-check"></i> online</span>
                                            <?php } elseif ($online === false) { ?>
                                                <span class="badge badge-danger"><i class="fa fa-times"></i> nicht erreichbar</span>
                                            <?php } else { ?>
                                                <span class="badge badge-warning">unbekannt</span>
                                            <?php } ?>
                                        </td>
                                        <td class="text-right">
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="?p=mumble_hosts&edit=<?php echo (int)$h['id']; ?>"
                                               title="Bearbeiten">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                            <form method="post" action="?p=mumble_hosts&c=delete" class="d-inline"
                                                  onsubmit="return confirm('Host &quot;<?php echo htmlspecialchars((string)$h['name'], ENT_QUOTES); ?>&quot; löschen?');">
                                                <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$h['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="Löschen">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
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

        <div class="col-lg-5">
            <?php if ($mb_isNew || $mb_edit) { ?>
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-<?php echo $mb_edit ? 'pencil' : 'plus'; ?>"></i>
                    Host <?php echo $mb_edit ? 'bearbeiten' : 'hinzufügen'; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="?p=mumble_hosts&c=save">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <?php if ($mb_edit) { ?>
                            <input type="hidden" name="id" value="<?php echo (int)$mb_edit['id']; ?>">
                        <?php } ?>

                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" class="form-control" name="name" maxlength="64" required
                                   placeholder="z.B. proxmox-vps-01"
                                   value="<?php echo htmlspecialchars((string)($_POST['name'] ?? $mb_edit['name'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Hostname (für Clients) *</label>
                            <input type="text" class="form-control" name="hostname" maxlength="255" required
                                   placeholder="mumble1.example.com"
                                   value="<?php echo htmlspecialchars((string)($_POST['hostname'] ?? $mb_edit['hostname'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Agent-URL *</label>
                            <input type="url" class="form-control" name="agent_url" maxlength="255" required
                                   placeholder="https://mumble1.example.com:8443"
                                   value="<?php echo htmlspecialchars((string)($_POST['agent_url'] ?? $mb_edit['agent_url'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Agent-Token *</label>
                            <input type="text" class="form-control" name="agent_token" maxlength="128" required
                                   autocomplete="off"
                                   placeholder="Bearer-Token vom Agent-Setup"
                                   value="<?php echo htmlspecialchars((string)($_POST['agent_token'] ?? $mb_edit['agent_token'] ?? '')); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Port-Min</label>
                                <input type="number" class="form-control" name="port_min" min="1024" max="65535"
                                       value="<?php echo (int)($_POST['port_min'] ?? $mb_edit['port_min'] ?? 64738); ?>">
                            </div>
                            <div class="form-group col-6">
                                <label>Port-Max</label>
                                <input type="number" class="form-control" name="port_max" min="1024" max="65535"
                                       value="<?php echo (int)($_POST['port_max'] ?? $mb_edit['port_max'] ?? 64838); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Max. Server auf diesem Host</label>
                            <input type="number" class="form-control" name="max_servers" min="1" max="999"
                                   value="<?php echo (int)($_POST['max_servers'] ?? $mb_edit['max_servers'] ?? 20); ?>">
                        </div>
                        <div class="form-group">
                            <label>Notiz</label>
                            <textarea class="form-control" name="note" rows="2" maxlength="500"><?php
                                echo htmlspecialchars((string)($_POST['note'] ?? $mb_edit['note'] ?? '')); ?></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="mb-is-active"
                                       name="is_active" value="1"
                                    <?php echo (!isset($mb_edit) || (int)($mb_edit['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="mb-is-active">Host aktiv</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check"></i> Speichern
                        </button>
                        <a href="?p=mumble_hosts" class="btn btn-secondary">
                            <i class="fa fa-times"></i> Abbrechen
                        </a>
                    </form>
                </div>
            </div>
            <?php } else { ?>
            <div class="card">
                <div class="card-header"><i class="fa fa-info-circle"></i> Hinweise</div>
                <div class="card-body">
                    <p>Ein <strong>Host</strong> ist ein Server (VPS oder Root) auf dem Docker
                    und der <code>mumble-agent</code> laufen.</p>
                    <p class="small">Anleitung: siehe <a href="https://github.com/nfsmw15/mumble-agent">mumble-agent Repo</a></p>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
</div>
