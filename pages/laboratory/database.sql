
CREATE TABLE `laboratory` (
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,

  `icon` varchar(255) DEFAULT NULL,

  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `laboratory`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `user_laboratory` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_laboratory` int(11) NOT NULL,
  `authority` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `user_laboratory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_laboratory` (`id_laboratory`);
