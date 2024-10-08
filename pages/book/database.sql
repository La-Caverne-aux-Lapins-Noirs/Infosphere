
CREATE TABLE `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `name` text DEFAULT NULL,
  `authors` text DEFAULT NULL,
  `edition` date DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL COMMENT 'id_lender'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `book_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_book` int(11) NOT NULL,
  KEY `id_book` (`id_book`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0: demande d''emprunt. 1: validé. 2: emprunté. 3: rendu. -1: refusé',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
