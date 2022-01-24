CREATE TABLE `room` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `fr_name` varchar(255) COLLATE utf8_bin NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `map` mediumblob NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `room` (`id`, `codename`, `fr_name`, `capacity`) VALUES
(1, 'no_room', 'Pas de salle', -1);

CREATE TABLE `room_desk` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` tinytext COLLATE utf8_bin NOT NULL,
  `id_room` int(11) NOT NULL,
  `mac` tinytext COLLATE utf8_bin DEFAULT "",
  `ip` tinytext COLLATE utf8_bin DEFAULT "",
  `type` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `id_user` int(11) NOT NULL DEFAULT -1,
  `x` int(11) DEFAULT -1,
  `y` int(11) DEFAULT -1,
  `w` int(11) DEFAULT -1,
  `h` int(11) DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

