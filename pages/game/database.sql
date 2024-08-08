CREATE TABLE `game_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `element` varchar(255) NOT NULL,
  `id_element` int(11) NOT NULL,
  KEY `id_element` (`id_element`),
  `event_date` datetime NOT NULL,
  `done` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
