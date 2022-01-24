
CREATE TABLE `user` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(64) COLLATE utf8_bin NOT NULL,
  `password` tinytext COLLATE utf8_bin NOT NULL,
  `salt` varchar(512) COLLATE utf8_bin NOT NULL,
  `local_salt` varchar(512) COLLATE utf8_bin NOT NULL,
  `mail` varchar(255) COLLATE utf8_bin NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `first_name` tinytext COLLATE utf8_bin DEFAULT NULL,
  `family_name` tinytext COLLATE utf8_bin DEFAULT NULL,
  `birth_date` timestamp NULL DEFAULT NULL,
  `authority` int(11) NOT NULL DEFAULT 0,
  `avatar` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `photo` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
  `money` int(11) NOT NULL DEFAULT 0,
  `visibility` int(11) NOT NULL DEFAULT 1,
  `connection_log` longtext COLLATE DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- id parent et id child sont deux id_user
CREATE TABLE `parent_child` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_parent` int(11) NOT NULL,
  `id_child` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_log` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `log_date` datetime NOT NULL,
  `type` int(11) NOT NULL -- 0: intra interne, 1: intra externe, 2: login, 3: logout, 4: work
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `authorities` (
  `id` int(11) PRIMARY KEY NOT NULL,
  `label` tinytext COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `authorities` (`id`, `label`) VALUES
(-1, 'Banished'),
(0, 'Visitor'),
(1, 'Extern'),
(2, 'Student'),
(3, 'Assistant'),
(4, 'Teacher'),
(5, 'ContentAuthor'),
(6, 'Administrator');

CREATE TABLE `user_school_year` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_school_year` int(11) NOT NULL,
  `tags` varchar(512) NOT NULL DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_laboratory` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_laboratory` int(11) NOT NULL,
  `authority` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_medal` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `insert_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_opinion` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instance` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `commentary` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

