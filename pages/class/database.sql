
CREATE TABLE `support` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,

  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `support`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `support_asset` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,

  `id_support` int(11) NOT NULL,
  `chapter` int(11) DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `fr_content` text DEFAULT NULL,
  `fr_link` text DEFAULT NULL,

  `en_name` varchar(255) DEFAULT NULL,
  `en_content` text DEFAULT NULL,
  `en_link` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `support_asset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_support` (`id_support`);

CREATE TABLE `activity_support` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_support` int(11) DEFAULT NULL,
  `id_support_asset` int(11) DEFAULT NULL,
  `id_subactivity` text DEFAULT NULL,
  `chapter` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_support`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_support` (`id_support`),
  ADD KEY `id_support_asset` (`id_support_asset`);
