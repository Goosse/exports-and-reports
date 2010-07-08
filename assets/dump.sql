DROP TABLE IF EXISTS `wp_exportsreports_groups`;
CREATE TABLE `wp_exportsreports_groups` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `disabled` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_exportsreports_log`;
CREATE TABLE `wp_exportsreports_log` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `report_id` int(10) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_exportsreports_reports`;
CREATE TABLE `wp_exportsreports_reports` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `group` int(10) NOT NULL,
  `sql_query` longtext NOT NULL,
  `field_data` longtext NOT NULL,
  `disable_export` int(1) NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

INSERT INTO `wp_exportsreports_groups` VALUES ('1', 'WordPress', '0', NOW(), NOW());
INSERT INTO `wp_exportsreports_reports` VALUES ('1', 'Posts', '1', 'SELECT ID,post_title,post_name,post_type,post_date FROM wp_posts WHERE post_type NOT IN (\'revision\',\'attachment\')', '[{\"name\":\"ID\",\"label\":\"Post ID\",\"custom_display\":\"\",\"type\":\"text\"},{\"name\":\"post_title\",\"label\":\"Post Title\",\"custom_display\":\"\",\"type\":\"text\"},{\"name\":\"post_name\",\"label\":\"Post\'s Slug\",\"custom_display\":\"\",\"type\":\"text\"},{\"name\":\"post_date\",\"label\":\"Post Date\",\"custom_display\":\"\",\"type\":\"date\"},{\"name\":\"post_type\",\"label\":\"Post Type\",\"custom_display\":\"\",\"type\":\"text\"}]', '0', NOW(), NOW());