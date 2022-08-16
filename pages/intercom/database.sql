
CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `position` varchar(255) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_laboratory` int(11) DEFAULT NULL,
  `visibility` int(11) DEFAULT NULL,
  `id_misc` int(255) NOT NULL,
  `postdate` datetime NOT NULL DEFAULT current_timestamp(),
  `lastdate` datetime NOT NULL DEFAULT current_timestamp(),
  `id_message` int(11) DEFAULT NULL,
  `title` text DEFAULT NULL,
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_laboratory` (`id_laboratory`),
  ADD KEY `id_misc` (`id_misc`),
  ADD KEY `id_message` (`id_message`);

CREATE TABLE `message_alert` (
  `id` int(11) NOT NULL,
  `id_message` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `status` int(11) DEFAULT NULL,
  `postdate` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `message_alert`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_message` (`id_message`),
  ADD KEY `id_user` (`id_user`);

