// sql modifications from v1.8

ALTER TABLE `astalavista_themes` ADD `buildin_style` TEXT AFTER `style` ;
DELETE FROM `astalavista_modules` WHERE `id`='7';
INSERT INTO `astalavista_backend_areas` VALUES (53, 6, 'function', 'Komplette Sites kopieren und l�schen', 1, '', '_self', 0, 0);
ALTER TABLE `astalavista_users` CHANGE `lang` `langId` SMALLINT( 2 ) NOT NULL;