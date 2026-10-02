CREATE TABLE IF NOT EXISTS `user_console_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `name` varchar(80) NOT NULL DEFAULT 'Terminal',
  `token_hash` char(64) NOT NULL,
  UNIQUE KEY `token_hash` (`token_hash`),
  `scope` varchar(255) NOT NULL DEFAULT 'console.read',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
