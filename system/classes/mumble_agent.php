<?php
declare(strict_types=1);

/********************************************
 * Easy2-Mumble Erweiterung
 * Class: mumble_agent
 * File: system/classes/mumble_agent.php
 *
 * HTTPS-Client für den Python-Agent (mumble-agent) auf jedem Mumble-Host.
 * Liefert normalisiertes Ergebnis:
 *   ['ok' => bool, 'data' => mixed|null, 'error' => ?string, 'http' => int]
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *********************************************/

class mumble_agent
{
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    public function __construct(string $agentUrl, string $token, int $timeout = 30)
    {
        $this->baseUrl = rtrim($agentUrl, '/');
        $this->token   = $token;
        $this->timeout = $timeout;
    }

    public function ping(): array { return $this->request('GET', '/v1/ping'); }
    public function createServer(array $cfg): array { return $this->request('POST', '/v1/servers', $cfg, 120); }
    public function deleteServer(string $cid): array { return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid), null, 30); }
    public function startServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/start'); }
    public function stopServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/stop'); }
    public function restartServer(string $cid): array { return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/restart'); }
    public function getStats(string $cid): array { return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/stats'); }
    public function getLogs(string $cid, int $tail = 200): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/logs?tail='.$tail);
    }
    public function updateConfig(string $cid, array $cfg): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid), $cfg, 120);
    }
    public function getSuperUser(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/superuser');
    }
    public function resetSuperUser(string $cid, string $pw = ''): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/superuser/reset',
            ['password' => $pw], 30);
    }
    public function getViewer(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/viewer', null, 12);
    }
    public function getSettings(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/settings');
    }
    public function saveSettings(string $cid, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid), $data, 120);
    }
    public function setCertificate(string $cid, string $cert, string $key): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/certificate',
            ['cert' => $cert, 'key' => $key], 60);
    }
    public function removeCertificate(string $cid): array {
        return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid).'/certificate', null, 60);
    }
    public function getConfig(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/config');
    }
    public function saveConfig(string $cid, string $content): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/config',
            ['content' => $content], 30);
    }
    public function getChannelAcl(string $cid, int $channelId): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/acl?channel_id='.$channelId, null, 15);
    }
    public function setChannelAcl(string $cid, array $data): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/acl', $data, 15);
    }
    // Live-User (ICE)
    public function getLiveUsers(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/users', null, 10);
    }
    public function kickUser(string $cid, int $session, string $reason = ''): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/users/'.rawurlencode((string)$session).'/kick', ['reason' => $reason], 10);
    }
    public function updateUser(string $cid, int $session, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/users/'.rawurlencode((string)$session), $data, 10);
    }
    // Channels (ICE)
    public function getChannels(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/channels', null, 10);
    }
    public function addChannel(string $cid, string $name, int $parent = 0): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/channels', ['name' => $name, 'parent' => $parent], 10);
    }
    public function updateChannel(string $cid, int $channelId, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/channels/'.rawurlencode((string)$channelId), $data, 10);
    }
    public function removeChannel(string $cid, int $channelId): array {
        return $this->request('DELETE', '/v1/servers/'.rawurlencode($cid).'/channels/'.rawurlencode((string)$channelId), null, 10);
    }
    // Bans (ICE)
    public function getBans(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/bans', null, 10);
    }
    public function setBans(string $cid, array $bans): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/bans', ['bans' => $bans], 10);
    }
    public function updateSettingsLive(string $cid, array $data): array {
        return $this->request('PATCH', '/v1/servers/'.rawurlencode($cid).'/live', $data, 15);
    }
    // ICE aktivieren
    public function enableIce(string $cid): array {
        return $this->request('POST', '/v1/servers/'.rawurlencode($cid).'/ice/enable', null, 30);
    }
    // Dashboard (ICE + Docker-Stats in einem Aufruf)
    public function getDashboard(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/dashboard', null, 15);
    }

    private function request(string $method, string $path, mixed $body = null, ?int $timeoutOverride = null): array
    {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer '.$this->token,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_URL            => $this->baseUrl.$path,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutOverride ?? $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
        ];

        // TLS-Validierung nur bei HTTPS aktivieren.
        // Für reine LAN-Setups (http://intern.lan:8000) ist HTTP ohne TLS ok,
        // weil das Token im Header die Authentifizierung übernimmt und das
        // Netzwerk vertrauenswürdig ist.
        if (str_starts_with($this->baseUrl, 'https://')) {
            $opts[CURLOPT_SSL_VERIFYPEER] = true;
            $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_POSTFIELDS] = $json;
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: '.strlen((string)$json);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw  = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch); // curl_close() ist ab PHP 8.4 deprecated; Handle wird automatisch freigegeben

        if ($raw === false) {
            return ['ok' => false, 'data' => null, 'error' => $err, 'http' => 0];
        }
        $decoded = json_decode((string)$raw, true);
        $ok = ($http >= 200 && $http < 300);
        return [
            'ok'    => $ok,
            'data'  => $decoded,
            'error' => $ok ? null : ($decoded['error'] ?? ('HTTP '.$http)),
            'http'  => $http,
        ];
    }
}
