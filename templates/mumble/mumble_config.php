<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble – Server-Konfiguration (Tabs)
 * File: templates/mumble/mumble_config.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) { return; }
if (!$mumble->canView()) { return; }

$mb_sid = (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);
$mb_uid = (int)$loginsystem->getUser('id');

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Server nicht gefunden oder keine Berechtigung.</div></div></div>';
    return;
}

$mb_csrf = (string)$loginsystem->getData('csrfToken');
$mb_cfg  = $mumble->getServerSettings($mb_sid) ?? [];

// Channel-Liste für Default-Channel-Dropdown (aus Viewer-Daten)
$mb_channels = [];
$mb_viewer = $mumble->getViewer($mb_sid);
if (!empty($mb_viewer['channels'])) {
    function mb_flatten_channels(array $ch, int $depth = 0): array {
        $out = [['id' => (int)($ch['id'] ?? 0), 'name' => str_repeat("\u{00A0}\u{00A0}", $depth).$ch['name']]];
        foreach ($ch['children'] ?? [] as $child) {
            $out = array_merge($out, mb_flatten_channels($child, $depth + 1));
        }
        return $out;
    }
    $mb_channels = mb_flatten_channels($mb_viewer['channels']);
}

function cfg(array $c, string $key, mixed $default = ''): mixed {
    return $c[$key] ?? $default;
}
?>
<div class="content-wrapper">
<div class="container full-container">
    <h1 class="mt-4 mb-3">
        <i class="fa fa-sliders"></i>
        <?php echo htmlspecialchars((string)$mb_srv['name']); ?>
        <small>Konfiguration</small>
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="?">Übersicht</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble">Mumble</a></li>
        <li class="breadcrumb-item"><a href="?p=mumble_edit&id=<?php echo $mb_sid; ?>">Details</a></li>
        <li class="breadcrumb-item active">Konfiguration</li>
    </ol>

    <?php echo $error ?? ''; ?>

    <form method="post" action="?p=mumble_config&id=<?php echo $mb_sid; ?>&c=save_settings">
    <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">

    <ul class="nav nav-tabs mb-3" id="cfgTabs">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-basis">Basis</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-reg">Registrierung</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-ban">Auto-Ban</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-adv">Erweitert</a></li>
    </ul>

    <div class="tab-content">

        <!-- TAB: Basis -->
        <div class="tab-pane fade show active" id="tab-basis">
        <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Audio &amp; Verbindung</div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Bandbreite pro User</label>
                        <select name="bandwidth" class="form-control form-control-sm">
                            <?php foreach ([72000=>'72 kbit/s (Sprache, schmal)',96000=>'96 kbit/s',130000=>'130 kbit/s (Standard)',192000=>'192 kbit/s (hoch)',320000=>'320 kbit/s (sehr hoch)'] as $v=>$l):
                            $sel = (int)cfg($mb_cfg,'bandwidth',130000)===$v?' selected':''; ?>
                            <option value="<?php echo $v; ?>"<?php echo $sel; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Timeout (Sekunden)</label>
                        <input type="number" name="timeout" class="form-control form-control-sm"
                               min="5" max="3600" value="<?php echo (int)cfg($mb_cfg,'timeout',30); ?>">
                        <small class="text-muted">Inaktive Verbindungen werden nach dieser Zeit getrennt.</small>
                    </div>
                    <div class="form-group">
                        <label>Opus-Schwellwert (%)</label>
                        <input type="number" name="opusthreshold" class="form-control form-control-sm"
                               min="0" max="100" value="<?php echo (int)cfg($mb_cfg,'opusthreshold',100); ?>">
                        <small class="text-muted">Ab diesem Anteil Opus-Clients wird Opus aktiviert.</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Limits &amp; Verhalten</div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Max. Textnachricht (Zeichen, 0=unbegrenzt)</label>
                        <input type="number" name="textmessagelength" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'textmessagelength',5000); ?>">
                    </div>
                    <div class="form-group">
                        <label>Max. Bildgröße in Nachrichten (Bytes, 0=unbegrenzt)</label>
                        <input type="number" name="imagemessagelength" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'imagemessagelength',131072); ?>">
                    </div>
                    <div class="form-group">
                        <label>Max. User pro Channel (0=unbegrenzt)</label>
                        <input type="number" name="usersperchannel" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'usersperchannel',0); ?>">
                    </div>
                    <div class="form-group">
                        <label>Standard-Channel</label>
                        <?php if ($mb_channels): ?>
                        <select name="defaultchannel" class="form-control form-control-sm">
                            <?php foreach ($mb_channels as $ch):
                                $sel = (int)cfg($mb_cfg,'defaultchannel',0) === $ch['id'] ? ' selected' : ''; ?>
                            <option value="<?php echo $ch['id']; ?>"<?php echo $sel; ?>>
                                <?php echo htmlspecialchars($ch['name']); ?> (ID <?php echo $ch['id']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="number" name="defaultchannel" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'defaultchannel',0); ?>">
                        <small class="text-muted">Channel-Liste nicht verfügbar (Server offline?).</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="allowhtml" id="cfg_allowhtml" value="1"
                               <?php echo cfg($mb_cfg,'allowhtml','true')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_allowhtml">HTML in Textnachrichten erlauben</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="rememberchannel" id="cfg_remember" value="1"
                               <?php echo cfg($mb_cfg,'rememberchannel','true')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_remember">Letzten Channel merken</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="certrequired" id="cfg_cert" value="1"
                               <?php echo cfg($mb_cfg,'certrequired','false')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_cert">Client-Zertifikat vorschreiben</label>
                    </div>
                </div>
            </div>
        </div>
        </div>
        </div><!-- /tab-basis -->

        <!-- TAB: Registrierung -->
        <div class="tab-pane fade" id="tab-reg">
        <div class="card mb-4">
            <div class="card-header">Öffentliche Mumble-Serverliste</div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Damit der Server in der offiziellen Mumble-Serverliste erscheint, müssen alle Felder
                    ausgefüllt sein, das Server-Passwort muss leer sein und der Server muss öffentlich erreichbar sein.
                </p>
                <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Anzeigename in der Liste</label>
                        <input type="text" name="register_name" class="form-control form-control-sm" maxlength="255"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'register_name','')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Registrierungs-Passwort</label>
                        <input type="text" name="register_password" class="form-control form-control-sm"
                               maxlength="255" autocomplete="off"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'register_password','')); ?>">
                        <small class="text-muted">Wird für Updates der Registrierung benötigt.</small>
                    </div>
                    <div class="form-group">
                        <label>Website-URL</label>
                        <input type="url" name="register_url" class="form-control form-control-sm" maxlength="512"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'register_url','')); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Hostname für Registrierung</label>
                        <input type="text" name="register_hostname" class="form-control form-control-sm" maxlength="255"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'register_hostname','')); ?>">
                        <small class="text-muted">Leer lassen wenn identisch mit Server-Hostname.</small>
                    </div>
                    <div class="form-group">
                        <label>Standort (z.B. DE, US)</label>
                        <input type="text" name="register_location" class="form-control form-control-sm" maxlength="64"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'register_location','')); ?>">
                    </div>
                </div>
                </div>
            </div>
        </div>
        </div><!-- /tab-reg -->

        <!-- TAB: Auto-Ban -->
        <div class="tab-pane fade" id="tab-ban">
        <div class="card mb-4">
            <div class="card-header">Automatischer Bann bei Brute-Force</div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Eine IP wird gebannt wenn <strong>Versuche</strong> fehlschlagen innerhalb von
                    <strong>Zeitfenster</strong> Sekunden. Auf 0 setzen zum Deaktivieren.
                </p>
                <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Fehlversuche (0=deaktiviert)</label>
                        <input type="number" name="autoban_attempts" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'autoban_attempts',10); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Zeitfenster (Sekunden)</label>
                        <input type="number" name="autoban_timeframe" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'autoban_timeframe',120); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Bann-Dauer (Sekunden)</label>
                        <input type="number" name="autoban_time" class="form-control form-control-sm"
                               min="0" value="<?php echo (int)cfg($mb_cfg,'autoban_time',300); ?>">
                    </div>
                </div>
                </div>
            </div>
        </div>
        </div><!-- /tab-ban -->

        <!-- TAB: Erweitert -->
        <div class="tab-pane fade" id="tab-adv">
        <div class="card mb-4">
            <div class="card-header">Erweiterte Einstellungen</div>
            <div class="card-body">
                <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Empfohlene Client-Version (leer=keine Empfehlung)</label>
                        <input type="text" name="suggestversion" class="form-control form-control-sm"
                               maxlength="32" placeholder="z.B. 1.5.0"
                               value="<?php echo htmlspecialchars((string)cfg($mb_cfg,'suggestversion','')); ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="suggestpositional" id="cfg_pos" value="1"
                               <?php echo cfg($mb_cfg,'suggestpositional','false')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_pos">Positional Audio empfehlen</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="suggestpushtotalk" id="cfg_ptt" value="1"
                               <?php echo cfg($mb_cfg,'suggestpushtotalk','false')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_ptt">Push-to-Talk empfehlen</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="sendversion" id="cfg_sendv" value="1"
                               <?php echo cfg($mb_cfg,'sendversion','true')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_sendv">Server-Version an Clients senden</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="bonjour" id="cfg_bonjour" value="1"
                               <?php echo cfg($mb_cfg,'bonjour','false')==='true'?' checked':''; ?>>
                        <label class="form-check-label" for="cfg_bonjour">Bonjour/ZeroConf-Ankündigung</label>
                    </div>
                </div>
                </div>
            </div>
        </div>
        </div><!-- /tab-adv -->

    </div><!-- /tab-content -->

    <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i> Einstellungen speichern &amp; Server neustarten
    </button>
    <a href="?p=mumble_edit&id=<?php echo $mb_sid; ?>" class="btn btn-secondary ml-2">
        <i class="fa fa-arrow-left"></i> Zurück
    </a>
    </form>

    <!-- Zertifikat -->
    <div class="card mt-4 mb-4">
        <div class="card-header"><i class="fa fa-lock"></i> TLS-Zertifikat</div>
        <div class="card-body">
            <?php $hasCert = !empty($mb_cfg['ssl_cert']); ?>
            <?php if ($hasCert): ?>
            <div class="alert alert-success py-2 mb-3">
                <i class="fa fa-check"></i> Eigenes Zertifikat aktiv
                <form method="post" action="?p=mumble_config&id=<?php echo $mb_sid; ?>&c=cert_remove"
                      class="d-inline ml-3" id="mb-form-cert-remove">
                    <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fa fa-trash"></i> Entfernen
                    </button>
                </form>
            </div>
            <?php else: ?>
            <p class="small text-muted mb-3">
                Aktuell wird ein selbst-signiertes Zertifikat verwendet. Lade hier ein eigenes PEM-Zertifikat hoch.<br>
                <strong>Tipp für dns-mgr-Nutzer:</strong> <code>mumble-cert-deploy</code> auf dem Host ausführen um alle Server gleichzeitig zu aktualisieren.
            </p>
            <?php endif; ?>
            <form method="post" action="?p=mumble_config&id=<?php echo $mb_sid; ?>&c=cert_upload">
                <input type="hidden" name="csrf" value="<?php echo $mb_csrf; ?>">
                <div class="form-group">
                    <label>Zertifikat (PEM, inkl. Chain)</label>
                    <textarea name="cert_pem" class="form-control form-control-sm" rows="5"
                              placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                </div>
                <div class="form-group">
                    <label>Privater Schlüssel (PEM)</label>
                    <textarea name="key_pem" class="form-control form-control-sm" rows="5"
                              placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fa fa-upload"></i> Zertifikat hochladen &amp; aktivieren
                </button>
            </form>
        </div>
    </div>

</div>
</div>
