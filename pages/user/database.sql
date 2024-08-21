
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  -- Bases --
  `codename` varchar(64) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `cache` text NOT NULL DEFAULT '{}' COMMENT 'Des informations calculées ici et là et pouvant être servie directement. Elles peuvent tout à fait être périmé.',
  `mail` varchar(255) NOT NULL,
  `password` tinytext NOT NULL,
  `salt` varchar(512) NOT NULL,
  `local_salt` varchar(512) NOT NULL,

  -- Informations lié au site --
  `uid` int(11) DEFAULT NULL COMMENT 'LDAP',
  `outside_secret` varchar(32) DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `visibility` int(11) NOT NULL DEFAULT 3,
  `authority` int(11) NOT NULL DEFAULT 0,
  `money` int(11) NOT NULL DEFAULT 0,

  -- Informations personnelles
  `nickname` varchar(255) DEFAULT NULL,
  `first_name` tinytext DEFAULT NULL,
  `family_name` tinytext DEFAULT NULL,
  `birth_date` datetime DEFAULT NULL,
  `phone` text DEFAULT NULL,
  `address_name` text DEFAULT NULL,
  `street_name` text DEFAULT NULL,
  `postal_code` text DEFAULT NULL,
  `city` text DEFAULT NULL,
  `country` text DEFAULT NULL,
  `bookbail` datetime DEFAULT NULL,

  `ìne` varchar(11) DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `current_class` int(11) DEFAULT NULL,
  `target_class` int(11) DEFAULT NULL,
  `target_entry` int(11) DEFAULT NULL,
  `prefered_hour` varchar(128) DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`),
  `hidden` int(11) DEFAULT NULL,
  `commentaries` text DEFAULT NULL,
  `cursus` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `log_date` datetime NOT NULL DEFAULT current_timestamp(),
  `last_log` datetime DEFAULT NULL,
  `last_ip` text NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_medal` int(11) NOT NULL,
  KEY `id_medal` (`id_medal`),
  `id_activity` int(11) NOT NULL DEFAULT -1,
  KEY `id_activity` (`id_activity`),
  `id_team` int(11) NOT NULL DEFAULT -1,
  KEY `id_team` (`id_team`),
  `id_user_team` int(11) NOT NULL DEFAULT -1,
  KEY `id_user_team` (`id_user_team`),
  `result` int(11) NOT NULL DEFAULT 0,
  `strength` int(11) NOT NULL DEFAULT 2,
  `insert_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `authority` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_todolist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `content` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `parent_child` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_parent` int(11) NOT NULL,
  KEY `id_parent` (`id_parent`),
  `id_child` int(11) NOT NULL,
  KEY `id_child` (`id_child`),
  `relation` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `misc_type` int(11) NOT NULL COMMENT '0: team, 1: user team, 2: user_cycle',
  `id_misc` int(11) NOT NULL,
  KEY `id_misc` (`id_misc`),
  `comment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `content` text NOT NULL,
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
