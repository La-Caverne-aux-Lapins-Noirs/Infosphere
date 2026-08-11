ALTER TABLE `billing_template`
  ADD COLUMN IF NOT EXISTS `invoice_type` varchar(32) NOT NULL DEFAULT 'school' AFTER `name`,
  ADD KEY IF NOT EXISTS `invoice_type` (`invoice_type`);

ALTER TABLE `billing_entry`
  ADD COLUMN IF NOT EXISTS `invoice_type` varchar(32) NOT NULL DEFAULT 'school' AFTER `entry_type`,
  ADD KEY IF NOT EXISTS `invoice_type` (`invoice_type`);
