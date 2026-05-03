<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Quota-Verwaltung pro Rang (Dashboard)
 * File: templates/mumble/mumble_quota.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert.</div></div></div>';
    return;
}
if (!$mumble->canManageQuotas()) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Keine Berechtigung.</div></div></div>';
    return;
}

$mb_csrf  = (string)$loginsystem->getData('csrfToken');
$mb_ranks = $mumble->getAllRanks();
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3"><i class="fa fa-tachometer"></i> Mumble-Quotas <small>pro Rang</small></h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item active">Quotas</li>
    </ol>

    <?php echo $error; ?>

    <form method="post" action="?p=mumble_quota&c=save">
        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">

        <div class="card mb-4">
            <div class="card-header"><i class="fa fa-list"></i> Berechtigungen je Rang</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr>
                            <th>Rang</th>
                            <th title="Maximale Anzahl Server, die ein Mitglied dieses Rangs anlegen darf">
                                Max. Server
                            </th>
                            <th title="Obergrenze für die max_users-Einstellung pro Server">
                                Max. Nutzer/Server
                            </th>
                            <th class="text-center">Server erstellen</th>
                            <th class="text-center">Alle Server verwalten</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($mb_ranks as $r) {
                            $rid = (int)$r['id'];
                            $q   = $mumble->getQuotaForRank($rid);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string)$r['name']); ?></strong>
                                    <small class="text-muted">#<?php echo $rid; ?></small></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm"
                                           name="q[<?php echo $rid; ?>][max_servers]"
                                           min="0" max="999" style="width:90px;"
                                           value="<?php echo (int)$q['max_servers']; ?>">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm"
                                           name="q[<?php echo $rid; ?>][max_users_cap]"
                                           min="1" max="500" style="width:90px;"
                                           value="<?php echo (int)$q['max_users_cap']; ?>">
                                </td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="form-check-input position-static"
                                           name="q[<?php echo $rid; ?>][can_create]" value="1"
                                        <?php echo (int)$q['can_create'] === 1 ? 'checked' : ''; ?>>
                                </td>
                                <td class="text-center align-middle">
                                    <input type="checkbox" class="form-check-input position-static"
                                           name="q[<?php echo $rid; ?>][can_admin_all]" value="1"
                                        <?php echo (int)$q['can_admin_all'] === 1 ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-check"></i> Quotas speichern
                </button>
            </div>
        </div>
    </form>

    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        Quotas wirken nur wenn der jeweilige Rang auch die Regel <code>mumble_create</code>
        besitzt. Die Regel "Alle Server verwalten" benötigt zusätzlich <code>mumble_admin</code>.
    </div>
</div>
</div>
