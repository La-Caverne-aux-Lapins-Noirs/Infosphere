-- Facturation électronique : identifiants d’organisation et destinataire B2B explicite.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `vat_number` varchar(64) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_address` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_address_scheme` varchar(32) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_routing_code` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `electronic_invoice_routing_scheme` varchar(32) NOT NULL DEFAULT '';

ALTER TABLE `billing_entry`
  ADD COLUMN IF NOT EXISTS `id_organization` int(11) DEFAULT NULL AFTER `id_user`,
  ADD KEY IF NOT EXISTS `id_organization` (`id_organization`),
  ADD COLUMN IF NOT EXISTS `buyer_routing_code` varchar(255) NOT NULL DEFAULT '' AFTER `id_organization`;
