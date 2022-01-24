
CREATE TABLE `cycle` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(64) COLLATE utf8_bin NOT NULL,
  `first_day` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cycle` int(11) NOT NULL DEFAULT -1,
  `done` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Responsable de cycle
CREATE TABLE `cycle_teacher` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_cycle` int(11) NOT NULL,
  `id_user` int(11) NOT NULL DEFAULT -1
  `id_laboratory` int(11) NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
