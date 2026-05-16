-- Easy2-Mumble Migration v0.5.0
-- ACL-Verwaltung: neue Site und Regel (kein Menüeintrag — ACL wird über Server-Detail aufgerufen)

INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (206, 'mumble_acl', 'mumble/', 'Mumble-ACL verwalten', 0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

INSERT INTO `[prefix]_ml_rules` (`id`, `name`, `tag`, `description`) VALUES
  (205, 'Mumble: ACL verwalten', 'mumble_acl', 'Darf Mumble-Server-ACLs bearbeiten')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`);

-- Menüeintrag entfernen falls vorhanden (ACL ist nur über Server-Detail erreichbar)
DELETE FROM `[prefix]_ml_menu` WHERE id = 205;
