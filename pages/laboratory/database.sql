
CREATE TABLE `laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `user_laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_laboratory` int(11) NOT NULL,
  KEY `id_laboratory` (`id_laboratory`),
  `authority` int(11) NOT NULL DEFAULT 0 COMMENT '0: membre, 1: assistant, 2: prof, 3: chef'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
