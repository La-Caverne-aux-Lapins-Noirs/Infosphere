CREATE TABLE IF NOT EXISTS `machine_health_sample` (
  `node_key` varchar(96) NOT NULL,
  `observed_at` bigint unsigned NOT NULL,
  `payload` mediumtext NOT NULL,
  PRIMARY KEY (`node_key`, `observed_at`),
  KEY `observed_at` (`observed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
