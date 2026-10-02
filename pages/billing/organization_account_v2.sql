ALTER TABLE `organization_account_entry`
  ADD COLUMN IF NOT EXISTS `document_name` varchar(255) DEFAULT NULL AFTER `comment`,
  ADD COLUMN IF NOT EXISTS `document_path` varchar(512) DEFAULT NULL AFTER `document_name`,
  ADD COLUMN IF NOT EXISTS `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;
