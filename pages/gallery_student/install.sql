CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `fr_name` varchar(255) COLLATE utf8_bin NOT NULL,
  `fr_description` varchar(255) COLLATE utf8_bin NOT NULL,
  `id_stud_rate` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `stud_rate` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `stud_endpoint` (
  `id` int(11) NOT NULL,
  `id_stud_rate` int(11) NOT NULL,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `valrange` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;