-- Informations bancaires / modalités de paiement de la personne morale.
-- Le contexte documentaire School expose aussi cette valeur sous School.RIB
-- afin de conserver la compatibilité avec res/docs/fr/facture.dab.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `billing_information` text NOT NULL DEFAULT '';

-- Un même contact peut assurer plusieurs usages documentaires, par exemple
-- être à la fois représentant de l'organisation et tuteur.
ALTER TABLE `organization_user`
  MODIFY COLUMN `document_role` varchar(96) NOT NULL DEFAULT 'contact';

-- Capital social affiché dans les mentions légales des documents.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `share_capital` varchar(64) NOT NULL DEFAULT '';


-- Identifiants nécessaires à la facturation électronique et au routage.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `vat_number` varchar(64) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_address` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_address_scheme` varchar(32) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_routing_code` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_routing_scheme` varchar(32) NOT NULL DEFAULT '';

-- Nature du payeur lorsqu'une organisation finance une scolarité. Cette
-- information est volontairement distincte de organization.type : une société,
-- un OPCO et un financeur institutionnel restent tous des organisations, mais
-- n'obéissent pas aux mêmes règles de relance.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `billing_payer_kind` varchar(32) NOT NULL DEFAULT 'direct',
  ADD COLUMN IF NOT EXISTS `billing_reminder_enabled` tinyint(1) NOT NULL DEFAULT 1;
