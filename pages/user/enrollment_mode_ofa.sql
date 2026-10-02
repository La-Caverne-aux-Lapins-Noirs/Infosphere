ALTER TABLE `user_cycle`
  MODIFY COLUMN `enrollment_mode` enum('school','of','ofa','cfa') DEFAULT NULL;
