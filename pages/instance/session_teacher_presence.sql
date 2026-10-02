CREATE TABLE IF NOT EXISTS `session_teacher_presence` (
  `id_session` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `attendance_day` date NOT NULL,
  `declared_at` datetime NOT NULL,
  PRIMARY KEY (`id_session`, `id_user`, `attendance_day`),
  KEY `id_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE `session`
  ADD COLUMN `signin_generated_at` datetime DEFAULT NULL,
  ADD COLUMN `signin_id_actor` int(11) DEFAULT NULL,
  ADD COLUMN `signin_morning_end` char(5) NOT NULL DEFAULT '13:00',
  ADD COLUMN `signin_afternoon_start` char(5) NOT NULL DEFAULT '14:00',
  ADD COLUMN `signin_sha256` char(64) DEFAULT NULL;

-- Si la première version du SQL a été exécutée, conserver ses métadonnées
-- avant de supprimer la table 1-pour-1.
SET @session_signin_old_table = (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'session_signin_document'
);
SET @session_signin_migration = IF(@session_signin_old_table > 0,
  'UPDATE `session` AS s INNER JOIN `session_signin_document` AS d ON d.id_session = s.id SET s.signin_generated_at = d.generated_at, s.signin_id_actor = d.id_actor, s.signin_morning_end = d.morning_end, s.signin_afternoon_start = d.afternoon_start, s.signin_sha256 = d.sha256 WHERE s.signin_generated_at IS NULL',
  'SELECT 1'
);
PREPARE session_signin_migration FROM @session_signin_migration;
EXECUTE session_signin_migration;
DEALLOCATE PREPARE session_signin_migration;
DROP TABLE IF EXISTS `session_signin_document`;
