
CREATE TABLE `skill` (
  `id` int(11) NOT NULL,
  `codename` varchar(128) NOT NULL,
  `fr_description` text DEFAULT NULL,
  `en_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `skill`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `activity_skill` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_skill` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_skill`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_skill` (`id_skill`);
