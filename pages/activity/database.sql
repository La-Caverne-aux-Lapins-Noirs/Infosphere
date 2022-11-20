CREATE TABLE `activity` (
  -- Base --
  `id` int(11) NOT NULL,
  `codename` varchar(255) NOT NULL,
  `disabled` datetime DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,

  -- A propos du système de template --
  `is_template` tinyint(1) NOT NULL DEFAULT 0,
  `id_template` int(11) DEFAULT NULL,
  `template_link` tinyint(1) NOT NULL DEFAULT 1,
  `medal_template` tinyint(1) NOT NULL DEFAULT 1,
  `support_template` tinyint(1) NOT NULL DEFAULT 1,

  -- Informations sur l'activité --
  `type` int(11) DEFAULT NULL,
  `parent_activity` int(11) DEFAULT NULL,
  `reference_activity` int(11) DEFAULT NULL,
  `min_team_size` int(11) DEFAULT NULL,
  `max_team_size` int(11) DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  `mandatory` tinyint(1) DEFAULT NULL,
  `maximum_subscription` int(11) DEFAULT NULL,
  `mark` int(11) DEFAULT NULL,
  `subscription` int(11) DEFAULT NULL,
  `repository_name` varchar(255) DEFAULT NULL,
  `estimated_work_duration` int(11) DEFAULT NULL,
  `slot_duration` int(11) DEFAULT NULL,
  `validation` int(11) DEFAULT 3,
  `credit_a` int(11) DEFAULT NULL,
  `credit_b` int(11) DEFAULT NULL,
  `credit_c` int(11) DEFAULT NULL,
  `credit_d` int(11) DEFAULT NULL,
  `grade_a` int(11) DEFAULT NULL,
  `grade_b` int(11) DEFAULT NULL,
  `grade_c` int(11) DEFAULT NULL,
  `grade_d` int(11) DEFAULT NULL,
  `grade_bonus` int(11) DEFAULT NULL,
  `allow_unregistration` tinyint(1) DEFAULT NULL,

  -- Dates --
  `emergence_date` datetime DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `subject_appeir_date` datetime DEFAULT NULL,
  `subject_disappeir_date` datetime DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,
  `done_date` datetime DEFAULT NULL,

  -- Langues --
  `fr_name` varchar(255) DEFAULT NULL,
  `fr_description` text DEFAULT NULL,
  `fr_objective` text DEFAULT NULL,
  `fr_method` text DEFAULT NULL,
  `fr_reference` text DEFAULT NULL,

  `en_name` varchar(255) DEFAULT NULL,
  `en_description` text DEFAULT NULL,
  `en_objective` text DEFAULT NULL,
  `en_method` text DEFAULT NULL,
  `en_reference` text DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_template` (`id_template`),
  ADD KEY `parent_activity` (`parent_activity`),
  ADD KEY `reference_activity` (`reference_activity`);

CREATE TABLE `activity_cycle` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_cycle` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_cycle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_cycle` (`id_cycle`);

CREATE TABLE `activity_medal` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `role` int(11) NOT NULL DEFAULT 1,
  `mark` int(11) NOT NULL DEFAULT 0,
  `local` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_medal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_medal` (`id_medal`);

CREATE TABLE `activity_software` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `software` text DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_software`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`);

CREATE TABLE `activity_teacher` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `id_laboratory` int(11) DEFAULT NULL,
  `teacher_pay` int(11) DEFAULT NULL,
  `assistant_pay` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_teacher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_laboratory` (`id_laboratory`);

CREATE TABLE `activity_type` (
  `id` int(11) NOT NULL,
  `codename` varchar(32) NOT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `activity_type` (`id`, `codename`, `type`) VALUES
(1, 'LearningDay', 2),
(2, 'PracticalWork', 2),
(3, 'Class', 2),
(4, 'Debate', 2),
(5, 'Exam', 2),
(6, 'MCQ', 2),
(7, 'Recode', 2),
(8, 'Challenge', 2),
(9, 'Marathon', 2),
(10, 'DailyMeeting', 2),
(11, 'PlanificationMeeting', 2),
(12, 'DemoMeeting', 2),
(13, 'RetrospectiveMeeting', 2),
(14, 'Project', 1),
(15, 'Rush', 1),
(16, 'MiniProject', 1),
(17, 'Product', 1),
(18, 'Module', 0),
(19, 'Misc', 2),
(20, 'Exercise', 1),
(21, 'PickableExercise', 0);

ALTER TABLE `activity_type`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `activity_user_medal` (
  `id` int(11) NOT NULL,
  `id_activity` int(11) NOT NULL,
  `id_user_medal` int(11) NOT NULL,
  `insert_date` datetime DEFAULT current_timestamp(),
  `result` int(11) DEFAULT NULL,
  `specifier` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `activity_user_medal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_user_medal` (`id_user_medal`);

CREATE TABLE `appointment_slot` (
  `id` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `id_team` int(11) DEFAULT NULL,
  `begin_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `was_present` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `appointment_slot`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_session` (`id_session`),
  ADD KEY `id_team` (`id_team`);

CREATE TABLE `session` (
  `id` int(11) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  `id_activity` int(11) DEFAULT NULL,
  `id_laboratory` int(11) DEFAULT NULL,
  `id_team` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `maximum_subscription` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_activity` (`id_activity`),
  ADD KEY `id_laboratory` (`id_laboratory`),
  ADD KEY `id_team` (`id_team`),
  ADD KEY `id_user` (`id_user`);

CREATE TABLE `session_room` (
  `id` int(11) NOT NULL,
  `id_session` int(11) NOT NULL,
  `id_room` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `session_room`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_session` (`id_session`),
  ADD KEY `id_room` (`id_room`);
