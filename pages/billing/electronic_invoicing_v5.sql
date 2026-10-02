-- Facturation électronique : robustesse transport, file de contrôle,
-- validation réglementaire optionnelle et transmission des encaissements.
ALTER TABLE `billing_electronic_document`
  ADD COLUMN IF NOT EXISTS `transport_attempts` int(11) NOT NULL DEFAULT 0 AFTER `transmission_id`,
  ADD COLUMN IF NOT EXISTS `last_transport_attempt_at` datetime DEFAULT NULL AFTER `transport_attempts`,
  ADD COLUMN IF NOT EXISTS `next_transport_retry_at` datetime DEFAULT NULL AFTER `last_transport_attempt_at`,
  ADD COLUMN IF NOT EXISTS `last_transport_error` varchar(255) DEFAULT NULL AFTER `next_transport_retry_at`,
  ADD COLUMN IF NOT EXISTS `review_note` text DEFAULT NULL AFTER `last_transport_error`,
  ADD COLUMN IF NOT EXISTS `review_closed_at` datetime DEFAULT NULL AFTER `review_note`,
  ADD COLUMN IF NOT EXISTS `regulatory_validation_status` varchar(32) DEFAULT NULL AFTER `review_closed_at`,
  ADD COLUMN IF NOT EXISTS `regulatory_validation_at` datetime DEFAULT NULL AFTER `regulatory_validation_status`,
  ADD COLUMN IF NOT EXISTS `regulatory_validation_details` longtext DEFAULT NULL AFTER `regulatory_validation_at`,
  ADD KEY IF NOT EXISTS `next_transport_retry_at` (`next_transport_retry_at`),
  ADD KEY IF NOT EXISTS `regulatory_validation_status` (`regulatory_validation_status`);

-- Propriété intrinsèque de l'école : pas de table 1:1 séparée.
ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `electronic_invoice_payment_reporting` tinyint(1) NOT NULL DEFAULT 0 AFTER `electronic_invoice_receive_at`;
