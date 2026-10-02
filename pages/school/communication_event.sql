CREATE TABLE IF NOT EXISTS `session_school` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_session` int(11) NOT NULL,
  KEY `id_session` (`id_session`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  UNIQUE KEY `session_school_unique` (`id_session`, `id_school`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `communication_event` (
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

SET @has_event_parental_authorization := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'communication_event'
    AND column_name = 'parental_authorization_required'
);
SET @sql := IF(
  @has_event_parental_authorization = 0,
  'ALTER TABLE `communication_event` ADD COLUMN `parental_authorization_required` tinyint(1) NOT NULL DEFAULT 0 AFTER `description`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `communication_event_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_communication_event` int(11) NOT NULL,
  KEY `id_communication_event` (`id_communication_event`),
  `id_session` int(11) NOT NULL,
  UNIQUE KEY `communication_event_session_unique` (`id_communication_event`, `id_session`),
  UNIQUE KEY `communication_event_session_session` (`id_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

SET @has_user_form_event := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_form'
    AND column_name = 'id_communication_event'
);
SET @sql := IF(
  @has_user_form_event = 0,
  'ALTER TABLE `user_form` ADD COLUMN `id_communication_event` int(11) DEFAULT NULL, ADD KEY `id_communication_event` (`id_communication_event`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_user_form_event_cancelled := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_form'
    AND column_name = 'event_cancelled_at'
);
SET @sql := IF(
  @has_user_form_event_cancelled = 0,
  'ALTER TABLE `user_form` ADD COLUMN `event_cancelled_at` datetime DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
