<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble - Server-Config (INI) bearbeiten
 * File: templates/mumble/mumble_config.php
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

$mb_csrf = (string)$loginsystem->getData('csrfToken');

// Config vom Agent laden
$mb_cfg = $mumble->getServerConfig($mb_sid);
$mb_ini = '';
if ($mb_cfg['ok'] && isset($mb_cfg['data']['content'])) {
    $mb_ini = (string)$mb_cfg['data']['content'];
} elseif (!$mb_cfg['ok']) {
    $mb_ini = '# Fehler beim Laden: '.htmlspecialchars((string)($mb_cfg['error'] ?? 'unbekannt'));
}
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3">
        <i class="fa fa-file-code-o"></i>
        Server-Config <small><?php echo htmlspecialchars((string)$mb_srv['name']); ?></small>
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">&Uuml;bersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>">
            <?php echo htmlspecialchars((string)$mb_srv['name']); ?>
        </a></li>
        <li class="breadcrumb-item active">Config</li>
    </ol>

    <?php echo $error; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-file-text-o"></i> mumble_server_config.ini</span>
                    <span class="badge badge-info">Port <?php echo (int)$mb_srv['port']; ?> (nicht änderbar)</span>
                </div>
                <div class="card-body p-0">
                    <form method="post" action="?p=mumble_config&id=<?php echo (int)$mb_srv['id']; ?>&c=save_config"
                          onsubmit="return confirm('Config speichern und Server neustarten?');">
                        <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                        <textarea name="config_content" id="mb-config-editor"
                                  class="form-control border-0"
                                  style="font-family:'Courier New',monospace;font-size:13px;line-height:1.5;min-height:500px;resize:vertical;tab-size:4;background:#1e1e1e;color:#dcdcdc;"
                                  spellcheck="false"><?php echo htmlspecialchars($mb_ini); ?></textarea>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fa fa-exclamation-triangle text-warning"></i>
                                Änderungen am <code>port</code> und <code>database</code> werden vom Agent abgelehnt.
                            </small>
                            <div>
                                <a href="?p=mumble_edit&id=<?php echo (int)$mb_srv['id']; ?>" class="btn btn-secondary">
                                    <i class="fa fa-times"></i> Abbrechen
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Speichern &amp; Neustart
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-info-circle"></i> Hinweise</div>
                <div class="card-body">
                    <p class="small">Die Datei <code>mumble_server_config.ini</code> steuert das
                    Verhalten des Mumble-Servers. Änderungen werden erst nach dem Neustart aktiv.</p>

                    <p class="small mb-1"><strong>Wichtige Felder:</strong></p>
                    <table class="table table-sm small mb-3">
                        <tr><td><code>users</code></td><td>Max. gleichzeitige Nutzer</td></tr>
                        <tr><td><code>bandwidth</code></td><td>Max. Bandbreite pro User (Bit/s)</td></tr>
                        <tr><td><code>welcometext</code></td><td>Begrüßungstext (HTML erlaubt)</td></tr>
                        <tr><td><code>serverpassword</code></td><td>Verbindungs-Passwort (leer = öffentlich)</td></tr>
                        <tr><td><code>registerName</code></td><td>Servername in der Serverliste</td></tr>
                        <tr><td><code>opusthreshold</code></td><td>Ab wie vielen Usern Opus erzwungen wird</td></tr>
                        <tr><td><code>timeout</code></td><td>Timeout für inaktive Clients (Sek.)</td></tr>
                        <tr><td><code>textmessagelength</code></td><td>Max. Zeichen in Chat-Nachrichten</td></tr>
                        <tr><td><code>allowhtml</code></td><td>HTML in Nachrichten erlauben (true/false)</td></tr>
                    </table>

                    <p class="small text-danger mb-0">
                        <i class="fa fa-warning"></i>
                        <strong>Nicht ändern:</strong> <code>port</code>, <code>database</code>, <code>ice</code> — diese
                        Werte sind systemkritisch und werden bei Fehleingaben vom Agent abgelehnt.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fa fa-external-link"></i> Dokumentation</div>
                <div class="card-body">
                    <a href="https://wiki.mumble.info/wiki/Murmur.ini" target="_blank"
                       class="btn btn-outline-primary btn-block btn-sm">
                        <i class="fa fa-book"></i> Mumble Wiki: murmur.ini Referenz
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// Tab-Key im Textarea erlauben statt Fokus-Wechsel
(function () {
    var ta = document.getElementById('mb-config-editor');
    if (!ta) return;
    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            var s = this.selectionStart, end = this.selectionEnd;
            this.value = this.value.substring(0, s) + '\t' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = s + 1;
        }
    });
})();
</script>
