CREATE TABLE IF NOT EXISTS `#__qrzlitesitemap_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(2048) NOT NULL,
  `title` varchar(400) NOT NULL DEFAULT '',
  `level` tinyint unsigned NOT NULL DEFAULT 1,
  `lastmod` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
