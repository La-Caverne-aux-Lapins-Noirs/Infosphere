-- Provenance des sessions générées.
-- Une session manuelle conserve les trois colonnes à NULL. Une session issue
-- d'un moteur/document possède les trois valeurs. La clé UNIQUE empêche qu'une
-- même occurrence logique soit matérialisée deux fois lors d'une resynchronisation.

ALTER TABLE `session`
    ADD COLUMN IF NOT EXISTS `source_type` varchar(32) DEFAULT NULL
        COMMENT 'Origine génératrice de la session (NULL = manuelle)' AFTER `name`,
    ADD COLUMN IF NOT EXISTS `source_id` varchar(96) DEFAULT NULL
        COMMENT 'Identifiant stable de la source, par exemple une instance documentaire' AFTER `source_type`,
    ADD COLUMN IF NOT EXISTS `source_key` varchar(96) DEFAULT NULL
        COMMENT 'Identifiant stable de cette occurrence dans la source' AFTER `source_id`;

ALTER TABLE `session`
    ADD INDEX IF NOT EXISTS `source_type` (`source_type`),
    ADD INDEX IF NOT EXISTS `source_id` (`source_id`),
    ADD UNIQUE INDEX IF NOT EXISTS `session_source_occurrence` (`source_type`, `source_id`, `source_key`);
