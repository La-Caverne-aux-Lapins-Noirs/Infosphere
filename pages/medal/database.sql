
CREATE TABLE `medal` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,

  `type` int(11) DEFAULT NULL,
  `tags` varchar(32) DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `medal`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `medal_medal` (
  `id` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `id_implied_medal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `medal_medal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_medal` (`id_medal`),
  ADD KEY `id_implied_medal` (`id_implied_medal`);

