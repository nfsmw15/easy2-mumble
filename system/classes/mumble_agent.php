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
    public function getConfig(string $cid): array {
        return $this->request('GET', '/v1/servers/'.rawurlencode($cid).'/config');
    }
    public function saveConfig(string $cid, string $content): array {
        return $this->request('PUT', '/v1/servers/'.rawurlencode($cid).'/config',
            ['content' => $content], 30);
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
        curl_close($ch);

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
