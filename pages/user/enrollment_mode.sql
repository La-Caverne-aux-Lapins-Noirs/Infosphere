ALTER TABLE `user_cycle`
  ADD COLUMN IF NOT EXISTS `enrollment_mode` enum('school','of','ofa','cfa') DEFAULT NULL AFTER `id_cycle`;

ALTER TABLE `user_cycle`
  MODIFY COLUMN `enrollment_mode` enum('school','of','ofa','cfa') DEFAULT NULL;
