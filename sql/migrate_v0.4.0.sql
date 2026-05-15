-- Easy2-Mumble Migration v0.4.0
-- Server-Mitglieder + Host-Zertifikat-Pfad

-- Mitglieder-Tabelle: zusätzliche User die einen Server verwalten dürfen
CREATE TABLE IF NOT EXISTS `[prefix]_ml_mumble_server_members` (
  `server_id` INT UNSIGNED NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `added_at`  DATETIME     NOT NULL DEFAULT NOW(),
  PRIMARY KEY (`server_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zertifikat-Pfad pro Host (leer = Agent-Default /etc/mumble-agent/ssl/)
ALTER TABLE `[prefix]_ml_mumble_host`
  ADD COLUMN IF NOT EXISTS `ssl_cert_path` VARCHAR(255) DEFAULT NULL AFTER `agent_token`,
  ADD COLUMN IF NOT EXISTS `ssl_key_path`  VARCHAR(255) DEFAULT NULL AFTER `ssl_cert_path`;
