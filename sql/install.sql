-- ============================================================
-- Easy2-Mumble - Install Migration
--
-- Platzhalter [prefix] vor dem Import durch eigenen Tabellen-Prefix
-- ersetzen (Konvention von Easy2-PHP8).
--
-- Anforderungen: MySQL 5.7+ / MariaDB 10.3+, InnoDB, utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS `[prefix]_ml_mumble_host` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(64)   NOT NULL,
  `hostname`      VARCHAR(255)  NOT NULL,
  `agent_url`     VARCHAR(255)  NOT NULL,
  `agent_token`   VARCHAR(128)  NOT NULL,
  `port_min`      INT UNSIGNED  NOT NULL DEFAULT 64738,
  `port_max`      INT UNSIGNED  NOT NULL DEFAULT 64838,
  `max_servers`   INT UNSIGNED  NOT NULL DEFAULT 20,
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `last_seen`     DATETIME      DEFAULT NULL,
  `note`          TEXT          DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `[prefix]_ml_mumble_server` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `host_id`       INT UNSIGNED  NOT NULL,
  `owner_user_id` INT UNSIGNED  NOT NULL,
  `container_id`  VARCHAR(64)   DEFAULT NULL,
  `name`          VARCHAR(64)   NOT NULL,
  `port`          INT UNSIGNED  NOT NULL,
  `password`      VARCHAR(255)  DEFAULT NULL,
  `max_users`     INT UNSIGNED  NOT NULL DEFAULT 10,
  `welcome_text`  TEXT          DEFAULT NULL,
  `status`        ENUM('stopped','running','error','creating') NOT NULL DEFAULT 'creating',
  `last_status`   DATETIME      DEFAULT NULL,
  `stats_online`  INT UNSIGNED  NOT NULL DEFAULT 0,
  `stats_uptime`  INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`    DATETIME      NOT NULL,
  `updated_at`    DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_host`  (`host_id`),
  KEY `idx_owner` (`owner_user_id`),
  UNIQUE KEY `uniq_host_port` (`host_id`,`port`),
  CONSTRAINT `fk_mms_host`
    FOREIGN KEY (`host_id`) REFERENCES `[prefix]_ml_mumble_host`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `[prefix]_ml_mumble_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `server_id`   INT UNSIGNED  DEFAULT NULL,
  `user_id`     INT UNSIGNED  NOT NULL,
  `action`      VARCHAR(32)   NOT NULL,
  `details`     TEXT          DEFAULT NULL,
  `success`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_server` (`server_id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `[prefix]_ml_mumble_quota` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rank_id`        INT UNSIGNED NOT NULL,
  `max_servers`    INT UNSIGNED NOT NULL DEFAULT 0,
  `max_users_cap`  INT UNSIGNED NOT NULL DEFAULT 25,
  `can_create`     TINYINT(1)   NOT NULL DEFAULT 0,
  `can_admin_all`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rank` (`rank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `[prefix]_ml_mumble_quota`
  (`rank_id`,`max_servers`,`max_users_cap`,`can_create`,`can_admin_all`)
VALUES
  (1, 999, 250, 1, 1),
  (2, 1,   25,  1, 0)
ON DUPLICATE KEY UPDATE `rank_id`=`rank_id`;


-- ------------------------------------------------------------
-- Integration ins Core (Sites, Rules, Menu) - IDs ab 200
-- ------------------------------------------------------------

INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (200, 'mumble',       'mumble/', 'Mumble-Server',           0, 0, 0, 'php', 0),
  (201, 'mumble_new',   'mumble/', 'Neuer Mumble-Server',     0, 0, 0, 'php', 0),
  (202, 'mumble_edit',  'mumble/', 'Mumble-Server bearbeiten',0, 0, 0, 'php', 0),
  (203, 'mumble_logs',  'mumble/', 'Mumble-Server Logs',      0, 0, 0, 'php', 0),
  (204, 'mumble_hosts', 'mumble/', 'Mumble-Hosts verwalten',  0, 0, 0, 'php', 0),
  (205, 'mumble_quota', 'mumble/', 'Mumble-Quotas verwalten', 0, 0, 0, 'php', 0),
  (206, 'mumble_acl',      'mumble/', 'Mumble-ACL verwalten',        0, 0, 0, 'php', 0),
  (207, 'mumble_channels', 'mumble/', 'Mumble-Channels verwalten',   0, 0, 0, 'php', 0),
  (208, 'mumble_bans',     'mumble/', 'Mumble-Bans verwalten',       0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

INSERT INTO `[prefix]_ml_rules` (`id`, `name`, `tag`, `description`) VALUES
  (200, 'Mumble: Ansehen',              'mumble_view',   'Darf die Mumble-Serverübersicht sehen'),
  (201, 'Mumble: Server erstellen',     'mumble_create', 'Darf neue Mumble-Server anlegen'),
  (202, 'Mumble: Fremdserver verwalten','mumble_admin',  'Darf alle Mumble-Server verwalten'),
  (203, 'Mumble: Hosts verwalten',      'mumble_hosts',  'Darf Mumble-Hosts anlegen/bearbeiten'),
  (204, 'Mumble: Quotas verwalten',     'mumble_quota',  'Darf Quota-Regeln pro Rang bearbeiten'),
  (205, 'Mumble: ACL verwalten',        'mumble_acl',    'Darf Mumble-Server-ACLs bearbeiten')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`);

INSERT INTO `[prefix]_ml_menu`
  (`id`, `sid`, `title`, `icon`, `pos`, `url`, `under`, `menu`, `link_type`, `target`)
VALUES
  (200, 200, 'Mumble',          'fa-headphones', 10, '', 0,   1, 0, '_self'),
  (201, 200, 'Meine Server',    'fa-list',        0, '', 200, 1, 0, '_self'),
  (202, 201, 'Neuer Server',    'fa-plus',        1, '', 200, 1, 0, '_self'),
  (203, 204, 'Hosts verwalten', 'fa-server',      2, '', 200, 1, 0, '_self'),
  (204, 205, 'Quotas',          'fa-tachometer',  3, '', 200, 1, 0, '_self'),
  (205, 206, 'ACL-Verwaltung',  'fa-key',         4, '', 200, 1, 0, '_self')
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `icon`=VALUES(`icon`);
