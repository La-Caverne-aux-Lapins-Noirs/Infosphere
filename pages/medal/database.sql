
CREATE TABLE `medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `hidden` datetime DEFAULT NULL COMMENT 'Cache la médaille du menu médaille. Utile pour dépréciser une médaille sans la supprimer.',

  `type` int(11) DEFAULT NULL,
  `tags` varchar(32) DEFAULT NULL,
  `command` varchar(255) DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `medal_medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_medal` int(11) NOT NULL,
  KEY `id_medal` (`id_medal`),
  `id_implied_medal` int(11) NOT NULL,
  KEY `id_implied_medal` (`id_implied_medal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

