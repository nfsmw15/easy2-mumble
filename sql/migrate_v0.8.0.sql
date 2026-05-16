-- Easy2-Mumble Migration v0.8.0
-- Menü: flache Struktur — alle Mumble-Punkte direkt sichtbar

-- Mumble-Parent-Dropdown entfernen
DELETE FROM `[prefix]_ml_menu` WHERE id = 200;

-- Alle Mumble-Items auf Top-Level setzen
UPDATE `[prefix]_ml_menu` SET `under` = 0, `pos` = 0 WHERE id = 206; -- Dashboard
UPDATE `[prefix]_ml_menu` SET `under` = 0, `pos` = 1 WHERE id = 201; -- Meine Server
UPDATE `[prefix]_ml_menu` SET `under` = 0, `pos` = 2 WHERE id = 203; -- Hosts verwalten
UPDATE `[prefix]_ml_menu` SET `under` = 0, `pos` = 3 WHERE id = 204; -- Quotas
