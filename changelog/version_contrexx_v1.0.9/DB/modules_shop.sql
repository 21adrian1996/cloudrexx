ALTER TABLE `contrexx_module_shop_products` ADD `handler` ENUM( 'none', 'delivery', 'download' ) NOT NULL DEFAULT 'none' AFTER `catid` ;