CREATE TABLE `laboratory` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `icon` varchar(255) COLLATE utf8_bin DEFAULT "",
  `fr_name` varchar(255) COLLATE utf8_bin DEFAULT "",
  `fr_description` text COLLATE utf8_bin DEFAULT "",
  `deleted` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

