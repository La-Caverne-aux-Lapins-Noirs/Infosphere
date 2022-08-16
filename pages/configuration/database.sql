
CREATE TABLE `configuration` (
  `id` int(11) NOT NULL,
  `codename` varchar(64) NOT NULL,
  `value` varchar(256) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `configuration`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `log` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type` int(11) NOT NULL,
  `url` text DEFAULT NULL,
  `urlhash` int(11) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `ip` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `urlhash` (`urlhash`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `ip` (`ip`);
