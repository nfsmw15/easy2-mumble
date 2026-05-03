-- ============================================================
-- Easy2-Mumble - Migration v0.2.0
-- Neue Spalte superuser_password
--
-- Platzhalter [prefix] vor dem Import ersetzen.
-- ============================================================

ALTER TABLE `[prefix]_ml_mumble_server`
  ADD COLUMN `superuser_password` VARCHAR(128) DEFAULT NULL
  AFTER `welcome_text`;

-- Neue Seite: mumble_config
INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (206, 'mumble_config', 'mumble/', 'Mumble-Server Config', 0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
