-- Facturation électronique : configuration du connecteur portée directement par school.
ALTER TABLE `school`
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_key` varchar(64) NOT NULL DEFAULT '' AFTER `diploma_secret`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `electronic_invoice_connector_key`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_environment` varchar(16) NOT NULL DEFAULT 'test' AFTER `electronic_invoice_connector_enabled`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_endpoint` varchar(512) NOT NULL DEFAULT '' AFTER `electronic_invoice_connector_environment`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_configuration_json` longtext DEFAULT NULL AFTER `electronic_invoice_connector_endpoint`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_actor` int(11) DEFAULT NULL AFTER `electronic_invoice_connector_configuration_json`,
  ADD COLUMN IF NOT EXISTS `electronic_invoice_connector_updated_at` datetime DEFAULT NULL AFTER `electronic_invoice_connector_actor`;

-- Compatibilité : si l'ancienne migration v3 a déjà été exécutée,
-- reprendre sa configuration avant de supprimer la table 1:1 devenue inutile.
SET @billing_einvoice_old_connector_table := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'billing_electronic_connector_config'
);

SET @billing_einvoice_migrate_sql := IF(
  @billing_einvoice_old_connector_table > 0,
  'UPDATE `school` s INNER JOIN `billing_electronic_connector_config` c ON c.id_school = s.id SET s.electronic_invoice_connector_key = c.connector_key, s.electronic_invoice_connector_enabled = c.enabled, s.electronic_invoice_connector_environment = c.environment, s.electronic_invoice_connector_endpoint = c.endpoint, s.electronic_invoice_connector_configuration_json = c.configuration_json, s.electronic_invoice_connector_actor = c.id_actor, s.electronic_invoice_connector_updated_at = c.updated_at',
  'SELECT 1'
);

PREPARE billing_einvoice_migrate_stmt FROM @billing_einvoice_migrate_sql;
EXECUTE billing_einvoice_migrate_stmt;
DEALLOCATE PREPARE billing_einvoice_migrate_stmt;

DROP TABLE IF EXISTS `billing_electronic_connector_config`;
