
CREATE TABLE `scale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `tag` text DEFAULT NULL,
  `last_edit_date` datetime NOT NULL DEFAULT current_timestamp(),
  
  `fr_name` text DEFAULT NULL,
  `fr_content` longtext DEFAULT NULL,
  `en_name` text DEFAULT NULL,
  `en_content` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_scale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_scale` int(11) NOT NULL,
  KEY `id_scale` (`id_activity`),
  `chapter` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_scale_user_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity_scale` int(11) NOT NULL,
  KEY `id_activity_scale` (`id_activity_scale`),
  `id_user_team` int(11) NOT NULL,
  KEY `id_user_team` (`id_user_team`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `last_edit_date` datetime DEFAULT NULL,
  `result` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
