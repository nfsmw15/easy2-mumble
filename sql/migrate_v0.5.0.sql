-- Easy2-Mumble Migration v0.5.0
-- ACL-Verwaltung: neue Site, Regel und Menüeintrag

INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (206, 'mumble_acl', 'mumble/', 'Mumble-ACL verwalten', 0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

INSERT INTO `[prefix]_ml_rules` (`id`, `name`, `tag`, `description`) VALUES
  (205, 'Mumble: ACL verwalten', 'mumble_acl', 'Darf Mumble-Server-ACLs bearbeiten')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`);

INSERT INTO `[prefix]_ml_menu`
  (`id`, `sid`, `title`, `icon`, `pos`, `url`, `under`, `menu`, `link_type`, `target`)
VALUES
  (205, 206, 'ACL-Verwaltung', 'fa-key', 4, '', 200, 1, 0, '_self')
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `icon`=VALUES(`icon`);
