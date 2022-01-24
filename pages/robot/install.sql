CREATE TABLE `robot` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `version` float NOT NULL,
  `file` text COLLATE utf8_bin NOT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  `complaint` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
