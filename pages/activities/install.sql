
CREATE TABLE `activity` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,

  `enabled` tinyint(1) NOT NULL DEFAULT 0, -- est ce que le template est censé etre utilisé
  `is_template` int(1) NOT NULL DEFAULT 0,
  `id_template` int(11) NOT NULL DEFAULT -1,
  `template_link` tinyint(1) NOT NULL DEFAULT 1, -- Utilise-t-on les valeurs du template ?
  `medal_template` tinyint(1) NOT NULL DEFAULT 1, -- Utilise-t-on encore les médailles du template ?
  `class_template` tinyint(1) NOT NULL DEFAULT 1, -- Utilise-t-on encore les supports de cours du template ?

  `parent_activity` int(11) DEFAULT NULL, -- L'activité parent (la matière, par exemple, ou le cursus)
  `reference_activity` int(11) DEFAULT NULL, -- L'activité dont on va tirer les equipes, les medailles, etc...

  `fr_name` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `fr_description` text COLLATE utf8_bin DEFAULT NULL,
  `type` int(11) DEFAULT NULL, -- liaison avec activity_type
  `hidden` tinyint(1) DEFAULT NULL, -- est ce que l'activité est affichée ou non parmi les autres modules
  `mandatory` tinyint(1) DEFAULT NULL, -- est ce que l'activité est obligatoire
  `min_team_size` int(1) DEFAULT NULL,
  `max_team_size` int(1) DEFAULT NULL,
  `credit` int(1) DEFAULT NULL, -- si c'est un module, le nombre de credit que ca rapporte
  `mark` int(1) DEFAULT NULL, -- le nombre de mark gagné en cas de réussite
  `price` int(11) DEFAULT NULL, -- le prix en euro de l'activité
  `slot_duration` int(11) DEFAULT NULL, -- la durée d'un rendez vous typique pour cette activité
  `estimated_work_duration` int(1) DEFAULT NULL, -- Le temps estimé néccessaire en heure

  `validation_by_percent` tinyint(1) DEFAULT NULL, -- On valide par pourcentage ou par tranche ?
  `grade_module` tinyint(1) DEFAULT NULL, -- 0: validation par medaille, 1: par note
  `no_grade` tinyint(1) DEFAULT NULL, -- 0: il y a validation, 1: il n'y a pas validation
  `grade_a` int(11) DEFAULT NULL, -- si on valide par tranche, le pourcentage de medaille A a obtenir
  `grade_b` int(11) DEFAULT NULL, -- si on valide par tranche, le pourcentage de medaille B a obtenir
  `grade_c` int(11) DEFAULT NULL, -- si on valide par tranche, le pourcentage de medaille C a obtenir
  `grade_d` int(11) DEFAULT NULL, -- si on valide par tranche, le pourcentage de medaille D a obtenir
  `grade_bonus` int(11) DEFAULT NULL, -- si on valide par tranche, le pourcentage de medaille autre a obtenir

  `subscription` int(11) DEFAULT NULL, -- 0: optionnel, 1: obligatoire, 2: automatique
  `allow_unregistration` int(1) DEFAULT NULL, -- 0: on peut se desinscrire, 1: on peut pas
  `maximum_subscription` int(11) DEFAULT NULL, -- Le nombre d'inscrit max, -1 si infini
  `repository_name` text DEFAULT NULL, -- Le moyen de ramassage
  `reference_repository` text DEFAULT NULL, -- Le repo qui contient le binaire de reference  

  `emergence_date` datetime DEFAULT NULL,
  `registration_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `subject_appeir_date` datetime DEFAULT NULL,
  `subject_disappeir_date` datetime DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,
  `done_date` datetime DEFAULT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_cycle` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_activity` int(11) KEY NOT NULL,
  `id_cycle` int(11) KEY NOT NULL,
  `mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `tags` varchar(512) COLLATE utf8_bin NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `session` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_template` int(11) NOT NULL DEFAULT -1, -- Est ce que cette session est l'instance d'une autre?
  `id_activity` int(11) NOT NULL,

  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `max_subscription` int(11) DEFAULT NULL,
  `allow_unregistration` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- status: 0: normale, 1 obligatoire
CREATE TABLE `activity_medal` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_activity` int(11) NOT NULL,
  `id_medal` int(11) NOT NULL,
  `status` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `activity_teacher` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT -1,
  `id_laboratory` int(11) NOT NULL DEFAULT -1,
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-------------------
-- ACTIVITY TYPE -
-----------------
CREATE TABLE `activity_type` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(32) COLLATE utf8_bin NOT NULL,
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
(21, 'PickableExercise', 1);

ALTER TABLE `activity_type`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `activity_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
