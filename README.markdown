Generic Exporter
==============
* Contributors: hstrowd
* Donate link: http://www.hstrowd.com/
* Tags: export
* Requires at least: 2.0.2
* Tested up to: 2.1
* Stable tag: 4.3

An extensible framework for exporting data from your WordPress site.

Description 
==============

This plugin is only in it's alpha testing stage and is very much still a work in progress. There is a long TODO list, but the idea is that it would allow a diverse range of WordPress content to be exported to a variety of destinations and/or formats.

The design is to allow a variety of "exporters" (located in the exporters directory), and "formatters" (to be located in the formatters directory) to be defined and plugged together at runtime by the user to allow them to export any of their content to any of the defined formats. The framework itself provides a way of exposing this functionality through the WordPress admin interface and tracking the content that has already been exported.

Installation
==============

1. Upload the `generic-exporter` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the plugin and export content through the Settings => Generic Exporter admin page.

Frequently Asked Questions
==============

None yet...

Screenshots
==============

None yet...

Changelog
==============

0.1
--------------
Initial version.
