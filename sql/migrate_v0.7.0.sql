-- Easy2-Mumble Migration v0.7.0
-- Dashboard: neue Site und Menüeintrag

INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (212, 'mumble_dashboard', 'mumble/', 'Mumble Dashboard', 0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

INSERT INTO `[prefix]_ml_menu`
  (`id`, `sid`, `title`, `icon`, `pos`, `url`, `under`, `menu`, `link_type`, `target`)
VALUES
  (206, 212, 'Dashboard', 'fa-tachometer', 0, '', 200, 1, 0, '_self')
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`), `icon`=VALUES(`icon`);
