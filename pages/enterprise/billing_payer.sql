-- Classification des organisations qui règlent des factures de scolarité.
-- Les OPCO sont exclus des relances par la logique applicative, même si une
-- ancienne ligne conserve billing_reminder_enabled = 1.
ALTER TABLE `organization`
  ADD COLUMN IF NOT EXISTS `billing_payer_kind` varchar(32) NOT NULL DEFAULT 'direct',
  ADD COLUMN IF NOT EXISTS `billing_reminder_enabled` tinyint(1) NOT NULL DEFAULT 1;
