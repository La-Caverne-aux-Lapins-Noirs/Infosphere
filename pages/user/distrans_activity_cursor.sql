CREATE TABLE IF NOT EXISTS `user_log_distrans_cursor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  `source_key` varchar(191) COLLATE utf8_bin NOT NULL,
  `reset_token` varchar(191) COLLATE utf8_bin NOT NULL DEFAULT '',
  `xtime` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sshtime` bigint(20) unsigned NOT NULL DEFAULT 0,
  `ssh_idle_time` bigint(20) unsigned NOT NULL DEFAULT 0,
  `locktime` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  UNIQUE KEY `user_log_distrans_cursor_unique` (`id_user`, `source_key`),
  KEY `id_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
