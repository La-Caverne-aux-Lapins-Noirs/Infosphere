
CREATE TABLE `room` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime(1) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `map` mediumblob DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `room`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `room_desk` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `id_room` int(11) NOT NULL,
  `mac` varchar(255) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `type` int(11) NOT NULL,
  `status` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `x` int(11) DEFAULT NULL,
  `y` int(11) DEFAULT NULL,
  `w` int(11) DEFAULT NULL,
  `h` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `room_desk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_room` (`id_room`);
