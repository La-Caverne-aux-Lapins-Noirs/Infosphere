
CREATE TABLE `support` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_support_category` int(11) NOT NULL,
  KEY `id_support_category` (`id_support_category`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  `fr_name` text DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` text DEFAULT NULL,
  `en_description` text DEFAULT NULL,
  `chapter` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_asset` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `id_support` int(11) NOT NULL,
  KEY `id_support` (`id_support`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `chapter` int(11) NOT NULL DEFAULT 0,
  `fr_name` varchar(255) DEFAULT NULL,
  `fr_content` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `support_asset_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_support_asset` int(11) NOT NULL,
  KEY `id_support_asset` (`id_support_asset`),
  `progress` int(11) NOT NULL DEFAULT 0,
  `first_view` datetime DEFAULT NULL,
  `last_view` datetime NOT NULL DEFAULT current_timestamp(),
  `finished` datetime DEFAULT NULL,
  UNIQUE KEY `support_asset_view_user_asset` (`id_user`, `id_support_asset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
