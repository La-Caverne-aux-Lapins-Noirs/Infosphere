
CREATE TABLE `skill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(128) NOT NULL,
  `fr_description` text DEFAULT NULL,
  `en_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
