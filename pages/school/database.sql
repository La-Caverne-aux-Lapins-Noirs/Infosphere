
CREATE TABLE `school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `codename` varchar(255) NOT NULL,
  `base_url` varchar(512) DEFAULT NULL,
  `is_school` tinyint(1) DEFAULT NULL,
  `is_of` tinyint(1) DEFAULT NULL,
  `is_cfa` tinyint(1) DEFAULT NULL,
  `deleted` datetime DEFAULT NULL,
  `fr_name` varchar(255) NOT NULL,
  `en_name` varchar(255) NOT NULL,
  `legal_name` varchar(128) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `uai` varchar(16) NOT NULL DEFAULT '',
  `cfa_name` varchar(255) NOT NULL DEFAULT '',
  `executing_establishment_name` varchar(255) NOT NULL DEFAULT '',
  `main_info` text NOT NULL DEFAULT '',
  `school_info` text NOT NULL DEFAULT '',
  `formation_info` text NOT NULL DEFAULT '',
  `alternation_info` text NOT NULL DEFAULT '',
  `document_information` text NOT NULL DEFAULT '',
  `vat_exemption_mention` varchar(512) NOT NULL DEFAULT 'TVA exonérée — article 261-4-4°-a du CGI.',
  `phone` varchar(16) NOT NULL DEFAULT '',
  `mail` text NOT NULL DEFAULT '',
  `technocore_configuration_json` longtext NOT NULL DEFAULT '{}',
  `diploma_secret` varchar(128) DEFAULT NULL,
  `electronic_invoice_connector_key` varchar(64) NOT NULL DEFAULT '',
  `electronic_invoice_connector_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `electronic_invoice_connector_environment` varchar(16) NOT NULL DEFAULT 'test',
  `electronic_invoice_connector_endpoint` varchar(512) NOT NULL DEFAULT '',
  `electronic_invoice_connector_configuration_json` longtext DEFAULT NULL,
  `electronic_invoice_connector_actor` int(11) DEFAULT NULL,
  `electronic_invoice_connector_updated_at` datetime DEFAULT NULL,
  `electronic_invoice_receive_cursor` varchar(64) NOT NULL DEFAULT '',
  `electronic_invoice_receive_at` datetime DEFAULT NULL,
  `electronic_invoice_payment_reporting` tinyint(1) NOT NULL DEFAULT 0

) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `technocore_configuration_json` longtext NOT NULL DEFAULT '{}' AFTER `mail`;

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `uai` varchar(16) NOT NULL DEFAULT '' AFTER `address`,
  ADD COLUMN IF NOT EXISTS `cfa_name` varchar(255) NOT NULL DEFAULT '' AFTER `uai`,
  ADD COLUMN IF NOT EXISTS `executing_establishment_name` varchar(255) NOT NULL DEFAULT '' AFTER `cfa_name`,
  ADD COLUMN IF NOT EXISTS `diploma_secret` varchar(128) DEFAULT NULL AFTER `mail`;

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `document_information` text NOT NULL DEFAULT '' AFTER `alternation_info`,
  ADD COLUMN IF NOT EXISTS `vat_exemption_mention` varchar(512) NOT NULL DEFAULT 'TVA exonérée — article 261-4-4°-a du CGI.' AFTER `document_information`;

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_key` varchar(64) NOT NULL DEFAULT '' AFTER `diploma_secret`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `electronic_invoice_connector_key`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_environment` varchar(16) NOT NULL DEFAULT 'test' AFTER `electronic_invoice_connector_enabled`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_endpoint` varchar(512) NOT NULL DEFAULT '' AFTER `electronic_invoice_connector_environment`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_configuration_json` longtext DEFAULT NULL AFTER `electronic_invoice_connector_endpoint`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_actor` int(11) DEFAULT NULL AFTER `electronic_invoice_connector_configuration_json`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_updated_at` datetime DEFAULT NULL AFTER `electronic_invoice_connector_actor`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_receive_cursor` varchar(64) NOT NULL DEFAULT '' AFTER `electronic_invoice_connector_updated_at`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_receive_at` datetime DEFAULT NULL AFTER `electronic_invoice_receive_cursor`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_payment_reporting` tinyint(1) NOT NULL DEFAULT 0 AFTER `electronic_invoice_receive_at`;


CREATE TABLE `school_responsibility` (
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

CREATE TABLE `user_school_responsibility` (
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

CREATE TABLE `school_cycle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_cycle` int(11) NOT NULL,
  KEY `id_cycle` (`id_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_laboratory` int(11) NOT NULL,
  KEY `id_laboratory` (`id_laboratory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `id_room` int(11) NOT NULL,
  KEY `id_room` (`id_room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


CREATE TABLE `communication_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL DEFAULT '',
  `parental_authorization_required` tinyint(1) NOT NULL DEFAULT 0,
  `token_hash` char(64) NOT NULL,
  UNIQUE KEY `token_hash` (`token_hash`),
  `token_secret` text NOT NULL,
  `id_creator` int(11) NOT NULL,
  KEY `id_creator` (`id_creator`),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `communication_event_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_communication_event` int(11) NOT NULL,
  KEY `id_communication_event` (`id_communication_event`),
  `id_session` int(11) NOT NULL,
  UNIQUE KEY `communication_event_session_unique` (`id_communication_event`, `id_session`),
  UNIQUE KEY `communication_event_session_session` (`id_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `school_mailbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `purpose` varchar(64) NOT NULL,
  `mail` varchar(255) NOT NULL,
  `deleted` datetime DEFAULT NULL,
  UNIQUE KEY `school_mailbox_purpose` (`id_school`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

