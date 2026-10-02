ALTER TABLE `user_cycle`
  ADD COLUMN IF NOT EXISTS `fr_name` varchar(255) DEFAULT NULL AFTER `enrollment_mode`,
  ADD COLUMN IF NOT EXISTS `en_name` varchar(255) DEFAULT NULL AFTER `fr_name`;
