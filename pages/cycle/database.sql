
CREATE TABLE `cycle` (
  `id` int(11) NOT NULL,
  `codename` varchar(64) NOT NULL,
  `is_template` int(11) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,

  `first_day` datetime DEFAULT NULL,
  `cycle` int(11) NOT NULL,
  `done` int(11) DEFAULT NULL,

  `fr_name` text DEFAULT NULL,
  `en_name` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `cycle`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `cycle_teacher` (
  `id` int(11) NOT NULL,
  `id_cycle` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_laboratory` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `cycle_teacher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cycle` (`id_cycle`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_laboratory` (`id_laboratory`);
