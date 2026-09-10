-- Informations bancaires / modalités de paiement de la personne morale.
-- Le contexte documentaire School expose aussi cette valeur sous School.RIB
-- afin de conserver la compatibilité avec res/docs/fr/facture.dab.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `billing_information` text NOT NULL DEFAULT '';

-- Un même contact peut assurer plusieurs usages documentaires, par exemple
-- être à la fois représentant de l'organisation et tuteur.
ALTER TABLE `organization_user`
  MODIFY COLUMN `document_role` varchar(96) NOT NULL DEFAULT 'contact';
