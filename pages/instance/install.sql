
CREATE TABLE `appointment_slot` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_session` int(11) NOT NULL,
  `id_team` int(11) NOT NULL DEFAULT -1,
  `begin_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `was_present` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- La date d'apparition du sujet est aussi la date de fermeture des inscriptions.
CREATE TABLE `instance` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_activity` int(11) NOT NULL,
  `parent_instance` int(11) NOT NULL DEFAULT -1,
  `codename` varchar(512) COLLATE utf8_bin NOT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,

  `emergence_date` datetime DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `close_registration_date` datetime DEFAULT NULL,
  `subject_appeir_date` datetime DEFAULT NULL,
  `subject_disappeir_date` datetime DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,

  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `instance_school_year` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instance` int(11) NOT NULL,
  `id_school_year` int(11) NOT NULL,
  `tags` varchar(512) NOT NULL DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `instance_teacher` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instance` int(11) NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT -1,
  `id_laboratory` int(11) NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- status: -1, echec, 1, acquis
CREATE TABLE `instance_user_medal` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instance` int(11) NOT NULL,
  `id_user_medal` int(11) NOT NULL,
  `insert_date` datetime DEFAULT current_timestamp(),
  `result` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session_room` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_session` int(11) NOT NULL,
  `id_room` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session_school_year` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_session` int(11) NOT NULL,
  `id_school_year` int(11) NOT NULL,
  `tags` varchar(512) NOT NULL DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session_teacher` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_session` int(11) NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT -1,
  `id_laboratory` int(11) NOT NULL DEFAULT -1,
  `duration` int(11) DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `team` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `team_name` varchar(255) COLLATE utf8_bin NOT NULL,
  `id_instance` int(11) NOT NULL DEFAULT -1,
  `id_session` int(11) NOT NULL DEFAULT -1,
  `present` tinyint(1) NOT NULL DEFAULT 0 -- 0: a voir, 1: present, -1, absent
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_team` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_team` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `job` varchar(32) COLLATE utf8_bin NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_class_gallery_asset` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_activity` int(11) NOT NULL,
  `id_class_gallery_asset` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

