CREATE TABLE `medal` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT -1,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `fr_name` varchar(255) COLLATE utf8_bin DEFAULT "",
  `fr_description` tinytext COLLATE utf8_bin DEFAULT "",
  `icon` varchar(255) COLLATE utf8_bin DEFAULT "",
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `type` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `medal_medal` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_medal` int(11) NOT NULL,
  `id_implied_medal` int(11) NOT NULL,
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

