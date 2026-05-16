<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Dashboard-Übersicht
 * File: templates/mumble/mumble_dashboard.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) { return; }
if (!$mumble->canView()) { return; }
$mb_isAdmin = $mumble->canAdminAll();
?>
<div class="content-wrapper">
<div class="container full-container">

    <h1 class="mt-4 mb-3">
        <i class="fa fa-tachometer"></i>
        Mumble Dashboard
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>

    <div id="db-root" data-sid="0" data-admin="<?= $mb_isAdmin ? '1' : '0' ?>">

        <!-- Zusammenfassungs-Karten -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="fa fa-server"></i> Server</div>
                        <div class="h4 mb-0" id="db-stat-servers">–</div>
                        <div class="text-muted small">laufend</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="fa fa-users"></i> Nutzer online</div>
                        <div class="h4 mb-0" id="db-stat-users">–</div>
                        <div class="text-muted small">gesamt</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="fa fa-tachometer"></i> Bandbreite</div>
                        <div class="h4 mb-0" id="db-stat-bw">–</div>
                        <div class="text-muted small">gesamt</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1"><i class="fa fa-clock-o"></i> Ping (Ø)</div>
                        <div class="h4 mb-0" id="db-stat-ping">–</div>
                        <div class="text-muted small">ms</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Letzte Aktualisierung -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fa fa-th-large"></i> Server</h5>
            <small class="text-muted" id="db-last-updated"></small>
        </div>

        <!-- Server-Karten -->
        <div class="row" id="db-servers">
            <div class="col-12 text-center text-muted py-4">
                <i class="fa fa-spinner fa-spin"></i> Lade Dashboard-Daten…
            </div>
        </div>

        <!-- Nutzer-Tabelle -->
        <div class="mt-4" id="db-users-wrap" style="display:none;">
            <h5 class="mb-3"><i class="fa fa-headphones"></i> Online-Nutzer</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Nutzer</th>
                            <th>Server</th>
                            <th>Channel</th>
                            <th>KB/s</th>
                            <th>UDP-Ping</th>
                            <th>Idle</th>
                            <th>OS</th>
                            <th>&#128263;</th>
                        </tr>
                    </thead>
                    <tbody id="db-users-tbody">
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- #db-root -->

</div><!-- .container -->
</div><!-- .content-wrapper -->

<script src="system/js/mumble-dashboard.js?v=<?php echo time(); ?>"></script>
