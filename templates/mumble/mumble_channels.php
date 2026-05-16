<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble – Channel-Verwaltung (ICE)
 * File: templates/mumble/mumble_channels.php
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

if (!isset($mumble) || !($mumble instanceof mumble)) { return; }
if (!$mumble->canView()) { return; }

$mb_sid = (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) {
    echo '<div class="content-wrapper"><div class="container full-container"><div class="alert alert-warning mt-4">Server nicht gefunden oder keine Berechtigung.</div></div></div>';
    return;
}

$mb_csrf = (string)$loginsystem->getData('csrfToken');
$mb_name = htmlspecialchars((string)$mb_srv['name']);
?>
<div class="content-wrapper">
<div class="container full-container">

<h1 class="mt-4 mb-3">
    <i class="fa fa-sitemap"></i> <?php echo $mb_name; ?>
    <small class="text-muted">Channel-Verwaltung</small>
</h1>
<p class="text-muted mb-3">
    <a href="?p=mumble_edit&id=<?php echo $mb_sid; ?>">
        <i class="fa fa-arrow-left"></i> Zurück zum Server
    </a>
    &nbsp;&mdash;&nbsp;
    <i class="fa fa-check-circle text-success"></i>
    Änderungen werden <strong>sofort live</strong> übernommen.
</p>

<div id="mb-error-box"></div>

<div class="row">
    <!-- Channel-Baum -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="fa fa-sitemap"></i> Channels</span>
                <button class="btn btn-sm btn-success" id="mb-add-root-btn" title="Root-Channel hinzufügen">
                    <i class="fa fa-plus"></i>
                </button>
            </div>
            <div class="card-body p-2" id="mb-channel-tree" style="min-height:200px;max-height:600px;overflow-y:auto">
                <div class="text-muted small p-2"><i class="fa fa-spinner fa-spin"></i> Lade…</div>
            </div>
        </div>
    </div>

    <!-- Edit-Bereich -->
    <div class="col-md-8">
        <div id="mb-edit-placeholder" class="alert alert-info">
            <i class="fa fa-arrow-left"></i>&nbsp; Channel auswählen zum Bearbeiten,
            oder <a href="#" id="mb-add-root-link"><i class="fa fa-plus"></i> Root-Channel erstellen</a>.
        </div>

        <div id="mb-edit-panel" style="display:none">
            <div class="card">
                <div class="card-header py-2">
                    <strong id="mb-edit-title">Channel bearbeiten</strong>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control form-control-sm" id="mb-ch-name" maxlength="64">
                    </div>
                    <div class="form-group">
                        <label>Beschreibung</label>
                        <textarea class="form-control form-control-sm" id="mb-ch-desc" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Sortierungs-Position</label>
                        <input type="number" class="form-control form-control-sm" id="mb-ch-pos" value="0">
                    </div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-primary" id="mb-save-btn">
                            <i class="fa fa-save"></i> Speichern
                        </button>
                        <button class="btn btn-danger" id="mb-delete-btn">
                            <i class="fa fa-trash"></i> Channel löschen
                        </button>
                    </div>
                    <span id="mb-status" class="mt-2 d-block small"></span>
                </div>
            </div>

            <!-- Sub-Channel hinzufügen -->
            <div class="card mt-3">
                <div class="card-header py-2"><i class="fa fa-plus"></i> Sub-Channel hinzufügen</div>
                <div class="card-body">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="mb-new-sub-name" placeholder="Channel-Name" maxlength="64">
                        <div class="input-group-append">
                            <button class="btn btn-success" id="mb-add-sub-btn">
                                <i class="fa fa-plus"></i> Erstellen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Neuer Root-Channel -->
        <div id="mb-new-root-panel" style="display:none" class="card">
            <div class="card-header py-2"><i class="fa fa-plus"></i> Root-Channel erstellen</div>
            <div class="card-body">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="mb-new-root-name" placeholder="Channel-Name" maxlength="64">
                    <div class="input-group-append">
                        <button class="btn btn-success" id="mb-add-root-confirm">
                            <i class="fa fa-check"></i> Erstellen
                        </button>
                        <button class="btn btn-secondary" id="mb-add-root-cancel">Abbrechen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<script>
(function(){
'use strict';

var MB_SID    = <?php echo $mb_sid; ?>;
var MB_CSRF   = <?php echo json_encode($mb_csrf); ?>;
var MB_SEL_ID = null;
var MB_CHANNELS = {};

function esc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function el(id) { return document.getElementById(id); }

function showError(msg) {
    el('mb-error-box').innerHTML = '<div class="alert alert-danger">' + msg + '</div>';
    setTimeout(function(){ el('mb-error-box').innerHTML = ''; }, 6000);
}
function showStatus(msg, ok) {
    el('mb-status').innerHTML = '<span class="text-' + (ok ? 'success' : 'danger') + '">' + msg + '</span>';
    setTimeout(function(){ el('mb-status').innerHTML = ''; }, 4000);
}

function buildTreeHtml(channels, parentId, depth, ancestorIsLast) {
    var html = '';
    var children = Object.values(channels)
        .filter(function(ch){ return ch.parent === parentId && ch.id !== 0; })
        .sort(function(a,b){ return a.position - b.position || a.name.localeCompare(b.name); });
    children.forEach(function(ch, idx){
        var isLast = idx === children.length - 1;
        var prefix = '';
        for (var d = 0; d < ancestorIsLast.length; d++) {
            prefix += '<span style="display:inline-block;width:18px;color:#ced4da;text-align:center">'
                    + (ancestorIsLast[d] ? '&nbsp;' : '│') + '</span>';
        }
        if (depth > 0) {
            prefix += '<span style="color:#ced4da">' + (isLast ? '└' : '├') + '─</span>&nbsp;';
        }
        var icon = depth === 0
            ? '<i class="fa fa-folder fa-fw" style="color:#5a6268"></i>'
            : '<i class="fa fa-folder-o fa-fw" style="color:#adb5bd"></i>';
        html += '<div class="mb-chan-item py-1 px-2' + (ch.temporary ? ' text-muted' : '') + '"'
              + ' data-id="' + ch.id + '" style="cursor:pointer;padding-left:10px">'
              + prefix + icon + ' ' + esc(ch.name)
              + (ch.temporary ? ' <small class="text-muted">(temp)</small>' : '')
              + '</div>'
              + buildTreeHtml(channels, ch.id, depth + 1, ancestorIsLast.concat([isLast]));
    });
    return html;
}

function loadChannels() {
    fetch('?p=mumble_channels&c=channels_data&id=' + MB_SID)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data || !data.ok) {
                el('mb-channel-tree').innerHTML = '<div class="text-danger small p-2">' + esc(data ? data.error : 'Fehler') + '</div>';
                return;
            }
            MB_CHANNELS = data.channels;
            var html = '<div class="mb-chan-item d-flex justify-content-between align-items-center py-1 px-2" '
                     + 'data-id="0" style="cursor:pointer;font-weight:bold">'
                     + '<span><i class="fa fa-home fa-fw text-muted"></i> Root</span></div>';
            html += buildTreeHtml(MB_CHANNELS, 0, 0, []);
            el('mb-channel-tree').innerHTML = html;
        })
        .catch(function(){
            el('mb-channel-tree').innerHTML = '<div class="text-danger small p-2">Verbindungsfehler</div>';
        });
}

function selectChannel(cid) {
    MB_SEL_ID = cid;
    if (cid === 0) {
        el('mb-edit-placeholder').style.display = '';
        el('mb-edit-panel').style.display = 'none';
        return;
    }
    var ch = MB_CHANNELS[cid] || MB_CHANNELS[String(cid)];
    if (!ch) return;
    el('mb-edit-placeholder').style.display = 'none';
    el('mb-new-root-panel').style.display = 'none';
    el('mb-edit-title').textContent = 'Bearbeiten: ' + ch.name;
    el('mb-ch-name').value = ch.name;
    el('mb-ch-desc').value = ch.description || '';
    el('mb-ch-pos').value = ch.position || 0;
    el('mb-delete-btn').style.display = ch.temporary ? 'none' : '';
    el('mb-edit-panel').style.display = '';
}

el('mb-channel-tree').addEventListener('click', function(e){
    var item = e.target.closest('.mb-chan-item');
    if (!item) return;
    document.querySelectorAll('.mb-chan-item').forEach(function(n){ n.style.background = ''; n.style.fontWeight = ''; });
    item.style.background = '#e8f0fe';
    selectChannel(parseInt(item.getAttribute('data-id')));
});

el('mb-save-btn').addEventListener('click', function(){
    if (!MB_SEL_ID) return;
    var data = {
        csrf: MB_CSRF,
        channel_id: MB_SEL_ID,
        name: el('mb-ch-name').value.trim(),
        description: el('mb-ch-desc').value,
        position: parseInt(el('mb-ch-pos').value) || 0
    };
    if (!data.name) { showStatus('<i class="fa fa-times"></i> Name darf nicht leer sein.', false); return; }
    fetch('?p=mumble_channels&c=channel_update&id=' + MB_SID, {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
    })
    .then(function(r){ return r.json(); })
    .then(function(r){
        if (r.ok) { showStatus('<i class="fa fa-check"></i> Gespeichert.', true); loadChannels(); }
        else showStatus('<i class="fa fa-times"></i> ' + esc(r.error || 'Fehler'), false);
    })
    .catch(function(){ showStatus('<i class="fa fa-times"></i> Verbindungsfehler', false); });
});

el('mb-delete-btn').addEventListener('click', function(){
    if (!MB_SEL_ID) return;
    var ch = MB_CHANNELS[MB_SEL_ID] || MB_CHANNELS[String(MB_SEL_ID)];
    if (!confirm('Channel "' + (ch ? ch.name : MB_SEL_ID) + '" und alle Sub-Channels löschen?')) return;
    fetch('?p=mumble_channels&c=channel_delete&id=' + MB_SID, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: MB_CSRF, channel_id: MB_SEL_ID})
    })
    .then(function(r){ return r.json(); })
    .then(function(r){
        if (r.ok) {
            el('mb-edit-panel').style.display = 'none';
            el('mb-edit-placeholder').style.display = '';
            MB_SEL_ID = null;
            loadChannels();
        } else showError(esc(r.error || 'Löschen fehlgeschlagen'));
    })
    .catch(function(){ showError('Verbindungsfehler'); });
});

el('mb-add-sub-btn').addEventListener('click', function(){
    var name = el('mb-new-sub-name').value.trim();
    if (!name || !MB_SEL_ID) return;
    fetch('?p=mumble_channels&c=channel_add&id=' + MB_SID, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: MB_CSRF, name: name, parent: MB_SEL_ID})
    })
    .then(function(r){ return r.json(); })
    .then(function(r){
        if (r.ok) { el('mb-new-sub-name').value = ''; loadChannels(); }
        else showError(esc(r.error || 'Erstellen fehlgeschlagen'));
    })
    .catch(function(){ showError('Verbindungsfehler'); });
});

function showRootPanel() {
    el('mb-edit-placeholder').style.display = 'none';
    el('mb-edit-panel').style.display = 'none';
    el('mb-new-root-panel').style.display = '';
    el('mb-new-root-name').focus();
}
el('mb-add-root-btn').addEventListener('click', showRootPanel);
el('mb-add-root-link').addEventListener('click', function(e){ e.preventDefault(); showRootPanel(); });

el('mb-add-root-confirm').addEventListener('click', function(){
    var name = el('mb-new-root-name').value.trim();
    if (!name) return;
    fetch('?p=mumble_channels&c=channel_add&id=' + MB_SID, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: MB_CSRF, name: name, parent: 0})
    })
    .then(function(r){ return r.json(); })
    .then(function(r){
        if (r.ok) { el('mb-new-root-name').value = ''; el('mb-new-root-panel').style.display = 'none'; el('mb-edit-placeholder').style.display = ''; loadChannels(); }
        else showError(esc(r.error || 'Erstellen fehlgeschlagen'));
    })
    .catch(function(){ showError('Verbindungsfehler'); });
});

el('mb-add-root-cancel').addEventListener('click', function(){
    el('mb-new-root-panel').style.display = 'none';
    el('mb-edit-placeholder').style.display = '';
});

loadChannels();
})();
</script>
