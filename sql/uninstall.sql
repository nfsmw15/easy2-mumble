-- ============================================================
-- Easy2-Mumble - Uninstall
-- VORSICHT: löscht auch Logs und Konfiguration!
-- ============================================================

DROP TABLE IF EXISTS `[prefix]_mumble_log`;
DROP TABLE IF EXISTS `[prefix]_mumble_server`;
DROP TABLE IF EXISTS `[prefix]_mumble_host`;
DROP TABLE IF EXISTS `[prefix]_mumble_quota`;

DELETE FROM `[prefix]_menu`  WHERE `id` BETWEEN 200 AND 205;
DELETE FROM `[prefix]_rules` WHERE `id` BETWEEN 200 AND 204;
DELETE FROM `[prefix]_sites` WHERE `id` BETWEEN 200 AND 205;

-- mumble_*-Rules in den Komma-Listen ranks.rules über die
-- Rang-Verwaltung manuell bereinigen.
