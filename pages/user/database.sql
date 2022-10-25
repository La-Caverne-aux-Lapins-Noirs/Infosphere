
CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  -- Bases --
  `codename` varchar(64) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `mail` varchar(255) NOT NULL,
  `password` tinytext NOT NULL,
  `salt` varchar(512) NOT NULL,
  `local_salt` varchar(512) NOT NULL,

  -- Informations lié au site --
  `outside_secret` varchar(32) DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `visibility` int(11) NOT NULL DEFAULT 3,
  `authority` int(11) NOT NULL DEFAULT 0,
  `money` int(11) NOT NULL DEFAULT 0,
  `bookbail` datetime DEFAULT NULL,

  -- Informations personnelles
  `nickname` varchar(255) DEFAULT NULL,
  `first_name` tinytext DEFAULT NULL,
  `family_name` tinytext DEFAULT NULL,
  `birth_date` datetime DEFAULT NULL,
  `phone` text DEFAULT NULL,
  `address_name` text DEFAULT NULL,
  `street_name` text DEFAULT NULL,
  `postal_code` text DEFAULT NULL,
  `city` text DEFAULT NULL,
  `country` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `user_cycle` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_cycle` int(11) NOT NULL,
  `hidden` int(11) DEFAULT NULL,
  `commentaries` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_cycle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_cycle` (`id_cycle`);

CREATE TABLE `user_log` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp(),
  `last_log` datetime DEFAULT NULL,
  `last_ip` text NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

CREATE TABLE `user_medal` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `insert_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_medal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_medal` (`id_medal`);

CREATE TABLE `user_school` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_school` int(11) NOT NULL,
  `authority` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_school`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_school` (`id_school`);

CREATE TABLE `parent_child` (
  `id` int(11) NOT NULL,
  `id_parent` int(11) NOT NULL,
  `id_child` int(11) NOT NULL,
  `relation` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `parent_child`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_parent` (`id_parent`),
  ADD KEY `id_child` (`id_child`);
