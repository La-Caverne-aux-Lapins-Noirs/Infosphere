-- Grille tarifaire EFRITS pour la page de facturation.
-- Les montants sont exprimés en centimes d'euros.
-- Ce fichier repart volontairement d'une facturation vide : la page est récente
-- et il n'y a pas d'historique de facturation à préserver.

DELETE FROM billing_payment;
DELETE FROM billing_entry;
DROP TABLE IF EXISTS billing_template;

CREATE TABLE `billing_template` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  `id_school` int(11) NOT NULL,
  KEY `id_school` (`id_school`),
  `tariff_year` int(11) NOT NULL DEFAULT 0,
  KEY `tariff_year` (`tariff_year`),
  `name` varchar(255) NOT NULL,
  `amount_once` int(11) NOT NULL DEFAULT 0,
  `amount_twice` int(11) NOT NULL DEFAULT 0,
  `amount_four` int(11) NOT NULL DEFAULT 0,
  `amount_twelve` int(11) NOT NULL DEFAULT 0,
  `registration_fee` int(11) NOT NULL DEFAULT 0,
  `id_actor` int(11) DEFAULT NULL,
  KEY `id_actor` (`id_actor`),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @efrits_school_id := (
  SELECT id
  FROM school
  WHERE deleted IS NULL
    AND (
      codename = 'EFRITS'
      OR fr_name LIKE '%EFRITS%'
      OR en_name LIKE '%EFRITS%'
      OR legal_name LIKE '%EFRITS%'
    )
  ORDER BY (codename = 'EFRITS') DESC, id ASC
  LIMIT 1
);

INSERT INTO billing_template
(id_school, tariff_year, name, registration_fee, amount_once, amount_twice, amount_four, amount_twelve)
SELECT @efrits_school_id, 1, 'Année 1 [EF1]', 99000, 743700, 373709, 187319, 62543
WHERE @efrits_school_id IS NOT NULL;

INSERT INTO billing_template
(id_school, tariff_year, name, registration_fee, amount_once, amount_twice, amount_four, amount_twelve)
SELECT @efrits_school_id, 2, 'Année 2 [EF2], [EF2X]', 0, 758540, 381166, 191057, 63791
WHERE @efrits_school_id IS NOT NULL;

INSERT INTO billing_template
(id_school, tariff_year, name, registration_fee, amount_once, amount_twice, amount_four, amount_twelve)
SELECT @efrits_school_id, 3, 'Année 3 [EF3], [EF3X]', 0, 797760, 400874, 200936, 67089
WHERE @efrits_school_id IS NOT NULL;

INSERT INTO billing_template
(id_school, tariff_year, name, registration_fee, amount_once, amount_twice, amount_four, amount_twelve)
SELECT @efrits_school_id, 4, 'Année 4 [EF4]', 0, 874080, 439225, 220159, 73508
WHERE @efrits_school_id IS NOT NULL;

INSERT INTO billing_template
(id_school, tariff_year, name, registration_fee, amount_once, amount_twice, amount_four, amount_twelve)
SELECT @efrits_school_id, 5, 'Année 5 [EF5]', 0, 874080, 439225, 220159, 73508
WHERE @efrits_school_id IS NOT NULL;
