CREATE TABLE IF NOT EXISTS `school_responsibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) DEFAULT NULL,
  KEY `id_school` (`id_school`),
  `scope_school_id` int(11) NOT NULL DEFAULT 0,
  `codename` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fr_name` varchar(255) NOT NULL,
  `en_name` varchar(255) NOT NULL,
  `student_assignable` tinyint(1) NOT NULL DEFAULT 0,
  `show_on_school_home` tinyint(1) NOT NULL DEFAULT 0,
  `icon` varchar(32) DEFAULT NULL,
  `insert_date` datetime NOT NULL DEFAULT current_timestamp(),
  `id_creator` int(11) DEFAULT NULL,
  KEY `id_creator` (`id_creator`),
  `deleted` datetime DEFAULT NULL,
  KEY `deleted` (`deleted`),
  `id_deleter` int(11) DEFAULT NULL,
  KEY `id_deleter` (`id_deleter`),
  UNIQUE KEY `school_responsibility_scope_codename` (`scope_school_id`, `codename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `school_responsibility`
(`id_school`, `scope_school_id`, `codename`, `fr_name`, `en_name`, `student_assignable`, `show_on_school_home`, `icon`)
VALUES
(NULL, 0, 'FIRE_SAFETY', 'Sécurité incendie', 'Fire safety', 1, 0, '🔥'),
(NULL, 0, 'REF_INCLUSION_EQUITY', 'Référent inclusion et de l’équité', 'Inclusion and equity referent', 0, 1, NULL),
(NULL, 0, 'REF_DISABILITY', 'Référent handicap (nationale et internationale)', 'Disability referent (national and international)', 0, 1, NULL),
(NULL, 0, 'REF_MOBILITY', 'Référent personnel dédié à la mobilité (nationale et internationale)', 'Mobility officer (national and international)', 0, 1, NULL),
(NULL, 0, 'REF_QUALITY', 'Référent qualité', 'Quality referent', 0, 1, NULL),
(NULL, 0, 'REF_RGPD', 'Référent RGPD', 'GDPR referent', 0, 1, NULL),
(NULL, 0, 'REF_COMPLAINTS', 'Référent réclamations', 'Complaints referent', 0, 1, NULL),
(NULL, 0, 'REF_HARASSMENT_DISCRIMINATION', 'Référent harcèlement / discrimination', 'Harassment / discrimination referent', 0, 1, NULL),
(NULL, 0, 'REF_PEDAGOGY', 'Référent pédagogique', 'Academic referent', 0, 1, NULL),
(NULL, 0, 'REF_IMPROVEMENT_COUNCIL', 'Référent conseil de perfectionnement', 'Improvement council referent', 0, 1, NULL),
(NULL, 0, 'REF_ADMIN_ABSENCE', 'Référent administration / absences', 'Administration / absences referent', 0, 1, NULL),
(NULL, 0, 'REF_INSERTION_SOCIAL', 'Référent insertion ou accompagnement social', 'Employment integration or social support referent', 0, 1, NULL),
(NULL, 0, 'REF_COMPANY', 'Référent entreprise (alternance, stage)', 'Company relations referent (work-study, internship)', 0, 1, NULL)
ON DUPLICATE KEY UPDATE
  `fr_name` = VALUES(`fr_name`),
  `en_name` = VALUES(`en_name`),
  `student_assignable` = VALUES(`student_assignable`),
  `show_on_school_home` = VALUES(`show_on_school_home`),
  `icon` = VALUES(`icon`),
  `deleted` = NULL,
  `id_deleter` = NULL;

CREATE TABLE IF NOT EXISTS `user_school_responsibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_user` int(11) NOT NULL,
  KEY `id_user` (`id_user`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_school_responsibility` int(11) NOT NULL,
  KEY `id_school_responsibility` (`id_school_responsibility`),
  `id_actor` int(11) DEFAULT NULL,
  KEY `id_actor` (`id_actor`),
  `insert_date` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted` datetime DEFAULT NULL,
  KEY `deleted` (`deleted`),
  `id_deleter` int(11) DEFAULT NULL,
  KEY `id_deleter` (`id_deleter`),
  KEY `user_school_responsibility_active` (`id_user`, `id_school`, `id_school_responsibility`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
