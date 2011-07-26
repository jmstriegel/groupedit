CREATE TABLE IF NOT EXISTS `docrevision` (
  `docrevision_id` bigint(20) NOT NULL auto_increment,
  `groupdoc_id` bigint(20) NOT NULL,
  `operation` enum('add','del') collate utf8_bin NOT NULL,
  `location` int(11) NOT NULL,
  `data` text collate utf8_bin NOT NULL,
  `revision_dt` datetime NOT NULL,
  PRIMARY KEY  (`docrevision_id`),
  KEY `groupdoc_id` (`groupdoc_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

