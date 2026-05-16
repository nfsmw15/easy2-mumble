-- Easy2-Mumble Migration v0.6.0
-- ICE-Integration: Channel-Verwaltung und Ban-Verwaltung

INSERT INTO `[prefix]_ml_sites`
  (`id`, `filename`, `dir`, `title`, `start_site`, `start_site_login`, `errorsite`, `type`, `logout_site`)
VALUES
  (207, 'mumble_channels', 'mumble/', 'Mumble-Channels verwalten', 0, 0, 0, 'php', 0),
  (208, 'mumble_bans',     'mumble/', 'Mumble-Bans verwalten',     0, 0, 0, 'php', 0)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);
