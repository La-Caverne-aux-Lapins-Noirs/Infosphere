-- Facturation électronique : curseur de réception porté par school.
ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `electronic_invoice_receive_cursor` varchar(64) NOT NULL DEFAULT '' AFTER `electronic_invoice_connector_updated_at`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_receive_at` datetime DEFAULT NULL AFTER `electronic_invoice_receive_cursor`;

-- Un même flux distant ne doit pouvoir être intégré qu'une seule fois par école/connecteur.
ALTER TABLE `billing_electronic_document`
  ADD UNIQUE KEY IF NOT EXISTS `billing_electronic_document_remote` (`id_school`, `direction`, `provider_key`, `provider_document_id`);
