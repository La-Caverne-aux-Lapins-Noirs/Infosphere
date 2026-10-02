-- Infosphère : séparation personne morale / établissement dans les documents.

ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `share_capital` varchar(64) NOT NULL DEFAULT '';

ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `document_information` text NOT NULL DEFAULT '' AFTER `alternation_info`,
  ADD COLUMN IF NOT EXISTS `vat_exemption_mention` varchar(512) NOT NULL DEFAULT 'TVA exonérée — article 261-4-4°-a du CGI.' AFTER `document_information`;
