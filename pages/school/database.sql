
CREATE TABLE `school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `base_url` varchar(512) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `fr_name` varchar(255) NOT NULL,
  `en_name` varchar(255) NOT NULL,
  `legal_name` varchar(128) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `uai` varchar(16) NOT NULL DEFAULT '',
  `cfa_name` varchar(255) NOT NULL DEFAULT '',
  `executing_establishment_name` varchar(255) NOT NULL DEFAULT '',
  `main_info` text NOT NULL DEFAULT '',
  `school_info` text NOT NULL DEFAULT '',
  `formation_info` text NOT NULL DEFAULT '',
  `alternation_info` text NOT NULL DEFAULT '',
  `phone` varchar(16) NOT NULL DEFAULT '',
  `mail` text NOT NULL DEFAULT ''

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `uai` varchar(16) NOT NULL DEFAULT '' AFTER `address`,
  ADD COLUMN IF NOT EXISTS `cfa_name` varchar(255) NOT NULL DEFAULT '' AFTER `uai`,
  ADD COLUMN IF NOT EXISTS `executing_establishment_name` varchar(255) NOT NULL DEFAULT '' AFTER `cfa_name`;

CREATE TABLE `school_cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_laboratory` int(11) NOT NULL,
  KEY `id_laboratory` (`id_laboratory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_room` int(11) NOT NULL,
  KEY `id_room` (`id_room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `school_mailbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `purpose` varchar(64) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  UNIQUE KEY `school_mailbox_purpose` (`id_school`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
