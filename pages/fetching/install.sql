
CREATE TABLE `pickedup_work` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_team` int(11) NOT NULL,
  `id_instance` int(11) NOT NULL,
  `id_session` int(11) NOT NULL DEFAULT -1,
  `pickedup_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `repository` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `delivered_work` longblob DEFAULT NULL,
  `traces` longblob DEFAULT NULL,
  `result` longtext COLLATE utf8_bin DEFAULT NULL,
  `errors` text COLLATE utf8_bin DEFAULT NULL,
  `status` varchar(255) DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
