-- Infosphere - réparation des définitions de médailles et remise à zéro Albedo
-- MariaDB 10.5+
--
-- Cette migration est volontairement destructive pour les seules attributions
-- Albedo : toutes les lignes user_medal dont le codename commence par albedo_
-- sont supprimées. Les définitions medal, elles, sont conservées et dédoublonnées.
--
-- Pour les autres médailles, le plus petit id non supprimé devient l'identité
-- canonique (ou le plus petit id si toutes les définitions sont supprimées).
-- Toutes les références connues sont ensuite rebranchées vers cet id.
--
-- Faire une sauvegarde de la base avant exécution.

SELECT codename, COUNT(*) AS definitions
FROM medal
GROUP BY codename
HAVING COUNT(*) > 1
ORDER BY definitions DESC, codename;

SELECT COUNT(*) AS albedo_user_medals_to_delete
FROM user_medal
INNER JOIN medal ON medal.id = user_medal.id_medal
WHERE LEFT(medal.codename, 7) = 'albedo_';

START TRANSACTION;

-- Albedo est un système d'alerte : on repart sans anciennes attributions.
DELETE user_medal
FROM user_medal
INNER JOIN medal ON medal.id = user_medal.id_medal
WHERE LEFT(medal.codename, 7) = 'albedo_';

-- Permet aux alertes encore actives d'être rematérialisées au prochain passage
-- d'Albedo, sans attendre l'ancien cooldown enregistré avant ce nettoyage.
UPDATE user_guidance
SET last_medal_date = NULL
WHERE last_medal_date IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS medal_keep;
CREATE TEMPORARY TABLE medal_keep (
    codename VARCHAR(255) NOT NULL,
    keep_id INT NOT NULL,
    PRIMARY KEY (codename),
    KEY (keep_id)
) ENGINE=MEMORY
AS
SELECT
    codename,
    COALESCE(MIN(CASE WHEN deleted IS NULL THEN id END), MIN(id)) AS keep_id
FROM medal
GROUP BY codename
HAVING COUNT(*) > 1;

DROP TEMPORARY TABLE IF EXISTS medal_map;
CREATE TEMPORARY TABLE medal_map (
    duplicate_id INT NOT NULL,
    keep_id INT NOT NULL,
    PRIMARY KEY (duplicate_id),
    KEY (keep_id)
) ENGINE=MEMORY
AS
SELECT medal.id AS duplicate_id, medal_keep.keep_id
FROM medal
INNER JOIN medal_keep ON medal_keep.codename = medal.codename
WHERE medal.id <> medal_keep.keep_id;

-- La contrainte (id_quiz_attempt, id_medal) peut entrer en collision lorsque
-- deux anciennes définitions de la même médaille sont fusionnées.
DELETE um_later
FROM user_medal AS um_later
INNER JOIN medal AS medal_later ON medal_later.id = um_later.id_medal
INNER JOIN user_medal AS um_earlier
    ON um_earlier.id_quiz_attempt = um_later.id_quiz_attempt
   AND um_earlier.id < um_later.id
INNER JOIN medal AS medal_earlier
    ON medal_earlier.id = um_earlier.id_medal
   AND medal_earlier.codename = medal_later.codename
WHERE um_later.id_quiz_attempt IS NOT NULL;

UPDATE user_medal
INNER JOIN medal_map ON medal_map.duplicate_id = user_medal.id_medal
SET user_medal.id_medal = medal_map.keep_id;

UPDATE activity_medal
INNER JOIN medal_map ON medal_map.duplicate_id = activity_medal.id_medal
SET activity_medal.id_medal = medal_map.keep_id;

UPDATE function_medal
INNER JOIN medal_map ON medal_map.duplicate_id = function_medal.id_medal
SET function_medal.id_medal = medal_map.keep_id;

UPDATE medal_medal
INNER JOIN medal_map ON medal_map.duplicate_id = medal_medal.id_medal
SET medal_medal.id_medal = medal_map.keep_id;

UPDATE medal_medal
INNER JOIN medal_map ON medal_map.duplicate_id = medal_medal.id_implied_medal
SET medal_medal.id_implied_medal = medal_map.keep_id;

UPDATE user_money_log
INNER JOIN medal_map ON medal_map.duplicate_id = user_money_log.id_medal
SET user_money_log.id_medal = medal_map.keep_id;

-- Relations devenues redondantes après fusion.
DELETE mm
FROM medal_medal AS mm
WHERE mm.id_medal = mm.id_implied_medal;

DELETE mm_later
FROM medal_medal AS mm_later
INNER JOIN medal_medal AS mm_earlier
    ON mm_earlier.id_medal = mm_later.id_medal
   AND mm_earlier.id_implied_medal = mm_later.id_implied_medal
   AND mm_earlier.id < mm_later.id;

DELETE fm_later
FROM function_medal AS fm_later
INNER JOIN function_medal AS fm_earlier
    ON fm_earlier.id_function = fm_later.id_function
   AND fm_earlier.id_medal = fm_later.id_medal
   AND fm_earlier.id < fm_later.id;

DELETE am_later
FROM activity_medal AS am_later
INNER JOIN activity_medal AS am_earlier
    ON am_earlier.id_activity = am_later.id_activity
   AND am_earlier.id_medal = am_later.id_medal
   AND am_earlier.role = am_later.role
   AND am_earlier.money = am_later.money
   AND am_earlier.local = am_later.local
   AND am_earlier.id < am_later.id;

DELETE medal
FROM medal
INNER JOIN medal_map ON medal_map.duplicate_id = medal.id;

COMMIT;

-- database.sql contient déjà cette contrainte pour les nouvelles bases. Cette
-- migration l'ajoute aux bases existantes seulement si elle n'y est pas encore.
SET @medal_codename_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'medal'
      AND index_name = 'medal_codename'
      AND non_unique = 0
);
SET @medal_codename_index_sql = IF(
    @medal_codename_index_exists = 0,
    'ALTER TABLE medal ADD UNIQUE KEY medal_codename (`codename`)',
    'SELECT 1'
);
PREPARE medal_codename_index_stmt FROM @medal_codename_index_sql;
EXECUTE medal_codename_index_stmt;
DEALLOCATE PREPARE medal_codename_index_stmt;

-- Contrôles finaux : aucune ligne au premier SELECT et zéro au second.
SELECT codename, COUNT(*) AS definitions
FROM medal
GROUP BY codename
HAVING COUNT(*) > 1;

SELECT COUNT(*) AS remaining_albedo_user_medals
FROM user_medal
INNER JOIN medal ON medal.id = user_medal.id_medal
WHERE LEFT(medal.codename, 7) = 'albedo_';
