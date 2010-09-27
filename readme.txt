=== Exports and Reports ===
Contributors: sc0ttkclark
Donate link: http://scottkclark.com/
Tags: exports, reports, reporting, exporting, csv, tab, xml, json
Requires at least: 3.0.1
Tested up to: 3.0.1
Stable tag: 0.3.2

THIS IS A BETA VERSION - Currently in development - Define custom exports / reports for users by creating each export / report and defining the fields as well as custom MySQL queries to run.

== Description ==

**THIS IS A BETA VERSION - Currently in development**

**OFFICIAL SUPPORT** - Exports and Reports - Support Forums: http://scottkclark.com/forums/exports-and-reports/

Define custom Exports / Reports for users by creating each export / report and defining the fields as well as custom MySQL queries to run.

All you do is install the plugin, create your Groups, create your Reports, and hand it off to your clients. Exportable reports in CSV, TSV, XML, JSON, and custom delimiter separated formats.

Coming soon... Pods CMS Framework integration

== Frequently Asked Questions ==

**What are Groups?**

Groups are groupings of Reports that are given their own menu item in the "Reports" menu.

**What is a Report?**

A Report is defined by a Custom MySQL query and can be configured to display however you wish using additional field definitions. Exports can be disabled per report.

== Changelog ==

= 0.3.2 =
* Bug fix for SQL

= 0.3.1 =
* Fixed menu access

= 0.3 =
* Upgraded Admin.class.php with Bug fixes and features (better UI and filtering)
* Export data fixes on CSV / TSV to support Excel
* Redefined Date Data Type into three (Date, Date + Time, Time)
* Filter by Date
* Ability to clear entire export directory (based on logged export files)
* Daily Export Cleanup via wp_cron

= 0.2 =
* First official release to the public as a plugin

== Upgrade Notice ==

= 0.3.2 =
* Bug fix for SQL

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP plugin page)
1. Activate this plugin

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name

== Official Support ==

Exports and Reports - Support Forums: http://scottkclark.com/forums/exports-and-reports/

== About the Plugin Author ==

Scott Kingsley Clark from SKC Development -- Scott specializes in WordPress and Pods CMS Framework development using PHP, MySQL, and AJAX. Scott is also a developer on the Pods CMS Framework plugin

== Features ==

= Administration =
* Create and Manage Groups
* Create and Manage Reports
* Limit which User Roles have access to a Group or Report
* Filter by Date
* Ability to clear entire export directory (based on logged export files)
* Daily Export Cleanup via wp_cron
* Admin.class.php - A class for plugins to manage data using the WordPress UI appearance

= Reporting =
* Automatic Pagination
* Show only the fields you want to show
* Pre-display modification through custom defined function per field or row

= Exporting =
* CSV - Comma-separated Values (w/ Excel support)
* TSV - Tab-separated Values (w/ Excel support)
* XML - XML 1.0 UTF-8 data
* JSON - JSON for use in Javascript and PHP5+
* Custom - Custom delimiter separated Values

== Roadmap ==

= 0.4 =
* Pods CMS Framework integration