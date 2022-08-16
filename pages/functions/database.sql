
CREATE TABLE `function` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `function`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `function_medal` (
  `id` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `id_function` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `function_medal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_medal` (`id_medal`),
  ADD KEY `id_function` (`id_function`);
