
CREATE TABLE `message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_laboratory` int(11) DEFAULT NULL,
  KEY `id_laboratory` (`id_laboratory`),
  `visibility` int(11) DEFAULT NULL,
  `misc_type` varchar(255) NOT NULL COMMENT 'En général, le nom de la table associée.',
  `id_misc` int(255) NOT NULL,
  KEY `id_misc` (`id_misc`),
  `postdate` datetime NOT NULL DEFAULT current_timestamp(),
  `lastdate` datetime NOT NULL DEFAULT current_timestamp(),
  `id_message` int(11) DEFAULT NULL,
  KEY `id_message` (`id_message`),
  `title` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `alert` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `message_key` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user_from` int(11) NOT NULL,
  KEY `id_user_from` (`id_user_from`),
  `id_user_to` int(11) NOT NULL,
  KEY `id_user_to` (`id_user_to`),
  `userkey` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `message_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_message` int(11) NOT NULL,
  KEY `id_message` (`id_message`),
  `view_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

