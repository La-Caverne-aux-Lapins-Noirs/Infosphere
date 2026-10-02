-- Sessions autonomes (personne / laboratoire).
-- Les colonnes id_user et id_laboratory existent déjà dans les versions
-- récentes d'Infosphere. Cette migration ajoute uniquement le nom local qui
-- remplace le nom normalement fourni par activity pour une session autonome.

SET @has_session_name := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'session'
      AND COLUMN_NAME = 'name'
);
SET @sql := IF(
    @has_session_name = 0,
    "ALTER TABLE `session` ADD COLUMN `name` varchar(255) DEFAULT NULL COMMENT 'Nom local des sessions sans activité' AFTER `id_user`",
    "SELECT 'session.name already exists'"
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
