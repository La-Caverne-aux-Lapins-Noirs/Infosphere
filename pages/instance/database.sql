
CREATE TABLE `token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  `status` int(11) DEFAULT NULL,
  `codename` varchar(255) NOT NULL,
  `invalidation_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `team_name` varchar(255) DEFAULT NULL,
  `id_activity` int(11) DEFAULT NULL,
  KEY `id_activity` (`id_activity`),
  `id_session` int(11) DEFAULT NULL,
  KEY `id_session` (`id_session`),
  `present` int(11) NOT NULL DEFAULT 0,
  `declaration_date` datetime DEFAULT NULL,
  `late_time` datetime DEFAULT NULL,
  `closed` datetime DEFAULT NULL,
  `manual_credit` int(11) DEFAULT NULL,
  `manual_grade` int(11) DEFAULT NULL,
  `canjoin` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_team` int(11) NOT NULL,
  KEY `id_team` (`id_team`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `status` int(11) DEFAULT NULL,
  `code` varchar(128) NOT NULL,
  `bonus_grade_d` int(11) NOT NULL DEFAULT 0,
  `bonus_grade_c` int(11) NOT NULL DEFAULT 0,
  `bonus_grade_b` int(11) NOT NULL DEFAULT 0,
  `bonus_grade_a` int(11) NOT NULL DEFAULT 0,
  `bonus_grade_bonus` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `pickedup_work` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_team` int(11) NOT NULL,
  KEY `id_team` (`id_team`),
  `pickedup_date` datetime DEFAULT NULL,
  `repository` varchar(255) DEFAULT NULL,
  `traces` text DEFAULT NULL,
  `result` longtext DEFAULT NULL,
  `errors` text DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `observation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `ticket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_team` int(11) NOT NULL,
  KEY `id_team` (`id_team`),
  `id_sprint` int(11) DEFAULT NULL,
  KEY `id_sprint` (`id_sprint`),
  `id_author` int(11) NOT NULL,
  KEY `id_author` (`id_author`),
  `id_user` int(11) DEFAULT NULL,
  KEY `id_user` (`id_user`),
  `deleted` datetime DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `real_time` int(11) DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `creation_date` datetime,
  `done_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `sprint` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_team` int(11) NOT NULL,
  KEY `id_team` (`id_team`),
  `deleted` datetime DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime,
  `done_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

