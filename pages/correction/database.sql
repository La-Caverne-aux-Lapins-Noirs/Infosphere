CREATE TABLE `correction_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_parent` int(11) DEFAULT NULL,
  KEY `id_parent` (`id_parent`),
  `codename` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  UNIQUE KEY `correction_category_parent_codename` (`id_parent`, `codename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `correction_asset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_category` int(11) NOT NULL,
  KEY `id_category` (`id_category`),
  `kind` enum('library','dabsic','resource') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `relative_path` varchar(1024) NOT NULL,
  `sha256` char(64) NOT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `source_type` enum('upload','git') NOT NULL DEFAULT 'upload',
  `source_reference` text DEFAULT NULL,
  `elf_class` varchar(64) DEFAULT NULL,
  `elf_machine` varchar(255) DEFAULT NULL,
  `elf_type` varchar(255) DEFAULT NULL,
  `analysis_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` datetime DEFAULT NULL,
  UNIQUE KEY `correction_asset_relative_path` (`relative_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `correction_symbol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_asset` int(11) NOT NULL,
  KEY `id_asset` (`id_asset`),
  `symbol` varchar(255) NOT NULL,
  `function_name` varchar(255) NOT NULL,
  UNIQUE KEY `correction_symbol_asset_symbol` (`id_asset`, `symbol`),
  KEY `function_name` (`function_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `correction_category` (`id`, `id_parent`, `codename`, `name`)
VALUES (1, NULL, 'general', 'Général');
