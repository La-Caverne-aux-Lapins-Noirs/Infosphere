
CREATE TABLE `school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `fr_name` varchar(255) NOT NULL,
  `en_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_laboratory` int(11) NOT NULL,
  KEY `id_laboratory` (`id_laboratory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_room` int(11) NOT NULL,
  KEY `id_room` (`id_room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
