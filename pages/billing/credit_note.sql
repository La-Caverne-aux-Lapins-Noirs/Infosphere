ALTER TABLE `billing_entry`
  ADD `related_entry_id` int(11) DEFAULT NULL AFTER `entry_type`,
  ADD KEY `related_entry_id` (`related_entry_id`);
