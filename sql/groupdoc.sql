CREATE TABLE IF NOT EXISTS `groupdoc` (
  `groupdoc_id` bigint(20) NOT NULL auto_increment,
  `cache_revision` bigint(20) NOT NULL,
  `cache_data` text collate utf8_bin NOT NULL,
  `created_dt` datetime NOT NULL,
  `modified_dt` datetime NOT NULL,
  PRIMARY KEY  (`groupdoc_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
