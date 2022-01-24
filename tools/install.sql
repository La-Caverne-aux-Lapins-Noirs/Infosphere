
CREATE TABLE `log` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `log_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type` int(11) NOT NULL,
  `message` varchar(255) COLLATE utf8_bin NOT NULL,
  `ip` varchar(255) COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `configuration` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(64) COLLATE utf8_bin NOT NULL,
  `value` varchar(256) COLLATE utf8_bin DEFAULT ""
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- Il faudrait que le password du mail soit chiffre avec comme clef le mot de passe d'Albedo
-- mais j'ai pas le temps bordel de merde.
INSERT INTO `configuration` (`id`, `codename`, `value`) VALUES
(1, 'subscription_possible', 0),
(2, 'self_signing', 1),
(3, 'mail_login', "infosphere@ecole-89.com"),
(4, 'mail_password', "OzZ9+2Md4uJ2vslZPeBN1A==")
;
