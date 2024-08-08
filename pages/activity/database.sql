CREATE TABLE `activity` (
  -- Base --
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `validated` int(11) DEFAULT 0,
  `disabled` datetime DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,

  -- A propos du système de template --
  `is_template` tinyint(1) NOT NULL DEFAULT 0,
  `id_template` int(11) DEFAULT NULL,
  KEY `id_template` (`id_template`),
  `template_link` tinyint(1) NOT NULL DEFAULT 1,
  `medal_template` tinyint(1) NOT NULL DEFAULT 1,
  `support_template` tinyint(1) NOT NULL DEFAULT 1,

  -- Informations sur l'activité --
  `type` int(11) DEFAULT NULL,
  KEY `type` (`type`),
  `parent_activity` int(11) DEFAULT NULL,
  KEY `parent_activity` (`parent_activity`),
  `reference_activity` int(11) DEFAULT NULL,
  KEY `reference_activity` (`reference_activity`),
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
  `declaration_type` int(11) NOT NULL DEFAULT 0 COMMENT 'Le type de déclaration des sessions associées:\r\n0: pas de déclaration\r\n1: déclaration locale seulement (verif par ip)\r\n2 : declaration de n''importe où',
  `allow_unregistration` tinyint(1) DEFAULT NULL,

  -- Dates --
  `emergence_date` datetime DEFAULT NULL,
  KEY `emergence_date` (`emergence_date`),
  `registration_date` datetime DEFAULT NULL,
  KEY `registration_date` (`registration_date`),
  `close_date` datetime DEFAULT NULL,
  KEY `close_date` (`close_date`),
  `subject_appeir_date` datetime DEFAULT NULL,
  KEY `subject_appeir_date` (`subject_appeir_date`),
  `subject_disappeir_date` datetime DEFAULT NULL,
  KEY `subject_disappeir_date` (`subject_disappeir_date`),
  `pickup_date` datetime DEFAULT NULL,
  KEY `pickup_date` (`pickup_date`),
  `done_date` datetime DEFAULT NULL,
  KEY `done_date` (`done_date`),

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

CREATE TABLE `activity_cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`),
  `week_shift` int(11) NOT NULL DEFAULT 0,
  `cursus` text NOT NULL DEFAULT '',
  `replacement_subscription` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_medal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_medal` int(11) NOT NULL,
  KEY `id_medal` (`id_medal`),
  `role` int(11) NOT NULL DEFAULT 1,
  `mark` int(11) NOT NULL DEFAULT 0,
  `local` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_software` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `software` text DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_teacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_user` int(11) DEFAULT NULL,
  KEY `id_user` (`id_user`),
  `id_laboratory` int(11) DEFAULT NULL,
  KEY `id_laboratory` (`id_laboratory`),
  `teacher_pay` int(11) DEFAULT NULL,
  `assistant_pay` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
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

CREATE TABLE `appointment_slot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  `id_team` int(11) DEFAULT NULL,
  KEY `id_team` (`id_session`),
  `begin_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `was_present` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `deleted` datetime DEFAULT NULL,
  `id_activity` int(11) DEFAULT NULL,
  KEY `id_activity` (`id_activity`),
  `id_laboratory` int(11) DEFAULT NULL,
  KEY `id_laboratory` (`id_laboratory`),
  `id_team` int(11) DEFAULT NULL,
  KEY `id_team` (`id_team`),
  `id_user` int(11) DEFAULT NULL,
  KEY `id_user` (`id_user`),
  `begin_date` datetime DEFAULT NULL,
  KEY `begin_date` (`begin_date`),
  `end_date` datetime DEFAULT NULL,
  KEY `end_date` (`end_date`),
  `maximum_subscription` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  `id_room` int(11) NOT NULL,
  KEY `id_room` (`id_room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_skill` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_skill` int(11) NOT NULL,
  KEY `id_skill` (`id_skill`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_support` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY `id` (`id`),
  `id_activity` int(11) NOT NULL,
  KEY `id_activity` (`id_activity`),
  `id_support_category` int(11) DEFAULT NULL,
  KEY `id_support_category` (`id_support_category`),
  `id_support` int(11) DEFAULT NULL,
  KEY `id_support` (`id_support`),
  `id_support_asset` int(11) DEFAULT NULL,
  KEY `id_support_asset` (`id_support_asset`),
  `id_subactivity` text DEFAULT NULL,
  KEY `id_subactivity` (`id_subactivity`),
  `chapter` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
