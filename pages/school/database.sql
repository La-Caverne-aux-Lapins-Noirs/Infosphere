CREATE TABLE `school` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,

  `fr_name` varchar(255) NOT NULL,
  `en_name` varchar(255) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `school_cycle` (
  `id` int(11) NOT NULL,
  `id_school` int(11) NOT NULL,
  `id_cycle` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school_cycle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_school` (`id_school`),
  ADD KEY `id_cycle` (`id_cycle`);

CREATE TABLE `school_laboratory` (
  `id` int(11) NOT NULL,
  `id_school` int(11) NOT NULL,
  `id_laboratory` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school_laboratory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_school` (`id_school`),
  ADD KEY `id_laboratory` (`id_laboratory`);

CREATE TABLE `school_room` (
  `id` int(11) NOT NULL,
  `id_school` int(11) NOT NULL,
  `id_room` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school_room`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_school` (`id_school`),
  ADD KEY `id_room` (`id_room`);

