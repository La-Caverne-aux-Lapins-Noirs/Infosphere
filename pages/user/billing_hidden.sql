ALTER TABLE `user`
  ADD `billing_hidden` tinyint(1) NOT NULL DEFAULT 0 AFTER `money`;
