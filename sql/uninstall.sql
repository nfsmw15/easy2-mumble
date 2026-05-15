-- ============================================================
-- Easy2-Mumble - Uninstall
-- VORSICHT: löscht auch Logs und Konfiguration!
-- ============================================================

DROP TABLE IF EXISTS `[prefix]_ml_mumble_log`;
DROP TABLE IF EXISTS `[prefix]_ml_mumble_server`;
DROP TABLE IF EXISTS `[prefix]_ml_mumble_host`;
DROP TABLE IF EXISTS `[prefix]_ml_mumble_quota`;

DELETE FROM `[prefix]_ml_menu`  WHERE `id` BETWEEN 200 AND 205;
DELETE FROM `[prefix]_ml_rules` WHERE `id` BETWEEN 200 AND 204;
DELETE FROM `[prefix]_ml_sites` WHERE `id` BETWEEN 200 AND 205;

-- mumble_*-Rules in den Komma-Listen ranks.rules über die
-- Rang-Verwaltung manuell bereinigen.
