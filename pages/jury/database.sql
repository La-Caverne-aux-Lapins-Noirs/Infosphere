ALTER TABLE `user`
  MODIFY COLUMN `profile_status` enum('member', 'prospect', 'enterprise_contact', 'jury') COLLATE utf8_bin NOT NULL DEFAULT 'member';

CREATE TABLE IF NOT EXISTS `title` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(64) NOT NULL,
  UNIQUE KEY `codename` (`codename`),
  `code` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `fr_name` tinytext DEFAULT NULL,
  `en_name` tinytext DEFAULT NULL,
  `diploma_text` text COLLATE utf8_bin DEFAULT NULL,
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `title`
  ADD COLUMN IF NOT EXISTS `code` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '' AFTER `codename`;

ALTER TABLE `title`
  ADD COLUMN IF NOT EXISTS `diploma_text` text COLLATE utf8_bin DEFAULT NULL AFTER `en_name`;

CREATE TABLE IF NOT EXISTS `user_title` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_title` int(11) NOT NULL,
  KEY `id_title` (`id_title`),
  `type` enum('certified', 'certificator') COLLATE utf8_bin NOT NULL DEFAULT 'certified',
  UNIQUE KEY `user_title_type` (`id_user`, `id_title`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `skill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(128) NOT NULL,
  UNIQUE KEY `codename` (`codename`),
  `fr_description` text DEFAULT NULL,
  `en_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `title_skill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_title` int(11) NOT NULL,
  KEY `id_title` (`id_title`),
  `id_skill` int(11) NOT NULL,
  KEY `id_skill` (`id_skill`),
  `reference` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  UNIQUE KEY `title_skill` (`id_title`, `id_skill`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `title_skill`
  ADD COLUMN IF NOT EXISTS `reference` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '' AFTER `id_skill`;

CREATE TABLE IF NOT EXISTS `jury_note` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  UNIQUE KEY `id_user` (`id_user`),
  `note` text COLLATE utf8_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `session_teacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  `id_user` int(11) DEFAULT NULL,
  KEY `id_user` (`id_user`),
  `id_laboratory` int(11) DEFAULT NULL,
  KEY `id_laboratory` (`id_laboratory`),
  UNIQUE KEY `session_user` (`id_session`, `id_user`),
  UNIQUE KEY `session_laboratory` (`id_session`, `id_laboratory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `title_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_title` int(11) NOT NULL,
  KEY `id_title` (`id_title`),
  `start_date` date NOT NULL,
  KEY `start_date` (`start_date`),
  `end_date` date NOT NULL,
  KEY `end_date` (`end_date`),
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `jury_arrival_time` time NOT NULL DEFAULT '08:30:00',
  `id_session_manager` int(11) DEFAULT NULL,
  KEY `id_session_manager` (`id_session_manager`),
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `title_session_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_title_session` int(11) NOT NULL,
  KEY `id_title_session` (`id_title_session`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  UNIQUE KEY `title_session_session_session` (`id_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `title_session_jury` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_title_session` int(11) NOT NULL,
  KEY `id_title_session` (`id_title_session`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  UNIQUE KEY `title_session_jury_user` (`id_title_session`, `id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
