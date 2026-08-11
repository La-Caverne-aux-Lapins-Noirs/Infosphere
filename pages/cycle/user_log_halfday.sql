CREATE TABLE IF NOT EXISTS `user_log_halfday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `log_date` date NOT NULL,
  KEY `log_date` (`log_date`),
  `period` tinyint(1) NOT NULL COMMENT '0: 08h-13h, 1: 13h-19h',
  `type` int(11) NOT NULL,
  KEY `type` (`type`),
  `duration` int(11) NOT NULL DEFAULT 0,
  UNIQUE KEY `user_log_halfday_unique` (`id_user`, `log_date`, `period`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
