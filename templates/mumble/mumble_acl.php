<?php
declare(strict_types=1);
/********************************************
 * Easy2-Mumble – ACL-Verwaltung
 * File: templates/mumble/mumble_acl.php
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
    <i class="fa fa-key"></i> <?php echo $mb_name; ?>
    <small class="text-muted">ACL-Verwaltung</small>
</h1>
<p class="text-muted mb-3">
    <a href="?p=mumble_edit&id=<?php echo $mb_sid; ?>">
        <i class="fa fa-arrow-left"></i> Zurück zum Server
    </a>
    &nbsp;&mdash;&nbsp;
    <i class="fa fa-check-circle text-success"></i>
    Änderungen werden <strong>sofort</strong> übernommen — kein Server-Neustart nötig.
</p>

<div class="row">
    <!-- Channel-Baum -->
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-header py-2">
                <i class="fa fa-sitemap"></i> Channels
            </div>
            <div class="card-body p-2" id="mb-channel-tree" style="max-height:600px;overflow-y:auto">
                <div class="text-muted small p-2"><i class="fa fa-spinner fa-spin"></i> Lade...</div>
            </div>
        </div>
    </div>

    <!-- ACL-Editor -->
    <div class="col-md-9">
        <div id="mb-acl-placeholder" class="alert alert-info">
            <i class="fa fa-arrow-left"></i>&nbsp;
            Channel im Baum auswählen um dessen ACL zu bearbeiten.
        </div>

        <div id="mb-acl-editor" style="display:none">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong><i class="fa fa-lock"></i> ACL: <span id="mb-channel-name">-</span></strong>
                    <label class="mb-0 small font-weight-normal">
                        <input type="checkbox" id="mb-inherit-acl">
                        Von Eltern-Channel erben
                    </label>
                </div>
                <div class="card-body">

                    <!-- ACL-Einträge -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">ACL-Einträge</h6>
                        <button class="btn btn-sm btn-success" id="mb-add-acl">
                            <i class="fa fa-plus"></i> Eintrag
                        </button>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0" id="mb-acl-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Ziel</th>
                                    <th class="text-center" title="Gilt für diesen Channel">Hier</th>
                                    <th class="text-center" title="Gilt für Unter-Channels">Sub</th>
                                    <th>Erteilt</th>
                                    <th>Verweigert</th>
                                    <th class="text-center">Rechte</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="mb-acl-body"></tbody>
                        </table>
                    </div>

                    <!-- Gruppen -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Gruppen</h6>
                        <button class="btn btn-sm btn-success" id="mb-add-group">
                            <i class="fa fa-plus"></i> Gruppe
                        </button>
                    </div>
                    <div id="mb-groups-container" class="mb-4"></div>

                    <!-- Speichern -->
                    <div class="border-top pt-3">
                        <button class="btn btn-primary" id="mb-save-btn">
                            <i class="fa fa-save"></i> Speichern
                        </button>
                        <span id="mb-save-status" class="ml-3 small"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<!-- Modal: Rechte bearbeiten -->
<div class="modal fade" id="mb-perm-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="fa fa-key"></i> Rechte: <strong id="mb-modal-target">-</strong>
                </h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3" id="mb-modal-body"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary btn-sm" id="mb-modal-apply">Übernehmen</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

var MB_SID      = <?php echo $mb_sid; ?>;
var MB_CSRF     = <?php echo json_encode($mb_csrf); ?>;
var MB_CHAN_ID  = 0;
var MB_DATA     = null;
var MB_EDIT_IDX = -1;

var PERMS = [
    {bit:4,      label:'Betreten'},
    {bit:8,      label:'Sprechen'},
    {bit:256,    label:'Flüstern'},
    {bit:16,     label:'Stumm/Taub'},
    {bit:32,     label:'Verschieben'},
    {bit:64,     label:'Channel+'},
    {bit:512,    label:'Textnachricht'},
    {bit:1024,   label:'Temp-Channel'},
    {bit:2048,   label:'Lauschen'},
    {bit:1,      label:'Admin (alle)'},
    {bit:65536,  label:'Kicken'},
    {bit:131072, label:'Bannen'},
    {bit:262144, label:'Registrieren'},
    {bit:524288, label:'Selbst-Reg.'}
];

function esc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function el(id) { return document.getElementById(id); }

function permBadges(mask, cls) {
    if (!mask) return '<span class="text-muted">–</span>';
    return PERMS.filter(function(p){ return mask & p.bit; })
        .map(function(p){ return '<span class="badge badge-' + cls + ' mr-1">' + p.label + '</span>'; })
        .join('');
}

function modalShow() {
    var m = el('mb-perm-modal');
    m.style.display = 'block';
    m.classList.add('show');
    var bd = document.createElement('div');
    bd.className = 'modal-backdrop fade show';
    bd.id = 'mb-modal-backdrop';
    document.body.appendChild(bd);
    document.body.classList.add('modal-open');
}
function modalHide() {
    var m = el('mb-perm-modal');
    m.style.display = 'none';
    m.classList.remove('show');
    var bd = el('mb-modal-backdrop');
    if (bd) bd.parentNode.removeChild(bd);
    document.body.classList.remove('modal-open');
}

function buildTreeHtml(ch, depth) {
    var indent = depth * 12;
    var icon = ch.children && ch.children.length ? 'fa-folder-open' : 'fa-volume-up';
    var html = '<div class="mb-chan-node" data-id="' + ch.id + '" data-name="' + esc(ch.name) + '"'
             + ' style="cursor:pointer;padding:4px 4px 4px ' + (indent + 4) + 'px"'
             + ' title="Channel-ID: ' + ch.id + '">'
             + '<i class="fa ' + icon + ' fa-fw text-muted"></i> ' + esc(ch.name)
             + '</div>';
    if (ch.children) {
        ch.children.forEach(function(c){ html += buildTreeHtml(c, depth + 1); });
    }
    return html;
}

function loadChannelTree() {
    fetch('?p=mumble_edit&c=viewer_data&id=' + MB_SID)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data || !data.channels) {
                el('mb-channel-tree').innerHTML = '<div class="text-danger small p-2">Fehler beim Laden.</div>';
                return;
            }
            el('mb-channel-tree').innerHTML = buildTreeHtml(data.channels, 0);
        })
        .catch(function(){
            el('mb-channel-tree').innerHTML = '<div class="text-danger small p-2">Server nicht erreichbar.</div>';
        });
}

el('mb-channel-tree').addEventListener('click', function(e){
    var node = e.target.closest('.mb-chan-node');
    if (!node) return;
    document.querySelectorAll('.mb-chan-node').forEach(function(n){ n.style.background = ''; n.style.fontWeight = 'normal'; });
    node.style.background = '#e8f0fe';
    node.style.fontWeight = 'bold';
    loadAcl(parseInt(node.getAttribute('data-id')), node.getAttribute('data-name'));
});

function loadAcl(channelId, channelName) {
    MB_CHAN_ID = channelId;
    el('mb-acl-placeholder').style.display = '';
    el('mb-acl-editor').style.display = 'none';
    el('mb-save-status').textContent = '';

    fetch('?p=mumble_acl&c=acl_data&id=' + MB_SID + '&channel_id=' + channelId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data || !data.ok) {
                var ph = el('mb-acl-placeholder');
                ph.className = 'alert alert-danger';
                ph.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Fehler: ' + esc(data ? data.error : 'unbekannt');
                return;
            }
            MB_DATA = data;
            el('mb-channel-name').textContent = channelName;
            el('mb-inherit-acl').checked = data.inherit_acl;
            renderAclRows();
            renderGroups();
            el('mb-acl-placeholder').style.display = 'none';
            el('mb-acl-editor').style.display = '';
        })
        .catch(function(){
            el('mb-acl-placeholder').innerHTML = '<i class="fa fa-exclamation-triangle"></i> Verbindungsfehler.';
        });
}

function renderAclRows() {
    var html = '';
    MB_DATA.acl.forEach(function(e, i){
        var target;
        if (!e.group && e.user_id !== null) {
            var u = (MB_DATA.registered_users || []).find(function(u){ return u.id === e.user_id; });
            target = esc(u ? u.name : ('ID ' + e.user_id)) + ' <small class="text-muted">(User)</small>';
        } else {
            target = esc(e.group);
        }
        html += '<tr>'
              + '<td class="align-middle" style="min-width:120px">' + target + '</td>'
              + '<td class="text-center align-middle">' + (e.apply_here ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>') + '</td>'
              + '<td class="text-center align-middle">' + (e.apply_sub  ? '<i class="fa fa-check text-success"></i>' : '<i class="fa fa-times text-muted"></i>') + '</td>'
              + '<td class="align-middle" style="max-width:180px">' + permBadges(e.grant, 'success') + '</td>'
              + '<td class="align-middle" style="max-width:180px">' + permBadges(e.deny, 'danger') + '</td>'
              + '<td class="text-center align-middle"><button class="btn btn-xs btn-outline-primary mb-edit-acl" data-idx="' + i + '"><i class="fa fa-pencil"></i></button></td>'
              + '<td class="text-center align-middle"><button class="btn btn-xs btn-outline-danger mb-del-acl" data-idx="' + i + '"><i class="fa fa-trash"></i></button></td>'
              + '</tr>';
    });
    if (!MB_DATA.acl.length) {
        html = '<tr><td colspan="7" class="text-center text-muted small py-2">Keine ACL-Einträge</td></tr>';
    }
    el('mb-acl-body').innerHTML = html;
}

function renderGroups() {
    var container = el('mb-groups-container');
    if (!MB_DATA.groups.length) {
        container.innerHTML = '<div class="text-muted small">Keine Gruppen für diesen Channel.</div>';
        return;
    }
    var html = '';
    MB_DATA.groups.forEach(function(g, gi){
        var memberBadges = g.members_add.map(function(uid, mi){
            var u = (MB_DATA.registered_users || []).find(function(u){ return u.id === uid; });
            return '<span class="badge badge-secondary mr-1 mb-1">' + esc(u ? u.name : ('ID ' + uid))
                 + ' <a href="#" class="text-white mb-rm-member" data-gi="' + gi + '" data-mi="' + mi + '" style="text-decoration:none">&times;</a></span>';
        }).join('') || '<span class="text-muted small">Keine Mitglieder</span>';

        var userOpts = (MB_DATA.registered_users || []).map(function(u){
            return '<option value="' + u.id + '">' + esc(u.name) + '</option>';
        }).join('');

        html += '<div class="card mb-2">'
              + '<div class="card-header py-1 d-flex justify-content-between align-items-center">'
              + '<span><i class="fa fa-users fa-fw"></i> <strong>' + esc(g.name) + '</strong></span>'
              + '<button class="btn btn-xs btn-outline-danger mb-del-grp" data-gi="' + gi + '"><i class="fa fa-trash"></i></button>'
              + '</div><div class="card-body py-2"><div class="row">'
              + '<div class="col-md-6">'
              + '<label class="mb-0 small mr-3"><input type="checkbox" class="mb-grp-inherit" data-gi="' + gi + '"' + (g.inherit ? ' checked' : '') + '> Von Eltern-Channel erben</label>'
              + '<label class="mb-0 small"><input type="checkbox" class="mb-grp-inheritable" data-gi="' + gi + '"' + (g.inheritable ? ' checked' : '') + '> An Kind-Channels vererben</label>'
              + '</div><div class="col-md-6 text-right">'
              + '<select class="form-control form-control-sm d-inline-block mb-add-member-sel" data-gi="' + gi + '" style="width:auto">' + userOpts + '</select> '
              + '<button class="btn btn-sm btn-outline-success mb-add-member" data-gi="' + gi + '"><i class="fa fa-plus"></i> Mitglied</button>'
              + '</div></div><div class="mt-2">' + memberBadges + '</div></div></div>';
    });
    container.innerHTML = html;
}

function openPermModal(idx) {
    MB_EDIT_IDX = idx;
    var e = MB_DATA.acl[idx];
    var html = '<div class="row">';
    PERMS.forEach(function(p, i){
        var g = (e.grant & p.bit) ? 'checked' : '';
        var d = (e.deny  & p.bit) ? 'checked' : '';
        if (i % 2 === 0 && i > 0) html += '</div><div class="row">';
        html += '<div class="col-md-6 mb-2"><div class="d-flex align-items-center border rounded p-2">'
              + '<span class="flex-grow-1 small font-weight-bold">' + p.label + '</span>'
              + '<label class="mb-0 mr-3 small text-success"><input type="radio" name="perm_' + p.bit + '" value="grant" ' + (g||'') + '> G</label>'
              + '<label class="mb-0 mr-3 small text-danger"><input type="radio" name="perm_' + p.bit + '" value="deny" ' + (d||'') + '> D</label>'
              + '<label class="mb-0 small text-muted"><input type="radio" name="perm_' + p.bit + '" value="none" ' + (!g && !d ? 'checked' : '') + '> –</label>'
              + '</div></div>';
    });
    html += '</div><hr class="my-2">'
          + '<label class="mr-4"><input type="checkbox" id="mb-modal-here" ' + (e.apply_here ? 'checked' : '') + '> Gilt für diesen Channel</label>'
          + '<label><input type="checkbox" id="mb-modal-sub" ' + (e.apply_sub ? 'checked' : '') + '> Gilt für Unter-Channels</label>';

    el('mb-modal-target').textContent = e.group ? e.group : ('User-ID ' + e.user_id);
    el('mb-modal-body').innerHTML = html;
    modalShow();
}

el('mb-modal-apply').addEventListener('click', function(){
    if (MB_EDIT_IDX < 0 || !MB_DATA) return;
    var e = MB_DATA.acl[MB_EDIT_IDX];
    var grant = 0, deny = 0;
    PERMS.forEach(function(p){
        var v = document.querySelector('input[name="perm_' + p.bit + '"]:checked');
        if (v && v.value === 'grant') grant |= p.bit;
        if (v && v.value === 'deny')  deny  |= p.bit;
    });
    e.grant = grant; e.deny = deny;
    e.apply_here = el('mb-modal-here').checked;
    e.apply_sub  = el('mb-modal-sub').checked;
    MB_DATA.acl[MB_EDIT_IDX] = e;
    renderAclRows();
    modalHide();
});

el('mb-perm-modal').addEventListener('click', function(e){ if (e.target === this) modalHide(); });
el('mb-perm-modal').querySelector('[data-dismiss="modal"]').addEventListener('click', modalHide);

el('mb-acl-body').addEventListener('click', function(e){
    var edit = e.target.closest('.mb-edit-acl');
    var del  = e.target.closest('.mb-del-acl');
    if (edit) openPermModal(parseInt(edit.getAttribute('data-idx')));
    if (del)  { MB_DATA.acl.splice(parseInt(del.getAttribute('data-idx')), 1); renderAclRows(); }
});

el('mb-add-acl').addEventListener('click', function(){
    if (!MB_DATA) return;
    MB_DATA.acl.push({group: '@all', user_id: null, apply_here: true, apply_sub: true, grant: 0, deny: 0});
    renderAclRows();
    openPermModal(MB_DATA.acl.length - 1);
});

el('mb-add-group').addEventListener('click', function(){
    if (!MB_DATA) return;
    var name = prompt('Gruppenname (ohne @):');
    if (!name || !name.trim()) return;
    MB_DATA.groups.push({name: name.trim(), inherit: true, inheritable: true, members_add: [], members_remove: []});
    renderGroups();
});

el('mb-groups-container').addEventListener('click', function(e){
    var del = e.target.closest('.mb-del-grp');
    var add = e.target.closest('.mb-add-member');
    var rm  = e.target.closest('.mb-rm-member');
    if (del) { e.preventDefault(); MB_DATA.groups.splice(parseInt(del.getAttribute('data-gi')), 1); renderGroups(); }
    if (add) {
        var gi  = parseInt(add.getAttribute('data-gi'));
        var sel = el('mb-groups-container').querySelector('.mb-add-member-sel[data-gi="' + gi + '"]');
        var uid = parseInt(sel.value);
        if (uid && MB_DATA.groups[gi].members_add.indexOf(uid) === -1) { MB_DATA.groups[gi].members_add.push(uid); renderGroups(); }
    }
    if (rm) {
        e.preventDefault();
        MB_DATA.groups[parseInt(rm.getAttribute('data-gi'))].members_add.splice(parseInt(rm.getAttribute('data-mi')), 1);
        renderGroups();
    }
});

el('mb-groups-container').addEventListener('change', function(e){
    if (e.target.classList.contains('mb-grp-inherit'))
        MB_DATA.groups[parseInt(e.target.getAttribute('data-gi'))].inherit = e.target.checked;
    if (e.target.classList.contains('mb-grp-inheritable'))
        MB_DATA.groups[parseInt(e.target.getAttribute('data-gi'))].inheritable = e.target.checked;
});

el('mb-save-btn').addEventListener('click', function(){
    if (!MB_DATA) return;
    var payload = {
        csrf: MB_CSRF, channel_id: MB_CHAN_ID,
        inherit_acl: el('mb-inherit-acl').checked,
        acl: MB_DATA.acl, groups: MB_DATA.groups
    };
    var btn = el('mb-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Speichere...';
    el('mb-save-status').textContent = '';

    fetch('?p=mumble_acl&c=save_acl&id=' + MB_SID, {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (data && data.ok) {
            el('mb-save-status').innerHTML = '<span class="text-success"><i class="fa fa-check"></i> Gespeichert.</span>';
            setTimeout(function(){ loadAcl(MB_CHAN_ID, el('mb-channel-name').textContent); }, 1000);
        } else {
            el('mb-save-status').innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Fehler: ' + esc(data ? data.error : 'unbekannt') + '</span>';
        }
    })
    .catch(function(){
        el('mb-save-status').innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Verbindungsfehler.</span>';
    })
    .finally(function(){ btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Speichern'; });
});

loadChannelTree();
})();
</script>
