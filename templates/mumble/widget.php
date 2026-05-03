<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Dashboard-Widget (SB-Admin Style)
 * File: templates/mumble/widget.php
 *
 * Drop-in für dashboard-home.php — fügt sich als SB-Admin Icon-Cards
 * nahtlos in die Kachel-Reihe ein.
 *
 * Einbindung in templates/dashboard-home.php in der Icon-Cards Row:
 *   <?php include __DIR__ . '/mumble/widget.php'; ?>
 *
 * Defensive: rendert nichts wenn Erweiterung nicht installiert oder
 * der Nutzer keine Mumble-Rechte hat.
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) return;
if (!isset($loginsystem) || !$loginsystem->login_session()) return;
if (!$mumble->canView()) return;

$mb_w = $mumble->getWidgetSummary();
?>
<!-- Mumble Widget: eigene Server -->
<div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-info o-hidden h-100">
        <div class="card-body">
            <div class="card-body-icon">
                <i class="fa fa-fw fa-headphones"></i>
            </div>
            <div class="mr-5">
                <strong><?php echo (int)$mb_w['own']['running']; ?></strong>
                / <?php echo (int)$mb_w['own']['total']; ?> Mumble-Server aktiv
            </div>
        </div>
        <a class="card-footer text-white clearfix small z-1" href="?p=mumble">
            <span class="float-left">Verwalten</span>
            <span class="float-right">
                <i class="fa fa-angle-right"></i>
            </span>
        </a>
    </div>
</div>

<!-- Mumble Widget: Online User -->
<div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-success o-hidden h-100">
        <div class="card-body">
            <div class="card-body-icon">
                <i class="fa fa-fw fa-users"></i>
            </div>
            <div class="mr-5">
                <strong><?php echo (int)$mb_w['own']['online']; ?></strong>
                User auf deinen Mumble-Servern online
            </div>
        </div>
        <a class="card-footer text-white clearfix small z-1" href="?p=mumble">
            <span class="float-left">Übersicht</span>
            <span class="float-right">
                <i class="fa fa-angle-right"></i>
            </span>
        </a>
    </div>
</div>

<?php if ($mb_w['all'] !== null) { ?>
<!-- Admin: alle Mumble-Server -->
<div class="col-xl-3 col-sm-6 mb-3">
    <div class="card text-white bg-secondary o-hidden h-100">
        <div class="card-body">
            <div class="card-body-icon">
                <i class="fa fa-fw fa-globe"></i>
            </div>
            <div class="mr-5">
                <strong><?php echo (int)$mb_w['all']['running']; ?></strong>
                / <?php echo (int)$mb_w['all']['total']; ?> Server gesamt (Admin)
            </div>
        </div>
        <a class="card-footer text-white clearfix small z-1" href="?p=mumble_hosts">
            <span class="float-left">Hosts verwalten</span>
            <span class="float-right">
                <i class="fa fa-angle-right"></i>
            </span>
        </a>
    </div>
</div>
<?php } ?>
