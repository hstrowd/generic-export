Generic Export
==============
* Contributors: hstrowd
* Donate link: http://www.hstrowd.com/
* Tags: export

An extensible framework for exporting data from your WordPress site.


Description 
==============

This plugin is only in it's alpha testing stage and is very much still a work in progress. There is a long TODO list, but the idea is that it would allow a diverse range of WordPress content to be exported to a variety of destinations and/or formats.

The design is to allow a variety of "exporters" (located in the exporters directory), and "formatters" (to be located in the formatters directory) to be defined and plugged together at runtime by the user to allow them to export any of their content to any of the defined formats. The framework itself provides a way of exposing this functionality through the WordPress admin interface and tracking the content that has already been exported.


Installation
==============

1. Upload the `generic-export` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure the plugin and export content through the Settings => Generic Export admin page.


Frequently Asked Questions
==============

1. How do I add a new type of exporter?

All exporters are defined in the 'exporters/' directory and must conferm to the iGenericExporter interface. This interface requires the following methods to be implemented:

* activate(): Executes any necessary setup to support exporting content of this type. Typically this will include adding a column to the table containing the content to be exported, so that the content already exported can be tracked.
* deactivate(): Executes any necessary clean-up from supporting export of this type of content. Typically this will include removing the column that tracked the content already exported.
* get_unexported_entries(): Returns an array of entry ids that have not yet been exported.
* get_all_entries(): Retruns an array of all entry ids.
* export_entries($entry_ids): Currently returns a string of CSVs. In the future, the plan is to have this return an array of the content and to define formatters that would allow this data to be presented in a variety of ways.
* mark_entries_exported($entry_ids): Updates the specified entries to be marked as having been exported.

This class should end with the suffix 'Exporter' and the filename must ent with '-exporter.php'. Once this class has been created, the following steps need to be taken:

* Add the class to the 'exporters/' directory.
* Add an entry to the static $supported_content_types array on the GenericExporter class. This entry should be keyed off of the exporter filename excluding the '-exporter.php' and the value should be a two element array where the first element is a name for this exporter to be displayed to the user and the second is the name of the exporter class.

After that everything else should be pickup up automatically.


Screenshots
==============

None yet...


Changelog
==============

0.1
--------------
Initial version.
