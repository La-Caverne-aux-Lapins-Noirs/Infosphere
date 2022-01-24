
CREATE TABLE `token` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_session` int(11) NOT NULL,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `invalidation_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

