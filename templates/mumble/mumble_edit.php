<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Server-Details + Bearbeiten (Dashboard)
 * File: templates/mumble/mumble_edit.php
 * v0.2.0: SuperUser-PW, Server-Eckdaten editieren, Config-Link
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-danger mt-4">Mumble-Erweiterung nicht initialisiert.</div></div></div>';
    return;
}
if (!$mumble->canView()) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Keine Berechtigung.</div></div></div>';
    return;
}

$mb_sid = (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);
$mb_uid = (int)$loginsystem->getUser('id');
if (!$mb_srv || (!$mumble->canAdminAll() && (int)$mb_srv['owner_user_id'] !== $mb_uid)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Server nicht gefunden oder keine Berechtigung.</div></div></div>';
    return;
}

$mumble->refreshStats($mb_sid);
$mb_srv  = $mumble->getServer($mb_sid);
$mb_csrf = (string)$loginsystem->getData('csrfToken');

$mb_uptime = static function (int $secs): string {
    if ($secs <= 0) return '–';
    $d = (int)floor($secs / 86400);
    $h = (int)floor(($secs % 86400) / 3600);
    $m = (int)floor(($secs % 3600) / 60);
    $parts = [];
    if ($d > 0) $parts[] = $d.'d';
    if ($h > 0) $parts[] = $h.'h';
    if ($m > 0) $parts[] = $m.'m';
    if (empty($parts)) $parts[] = $secs.'s';
    return implode(' ', $parts);
};

$mb_status = (string)$mb_srv['status'];
$mb_cls = match ($mb_status) {
    'running'  => 'success',
    'stopped'  => 'secondary',
    'error'    => 'danger',
    'creating' => 'warning',
    default    => 'info',
};

// SuperUser-PW laden (best effort)
$mb_supw = $mumble->getSuperUserPassword($mb_sid);
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3">
        <i class="fa fa-headphones"></i>
        <?php echo htmlspecialchars((string)$mb_srv['name']); ?>
        <small>Mumble-Server #<?php echo (int)$mb_srv['id']; ?></small>
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>

    <?php echo $error; ?>

    <div class="row">
        <!-- Linke Spalte: Verbindungsdaten + SuperUser + Begrüßung -->
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-info-circle"></i> Verbindungsdaten</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th style="width:40%;">Adresse</th>
                                <td>
                                    <code><?php echo htmlspecialchars((string)$mb_srv['hostname']); ?>:<?php echo (int)$mb_srv['port']; ?></code>
                                    <a class="btn btn-link btn-sm py-0"
                                       href="mumble://<?php echo htmlspecialchars((string)$mb_srv['hostname']); ?>:<?php echo (int)$mb_srv['port']; ?>">
                                        <i class="fa fa-external-link"></i> verbinden
                                    </a>
                                </td>
                            </tr>
                            <tr><th>Host</th><td><?php echo htmlspecialchars((string)$mb_srv['host_name']); ?></td></tr>
                            <tr><th>Status</th><td>
                                <span class="badge badge-<?php echo $mb_cls; ?>"><?php echo htmlspecialchars($mb_status); ?></span>
                            </td></tr>
                            <tr><th>Passwortgeschützt</th><td>
                                <?php echo !empty($mb_srv['password'])
                                    ? '<i class="fa fa-lock text-success"></i> Ja'
                                    : '<i class="fa fa-unlock text-muted"></i> Nein (öffentlich)'; ?>
                            </td></tr>
                            <tr><th>Online</th><td>
                                <strong><?php echo (int)$mb_srv['stats_online']; ?></strong>
                                / <?php echo (int)$mb_srv['max_users']; ?>
                            </td></tr>
                            <tr><th>Uptime</th><td><?php echo htmlspecialchars($mb_uptime((int)$mb_srv['stats_uptime'])); ?></td></tr>
                            <tr><th>Erstellt</th><td><?php echo htmlspecialchars((string)$mb_srv['created_at']); ?></td></tr>
                            <?php if ($mumble->canAdminAll()) { ?>
                            <tr><th>Container-ID</th>
                                <td><code class="small"><?php echo htmlspecialchars(substr((string)$mb_srv['container_id'], 0, 12)); ?></code></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SuperUser-Passwort -->
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-key"></i> SuperUser-Zugang</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Mit dem SuperUser-Account kannst du dich im Mumble-Client als Administrator
                        anmelden (Benutzername: <code>SuperUser</code>). Damit lassen sich Channels
                        erstellen, ACLs setzen und User verwalten.
                    </p>
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" id="mb-supw-field" readonly
                               value="<?php echo htmlspecialchars($mb_supw ?? '(unbekannt)'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="mb-supw-toggle"
                                    title="Passwort anzeigen/verstecken">
                                <i class="fa fa-eye" id="mb-supw-icon"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" id="mb-supw-copy"
                                    title="Kopieren">
                                <i class="fa fa-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <form method="post" action="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=superuser_reset"
                          onsubmit="return confirm('SuperUser-Passwort wirklich zurücksetzen? Das alte ist danach ungültig.');">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-8 mb-0">
                                <input type="text" class="form-control form-control-sm" name="new_supw"
                                       placeholder="neues Passwort (leer = zufällig generieren)"
                                       maxlength="128" autocomplete="off">
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <button type="submit" class="btn btn-sm btn-warning btn-block">
                                    <i class="fa fa-refresh"></i> Zurücksetzen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($mb_srv['welcome_text'])) { ?>
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-comment-o"></i> Begrüßungstext</div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars((string)$mb_srv['welcome_text'])); ?></p>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Rechte Spalte: Aktionen + Einstellungen bearbeiten -->
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-cogs"></i> Aktionen</div>
                <div class="card-body">
                    <?php if (in_array($mb_status, ['stopped','error'], true)) { ?>
                        <form method="post" action="?p=mumble&c=start" class="mb-2">
                            <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$mb_srv['id']; ?>">
                            <button class="btn btn-success btn-block">
                                <i class="fa fa-play"></i> Server starten
                            </button>
                        </form>
                    <?php } else { ?>
                        <form method="post" action="?p=mumble&c=stop" class="mb-2">
                            <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$mb_srv['id']; ?>">
                            <button class="btn btn-warning btn-block">
                                <i class="fa fa-stop"></i> Server stoppen
                            </button>
                        </form>
                    <?php } ?>

                    <?php if ($mb_status === 'running') { ?>
                    <form method="post" action="?p=mumble&c=restart" class="mb-2">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$mb_srv['id']; ?>">
                        <button class="btn btn-info btn-block">
                            <i class="fa fa-refresh"></i> Neustarten
                        </button>
                    </form>
                    <?php } ?>

                    <a href="?p=mumble_logs&id=<?php echo (int)$mb_srv['id']; ?>"
                       class="btn btn-secondary btn-block mb-2">
                        <i class="fa fa-file-text-o"></i> Logs anzeigen
                    </a>
                    <a href="?p=mumble_config&id=<?php echo (int)$mb_srv['id']; ?>"
                       class="btn btn-outline-dark btn-block mb-2">
                        <i class="fa fa-file-code-o"></i> Server-Config (INI) bearbeiten
                    </a>
                    <hr>
                    <form method="post" action="?p=mumble&c=delete"
                          onsubmit="return confirm('Server wirklich endgültig löschen?');">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$mb_srv['id']; ?>">
                        <button class="btn btn-danger btn-block">
                            <i class="fa fa-trash"></i> Server löschen
                        </button>
                    </form>
                </div>
            </div>

            <!-- Server-Einstellungen bearbeiten -->
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-pencil"></i> Einstellungen bearbeiten</div>
                <div class="card-body">
                    <form method="post" action="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=update_settings">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <div class="form-group">
                            <label>Server-Name</label>
                            <input type="text" class="form-control form-control-sm" name="name"
                                   maxlength="64"
                                   value="<?php echo htmlspecialchars((string)$mb_srv['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Server-Passwort <small class="text-muted">(leer = öffentlich)</small></label>
                            <input type="text" class="form-control form-control-sm" name="password"
                                   maxlength="64" autocomplete="off"
                                   value="<?php echo htmlspecialchars((string)$mb_srv['password']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Maximale Nutzer</label>
                            <input type="number" class="form-control form-control-sm" name="max_users"
                                   min="1" max="500"
                                   value="<?php echo (int)$mb_srv['max_users']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Begrüßungstext</label>
                            <textarea class="form-control form-control-sm" name="welcome_text"
                                      rows="3" maxlength="2000"><?php echo htmlspecialchars((string)$mb_srv['welcome_text']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-save"></i> Speichern &amp; Neustart
                        </button>
                        <small class="form-text text-muted text-center">
                            Der Server wird nach dem Speichern automatisch neu gestartet,
                            damit die Änderungen aktiv werden.
                        </small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
(function () {
    'use strict';
    // SuperUser PW reveal toggle
    var f = document.getElementById('mb-supw-field');
    var b = document.getElementById('mb-supw-toggle');
    var i = document.getElementById('mb-supw-icon');
    if (f && b) {
        b.addEventListener('click', function () {
            if (f.type === 'password') {
                f.type = 'text'; i.className = 'fa fa-eye-slash';
            } else {
                f.type = 'password'; i.className = 'fa fa-eye';
            }
        });
    }
    // Copy
    var cb = document.getElementById('mb-supw-copy');
    if (cb && f) {
        cb.addEventListener('click', function () {
            var old = f.type; f.type = 'text';
            f.select(); document.execCommand('copy');
            f.type = old;
            cb.innerHTML = '<i class="fa fa-check"></i>';
            setTimeout(function () { cb.innerHTML = '<i class="fa fa-clipboard"></i>'; }, 2000);
        });
    }
})();
</script>
