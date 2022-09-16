
CREATE TABLE `token` (
  `id` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `status` int(11) DEFAULT NULL,
  `codename` varchar(255) NOT NULL,
  `invalidation_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `token`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_session` (`id_session`);

CREATE TABLE `team` (
  `id` int(11) NOT NULL,
  `team_name` varchar(255) DEFAULT NULL,
  `id_activity` int(11) DEFAULT NULL,
  `id_session` int(11) DEFAULT NULL,
  `present` int(11) NOT NULL DEFAULT 0,
  `declaration_date` datetime DEFAULT NULL,
  `closed` datetime DEFAULT NULL,
  `manual_credit` int(11) DEFAULT NULL,
  `manual_grade` int(11) DEFAULT NULL,
  `commentaries` text DEFAULT NULL,
  `canjoin` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_session` (`id_session`);

CREATE TABLE `user_team` (
  `id` int(11) NOT NULL,
  `id_team` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `status` int(11) DEFAULT NULL,
  `commentaries` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_team` (`id_team`),
  ADD KEY `id_user` (`id_user`);

CREATE TABLE `pickedup_work` (
  `id` int(11) NOT NULL,
  `id_team` int(11) NOT NULL,
  `pickedup_date` datetime DEFAULT NULL,
  `repository` varchar(255) DEFAULT NULL,
  `traces` text DEFAULT NULL,
  `result` longtext DEFAULT NULL,
  `errors` text DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `observation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `pickedup_work`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_team` (`id_team`);

CREATE TABLE `ticket` (
  `id` int(11) NOT NULL,
  `id_team` int(11) NOT NULL,
  `id_sprint` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `real_time` int(11) DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `creation_date` datetime,
  `done_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `ticket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_team` (`id_team`),
  ADD KEY `id_sprint` (`id_sprint`),
  ADD KEY `id_user` (`id_user`);

CREATE TABLE `sprint` (
  `id` int(11) NOT NULL,
  `id_team` int(11) NOT NULL,
  `title` text NOT NULL,
  `start_date` datetime,
  `done_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `sprint`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_team` (`id_team`);
