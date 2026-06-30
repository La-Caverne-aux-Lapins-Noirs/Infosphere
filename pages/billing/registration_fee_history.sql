-- Facturation : type de ligne + frais d'inscription appliqués une seule fois par élève.
-- Les montants sont exprimés en centimes d'euros.

ALTER TABLE billing_entry
  ADD COLUMN entry_type varchar(32) NOT NULL DEFAULT 'tuition' AFTER amount,
  ADD KEY `entry_type` (`entry_type`);

UPDATE billing_entry
SET entry_type = 'registration_fee'
WHERE deleted IS NULL
  AND (
    LOWER(label) LIKE '%frais d%inscription%'
    OR LOWER(label) LIKE '%registration%'
  );

UPDATE billing_template
LEFT JOIN school ON school.id = billing_template.id_school
SET billing_template.registration_fee = 99000
WHERE billing_template.deleted IS NULL
  AND school.deleted IS NULL
  AND billing_template.registration_fee = 0
  AND (
    school.codename = 'EFRITS'
    OR school.fr_name LIKE '%EFRITS%'
    OR school.en_name LIKE '%EFRITS%'
    OR school.legal_name LIKE '%EFRITS%'
  );
