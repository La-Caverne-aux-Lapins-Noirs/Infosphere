CREATE TABLE `quiz` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `codename` varchar(128) NOT NULL,
  `reference` varchar(1024) NOT NULL,
  `id_creator` int(11) DEFAULT NULL,
  KEY `id_creator` (`id_creator`),
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` datetime DEFAULT NULL,
  UNIQUE KEY `quiz_school_codename` (`id_school`, `codename`),
  UNIQUE KEY `quiz_reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
