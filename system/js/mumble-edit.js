(function () {
    'use strict';

    // --- SuperUser PW reveal toggle ---
    var f  = document.getElementById('mb-supw-field');
    var b  = document.getElementById('mb-supw-toggle');
    var ic = document.getElementById('mb-supw-icon');
    var pw = f ? (f.getAttribute('data-pw') || '') : '';
    var shown = false;

    if (f && b) {
        b.addEventListener('click', function () {
            shown = !shown;
            f.value = shown ? (pw || '(unbekannt)') : '••••••••••••';
            ic.className = shown ? 'fa fa-eye-slash' : 'fa fa-eye';
        });
    }

    var cb = document.getElementById('mb-supw-copy');
    if (cb) {
        cb.addEventListener('click', function () {
            if (!pw) return;
            var tmp = document.createElement('input');
            tmp.value = pw;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            cb.innerHTML = '<i class="fa fa-check"></i>';
            setTimeout(function () { cb.innerHTML = '<i class="fa fa-clipboard"></i>'; }, 2000);
        });
    }

    // --- Confirm-Dialoge (CSP-konform, kein inline onsubmit) ---
    var formReset = document.getElementById('mb-form-supw-reset');
    if (formReset) {
        formReset.addEventListener('submit', function (e) {
            if (!confirm('SuperUser-Passwort wirklich zurücksetzen? Das alte ist danach ungültig.')) {
                e.preventDefault();
            }
        });
    }

    var formDelete = document.getElementById('mb-form-delete');
    if (formDelete) {
        formDelete.addEventListener('submit', function (e) {
            if (!confirm('Server wirklich endgültig löschen?')) {
                e.preventDefault();
            }
        });
    }

    // --- Widget Copy-Buttons ---
    function copyText(id, btnId) {
        var el = document.getElementById(id);
        var btn = document.getElementById(btnId);
        if (!el || !btn) return;
        btn.addEventListener('click', function () {
            var tmp = document.createElement('textarea');
            tmp.value = el.value || el.textContent;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            btn.innerHTML = '<i class="fa fa-check"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="fa fa-clipboard"></i>'; }, 2000);
        });
    }
    copyText('mb-widget-url', 'mb-widget-copy-url');
    copyText('mb-widget-code', 'mb-widget-copy-code');

    // --- Channel-Viewer ---
    var viewerBox = document.getElementById('mb-viewer-content');
    var viewerUrl = (typeof MB_VIEWER_URL !== 'undefined') ? MB_VIEWER_URL : null;
    var refreshTimer = null;
    var refreshInterval = 0; // 0 = kein Auto-Refresh (wird nach erstem Load gesetzt)

    function renderChannel(ch, depth) {
        depth = depth || 0;
        var indent = depth * 14;
        var html = '';
        var icon = depth === 0 ? '🔊' : '📁';
        html += '<div style="padding-left:' + indent + 'px;display:flex;align-items:center;gap:5px;padding-top:3px;padding-bottom:3px;font-weight:600;color:#4a90d9;">';
        html += '<span style="font-size:12px;">' + icon + '</span>';
        html += '<span>' + escHtml(ch.name) + '</span></div>';
        (ch.users || []).forEach(function (u) {
            html += '<div style="padding-left:' + (indent + 16) + 'px;display:flex;align-items:center;gap:5px;padding-top:2px;padding-bottom:2px;color:#555;">';
            html += '<span style="font-size:12px;">🎧</span><span>' + escHtml(u) + '</span></div>';
        });
        (ch.children || []).forEach(function (child) {
            html += renderChannel(child, depth + 1);
        });
        return html;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function loadViewer() {
        if (!viewerBox || !viewerUrl) return;
        fetch(viewerUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.channels) {
                    viewerBox.innerHTML = '<p class="text-muted small p-3 mb-0">Server nicht erreichbar.</p>';
                    return;
                }
                var html = '<div style="padding:8px;font-family:\'Segoe UI\',sans-serif;font-size:13px;">';
                html += '<div style="margin-bottom:6px;font-size:11px;color:#888;">';
                html += '<strong>' + (data.user_count || 0) + '</strong> Nutzer online';
                html += '</div>';
                html += renderChannel(data.channels);
                html += '</div>';
                viewerBox.innerHTML = html;

                // Refresh-Intervall aus den Widget-Settings lesen (via data-Attribut)
                var ri = parseInt(viewerBox.getAttribute('data-refresh') || '0', 10);
                if (ri > 0 && !refreshTimer) {
                    refreshTimer = setInterval(loadViewer, ri * 1000);
                }
            })
            .catch(function () {
                viewerBox.innerHTML = '<p class="text-muted small p-3 mb-0">Viewer nicht verfügbar.</p>';
            });
    }

    var refreshBtn = document.getElementById('mb-viewer-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadViewer);
    }

    // Refresh-Intervall aus Widget-Settings (data-Attribut wird vom Template gesetzt)
    if (viewerBox) {
        var ri = parseInt(viewerBox.getAttribute('data-refresh') || '0', 10);
        if (ri > 0) {
            refreshTimer = setInterval(loadViewer, ri * 1000);
        }
    }

    if (viewerBox && viewerUrl) {
        loadViewer();
    }

})();
