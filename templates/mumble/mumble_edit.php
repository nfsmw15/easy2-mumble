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

// Widget-Einstellungen
$mb_widget = $mumble->getWidgetSettings($mb_sid);
$mb_widget_token   = (string)($mb_widget['widget_token'] ?? '');
$mb_widget_public  = (bool)($mb_widget['widget_public'] ?? false);
$mb_widget_refresh = (int)($mb_widget['widget_refresh'] ?? 30);
$mb_widget_url     = rtrim((string)($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.(string)($_SERVER['HTTP_HOST'] ?? ''), '/');
$mb_widget_iframe  = $mb_widget_token !== ''
    ? $mb_widget_url.'/?p=mumble_widget&token='.$mb_widget_token
    : ($mb_widget_public ? $mb_widget_url.'/?p=mumble_widget&id='.$mb_sid : '');
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
                        <input type="text" class="form-control" id="mb-supw-field" readonly
                               value="••••••••••••"
                               data-pw="<?php echo htmlspecialchars($mb_supw ?? ''); ?>">
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
                    <form id="mb-form-supw-reset" method="post" action="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=superuser_reset">
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

        <!-- Channel-Viewer -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fa fa-sitemap"></i> Channel-Viewer</span>
                    <button class="btn btn-sm btn-outline-secondary" id="mb-viewer-refresh" title="Aktualisieren">
                        <i class="fa fa-refresh"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="mb-viewer-content" style="min-height:60px;"
                         data-refresh="<?php echo $mb_widget_refresh; ?>">
                        <div class="p-3 text-muted text-center small">
                            <i class="fa fa-spinner fa-spin"></i> Lade…
                        </div>
                    </div>
                </div>
            </div>
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
                    <form id="mb-form-delete" method="post" action="?p=mumble&c=delete">
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

            <!-- Widget-Einstellungen -->
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-code"></i> Einbettbares Widget</div>
                <div class="card-body">
                    <form method="post" action="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=widget_save">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <div class="form-group">
                            <label class="mb-1">Sichtbarkeit</label>
                            <select name="widget_mode" class="form-control form-control-sm" id="mb-widget-mode">
                                <option value="disabled" <?php echo $mb_widget_token === '' && !$mb_widget_public ? 'selected' : ''; ?>>Deaktiviert</option>
                                <option value="token"    <?php echo $mb_widget_token !== '' && !$mb_widget_public ? 'selected' : ''; ?>>Token-geschützt (empfohlen)</option>
                                <option value="public"   <?php echo $mb_widget_public ? 'selected' : ''; ?>>Öffentlich (nur Server-ID)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="mb-1">Auto-Refresh (Sekunden, 0 = aus)</label>
                            <input type="number" name="widget_refresh" class="form-control form-control-sm"
                                   min="0" max="300" value="<?php echo $mb_widget_refresh; ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary btn-block">
                            <i class="fa fa-save"></i> Speichern
                        </button>
                    </form>

                    <?php if ($mb_widget_iframe !== ''): ?>
                    <hr>
                    <label class="small mb-1"><i class="fa fa-link"></i> Widget-URL / iframe-Code</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control form-control-sm" id="mb-widget-url"
                               readonly value="<?php echo htmlspecialchars($mb_widget_iframe); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary btn-sm" id="mb-widget-copy-url" title="URL kopieren">
                                <i class="fa fa-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <textarea class="form-control form-control-sm mb-2" id="mb-widget-code" readonly rows="3"
                    ><?php echo htmlspecialchars('<iframe src="'.$mb_widget_iframe.'" width="300" height="400" frameborder="0" scrolling="auto" style="border-radius:8px;border:none;"></iframe>'); ?></textarea>
                    <button class="btn btn-sm btn-outline-secondary btn-block" id="mb-widget-copy-code">
                        <i class="fa fa-clipboard"></i> iframe-Code kopieren
                    </button>
                    <?php if ($mb_widget_token !== '' && !$mb_widget_public): ?>
                    <form method="post" action="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=widget_regen" class="mt-2">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning btn-block"
                                onclick="return confirm('Token wirklich neu generieren? Bestehende Einbettungen werden ungültig.')">
                            <i class="fa fa-refresh"></i> Token neu generieren
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
var MB_VIEWER_URL = '?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>&c=viewer_data';
var MB_SERVER_ID  = <?php echo (int)$mb_srv['id']; ?>;
</script>
<script src="system/js/mumble-edit.js"></script>
