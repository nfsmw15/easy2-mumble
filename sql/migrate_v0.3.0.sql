-- Easy2-Mumble Migration v0.3.0
-- Channel-Viewer Widget: widget_token, widget_public, widget_refresh

ALTER TABLE `[prefix]_ml_mumble_server`
  ADD COLUMN `widget_token`   VARCHAR(64)  DEFAULT NULL        AFTER `superuser_password`,
  ADD COLUMN `widget_public`  TINYINT(1)   NOT NULL DEFAULT 0  AFTER `widget_token`,
  ADD COLUMN `widget_refresh` SMALLINT     NOT NULL DEFAULT 30 AFTER `widget_public`;

-- Neue Seite: mumble_widget (öffentlich zugänglich)
INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (207, 'mumble_widget', 'mumble/', 'Mumble Channel-Viewer', 0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
