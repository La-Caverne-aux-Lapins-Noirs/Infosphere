
CREATE TABLE `cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(64) NOT NULL,
  `is_template` int(11) DEFAULT NULL,
  `id_template` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,

  `first_day` datetime DEFAULT NULL,
  `cycle` int(11) NOT NULL,
  `objective` int(11) NOT NULL DEFAULT 100,
  `done` int(11) DEFAULT NULL,

  `fr_name` text DEFAULT NULL,
  `en_name` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `cycle_teacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`),
  `id_user` int(11) DEFAULT NULL,
  KEY `id_user` (`id_user`),
  `id_laboratory` int(11) DEFAULT NULL,
  KEY `id_laboratory` (`id_laboratory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
