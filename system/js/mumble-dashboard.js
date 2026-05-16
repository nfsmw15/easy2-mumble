(function () {
    'use strict';

    // ── Hilfsfunktionen ──────────────────────────────────────────────────────

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;');
    }

    /** Bytes/s → lesbarer String (B/s, KB/s, MB/s) */
    function fmtBw(bps) {
        bps = bps || 0;
        if (bps <= 0)           return '0 B/s';
        if (bps < 1024)         return bps + ' B/s';
        if (bps < 1048576)      return (bps / 1024).toFixed(1) + ' KB/s';
        return (bps / 1048576).toFixed(2) + ' MB/s';
    }

    /** Sekunden → "5m", "2h 15m", "3d 4h" */
    function fmtTime(secs) {
        secs = secs || 0;
        if (secs <= 0) return '–';
        var d = Math.floor(secs / 86400);
        var h = Math.floor((secs % 86400) / 3600);
        var m = Math.floor((secs % 3600)  / 60);
        var parts = [];
        if (d > 0) parts.push(d + 'd');
        if (h > 0) parts.push(h + 'h');
        if (m > 0) parts.push(m + 'm');
        if (parts.length === 0) parts.push(secs + 's');
        return parts.join(' ');
    }

    /** Ping in ms → farbiger HTML-Span */
    function fmtPing(ms) {
        ms = ms || 0;
        var color;
        if (ms <= 0)   return '<span class="text-muted">–</span>';
        if (ms <  50)  color = 'text-success';
        else if (ms < 150) color = 'text-warning';
        else           color = 'text-danger';
        return '<span class="' + color + '">' + ms.toFixed(1) + ' ms</span>';
    }

    /** Idle-Zeit: "active" wenn < 30 s, sonst fmtTime */
    function fmtIdle(secs) {
        secs = secs || 0;
        return secs < 30 ? '<span class="text-success">aktiv</span>' : escHtml(fmtTime(secs));
    }

    // ── Render-Funktionen ────────────────────────────────────────────────────

    function renderSummary(servers) {
        var totalServers   = servers.length;
        var runningServers = 0;
        var totalUsers     = 0;
        var totalBw        = 0;
        var pingSum        = 0;
        var pingCount      = 0;

        servers.forEach(function (s) {
            if (s.status === 'running') runningServers++;
            totalUsers += (s.user_count || 0);
            totalBw    += (s.bandwidth_total || 0);
            (s.users || []).forEach(function (u) {
                var p = u.udp_ping > 0 ? u.udp_ping : (u.tcp_ping > 0 ? u.tcp_ping : 0);
                if (p > 0) { pingSum += p; pingCount++; }
            });
        });

        var statServers = document.getElementById('db-stat-servers');
        var statUsers   = document.getElementById('db-stat-users');
        var statBw      = document.getElementById('db-stat-bw');
        var statPing    = document.getElementById('db-stat-ping');

        if (statServers) statServers.textContent = runningServers + ' / ' + totalServers;
        if (statUsers)   statUsers.textContent   = totalUsers;
        if (statBw)      statBw.textContent       = fmtBw(totalBw);
        if (statPing)    statPing.textContent     = pingCount > 0
            ? (pingSum / pingCount).toFixed(1) + ' ms'
            : '–';
    }

    function renderServers(servers) {
        var wrap = document.getElementById('db-servers');
        if (!wrap) return;

        if (!servers || servers.length === 0) {
            wrap.innerHTML = '<div class="col-12 text-muted">Keine Server gefunden.</div>';
            return;
        }

        var html = '';
        servers.forEach(function (s) {
            var isRunning  = s.status === 'running';
            var badgeCls   = isRunning ? 'badge-success' : 'badge-secondary';
            var badgeTxt   = escHtml(s.status || 'unbekannt');
            var userPct    = s.max_users > 0 ? Math.round((s.user_count / s.max_users) * 100) : 0;
            var barCls     = userPct >= 90 ? 'bg-danger' : (userPct >= 70 ? 'bg-warning' : 'bg-success');
            var bwTotal    = fmtBw(s.bandwidth_total || 0);

            html += '<div class="col-md-4 col-sm-6 mb-4">';
            html += '<div class="card h-100">';
            html += '<div class="card-header d-flex justify-content-between align-items-center">';
            html += '<strong>' + escHtml(s.name) + '</strong>';
            html += '<span class="badge ' + badgeCls + '">' + badgeTxt + '</span>';
            html += '</div>';
            html += '<div class="card-body">';

            // Port
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Port</small>';
            html += '<small>' + escHtml(String(s.port)) + '</small>';
            html += '</div>';

            // Nutzer + Progressbar
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Nutzer</small>';
            html += '<small>' + s.user_count + ' / ' + s.max_users + '</small>';
            html += '</div>';
            html += '<div class="progress mb-2" style="height:6px;">';
            html += '<div class="progress-bar ' + barCls + '" style="width:' + userPct + '%"></div>';
            html += '</div>';

            // Uptime
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Uptime</small>';
            html += '<small>' + escHtml(fmtTime(s.uptime_secs)) + '</small>';
            html += '</div>';

            // Bandbreite
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Bandbreite</small>';
            html += '<small>' + escHtml(bwTotal) + '</small>';
            html += '</div>';

            // CPU + RAM
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">CPU / RAM</small>';
            html += '<small class="text-muted">' + (s.cpu_percent || 0) + '% / ' + (s.mem_mb || 0) + ' MB</small>';
            html += '</div>';

            // Channels + Bans
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Channels / Bans</small>';
            html += '<small>' + (s.channel_count || 0) + ' / ' + (s.ban_count || 0) + '</small>';
            html += '</div>';

            // Netzwerk
            html += '<div class="d-flex justify-content-between mb-1">';
            html += '<small class="text-muted">Netz RX / TX</small>';
            html += '<small class="text-muted">' + (s.net_rx_mb || 0) + ' / ' + (s.net_tx_mb || 0) + ' MB</small>';
            html += '</div>';

            html += '</div>'; // card-body
            html += '<div class="card-footer text-right">';
            html += '<a href="?p=mumble_edit&amp;id=' + s.id + '" class="btn btn-sm btn-outline-primary">Details</a>';
            html += '</div>';
            html += '</div>'; // card
            html += '</div>'; // col
        });

        wrap.innerHTML = html;
    }

    function renderUsers(servers) {
        var wrap  = document.getElementById('db-users-wrap');
        var tbody = document.getElementById('db-users-tbody');
        if (!wrap || !tbody) return;

        var rows = '';
        servers.forEach(function (s) {
            (s.users || []).forEach(function (u) {
                var muted = u.mute || u.self_mute;
                var muteIcon = muted ? '&#128263;' : '';
                var ping = u.udp_ping > 0 ? u.udp_ping : (u.tcp_ping > 0 ? u.tcp_ping : 0);
                var connType = u.tcp_only ? ' <small class="text-muted">(TCP)</small>' : '';
                rows += '<tr>';
                rows += '<td>' + escHtml(u.name || '') + connType + '</td>';
                rows += '<td>' + escHtml(s.name || '') + '</td>';
                rows += '<td>Channel #' + (u.channel || 0) + '</td>';
                rows += '<td>' + escHtml(fmtBw(u.bytespersec || 0)) + '</td>';
                rows += '<td>' + fmtPing(ping) + '</td>';
                rows += '<td>' + fmtIdle(u.idle || 0) + '</td>';
                rows += '<td>' + escHtml((u.os || '') + (u.os_version ? ' ' + u.os_version : '')) + '</td>';
                rows += '<td>' + muteIcon + '</td>';
                rows += '</tr>';
            });
        });

        if (rows === '') {
            wrap.style.display = 'none';
            return;
        }
        tbody.innerHTML = rows;
        wrap.style.display = '';
    }

    // ── Haupt-Ladefunktion ───────────────────────────────────────────────────

    function loadDashboard() {
        fetch('?p=mumble_dashboard&c=dashboard_data')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    var wrap = document.getElementById('db-servers');
                    if (wrap) wrap.innerHTML = '<div class="col-12 text-danger">Fehler beim Laden der Dashboard-Daten.</div>';
                    return;
                }
                var servers = data.servers || [];
                renderSummary(servers);
                renderServers(servers);
                renderUsers(servers);

                var ts = document.getElementById('db-last-updated');
                if (ts) {
                    var now = new Date();
                    var hh  = String(now.getHours()).padStart(2, '0');
                    var mm  = String(now.getMinutes()).padStart(2, '0');
                    var ss  = String(now.getSeconds()).padStart(2, '0');
                    ts.textContent = 'Zuletzt aktualisiert: ' + hh + ':' + mm + ':' + ss;
                }
            })
            .catch(function (err) {
                var wrap = document.getElementById('db-servers');
                if (wrap) wrap.innerHTML = '<div class="col-12 text-danger">Verbindungsfehler: ' + escHtml(String(err)) + '</div>';
            });
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    var root = document.getElementById('db-root');
    if (root) {
        loadDashboard();
        setInterval(loadDashboard, 30000);
    }

})();
