CREATE TABLE `class_gallery` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `codename` varchar(255) COLLATE utf8_bin NOT NULL,
  `fr_name` varchar(255) COLLATE utf8_bin DEFAULT "",
  `fr_description` text COLLATE utf8_bin DEFAULT "",
  `deleted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `class_gallery_asset` (
  `id` int(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_class_gallery` int(11) NOT NULL,
  `chapter` int(11) DEFAULT 0,
  `fr_name` varchar(255) COLLATE utf8_bin DEFAULT "",
  `fr_content` text COLLATE utf8_bin DEFAULT "",
  `deleted` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

