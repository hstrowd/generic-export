<?php

class GenericExporter {
  /*  Dictionary of supported content types for export.
      The keys in this dictionary should be the internal IDs used for these content types.
      The values in this dictionary should be an arry in which the first element is the user
      facing name for each content type and the second is the class name used to export this
      content.
  
      For each key, a file should exist in the exporters directory that is the key appended 
      with '-exporter.php'.
   */
  // TODO: Dynamically load this based on the content in the exporters directory.
  public static $supported_content_types = 
    array( 'visual-form-builder' => array('Visual Form Builder', 'VisualFormBuilderExporter') );

  // The directory into which backups of executed exports will be saved. I wanted to make this
  // a constant but since it is a calculated value, I couldn't get it to work.
  public static function backup_dir() {
    return plugin_dir_path( __FILE__ ) . 'export_backups';
  }

  /* Required WordPress Hooks -- BEGIN */

  public function __construct() {

    // Loads supported exporters.
    foreach(self::$supported_content_types as $content_type_key => $content_type_array) {
      require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . '/exporters/' . 
		    $content_type_key . '-exporter.php' );
    }

    // Ensure the export backup directory exists.
    if(!is_dir(self::backup_dir()) && !mkdir(self::backup_dir())) {
      // Set notice to notify the user that the backups directory could not be created.
      add_action('admin_notices', array( &$this, 'unable_to_create_backups_dir' ));
    }
  }

  // Handles user action for activating a content type.
  public function activate_content_type($content_type) {
    $exporter_class = self::$supported_content_types[$content_type][1];
    $exporter = new $exporter_class();

    /* Update the plugin option. */
    $activated_types = get_option('generic-export-active-content-types');
    if(!in_array($content_type, $activated_types)) {
      echo "adding content type";
      array_push($activated_types, $content_type);
      update_option('generic-export-active-content-types', $activated_types);
    }

    /* Add an exported column to the appropriate table. */
    global $wpdb;
    $content_table_name = $exporter->content_table_name();
    $wpdb->query("ALTER TABLE " . $content_table_name . 
		 " ADD COLUMN exported BOOLEAN NOT NULL DEFAULT FALSE;");
  }

  // Handles user action for deactivating a content type.
  public function deactivate_content_type($content_type) {
    $exporter_class = self::$supported_content_types[$content_type][1];
    $exporter = new $exporter_class();

    /* Update the plugin options. */
    $activated_types = get_option('generic-export-active-content-types');
    if(in_array($content_type, $activated_types)) {
      $keys = array_keys($activated_types, $content_type);
      foreach($keys as $key) {
	unset($activated_types[$key]);
      }
      update_option('generic-export-active-content-types', $activated_types);
    }

    $content_table_name = $exporter->content_table_name();

    /* Drop exported column from the appropriate table */
    global $wpdb;
    $wpdb->query("ALTER TABLE " . $content_table_name . " DROP COLUMN exported;");
  }

  // Handles user action for exporting content.
  // TODO: Add default arguments.
  public function export_content($content_type, $content_to_export, $mark_as_exported, $backup_output) {
    $activated_types = get_option('generic-export-active-content-types');
    if(!in_array($content_type, $activated_types)) {
      add_action('admin_notices', array( &$this, 'content_type_not_activated' ));      
      return;
    }

    $exporter_class = self::$supported_content_types[$content_type][1];
    $exporter = new $exporter_class();

    switch($content_to_export) {
    case "non-exported":
      $entry_ids = $exporter->get_unexported_entries();
      break;
    case "all":
      $entry_ids = $exporter->get_all_entries();
      break;
    default:
      $entry_ids = array();
      break;
    }

    if(count($entry_ids) > 0) {
      $output = $exporter->export_entries($entry_ids);
      $filename = date("Y-m-d_H.i.s") . '-' . $content_to_export . '-' . $content_type . '.csv';

      if($backup_output) {
	// Write output to a backup file on the server.
	if($file = fopen(self::backup_dir() . '/' . $filename, 'w')) {
	  fwrite($file, $output);
	  fclose($file);
	} else {
	  // If we are unable to backup the content, the user should be notified, there
          // should be no lasting impact of this action, and the content should not be
          // delivered to the user.
	  // Set notice to notify the user that the backups directory could not be created.
	  add_action('admin_notices', array( &$this, 'unable_to_create_backup' ));
	  return;
	}
      }

      // Only update entries, if told to do so.
      if($mark_as_exported) {
        $exporter->mark_entries_exported($entry_ids);
      }

      return array($filename, $output);
    } else {
      add_action('admin_notices', array( &$this, 'no_content_to_export_notice' ));
    }
  }

  public function no_content_to_export_notice() {
    echo "<div class=\"error\">No content found to export.</div>";
    remove_action('admin_notices', array( &$this, 'no_content_to_export_notice' ));
  }

  public function unable_to_create_backups_dir() {
    echo "<div class=\"warning\">Unable to create the export backup directory in the " . self::backup_dir() . " directory. Please make sure that the web server has write access to this directory.</div>";
    remove_action('admin_notices', array( &$this, 'unable_to_create_backups_dir' ));
  }

  public function content_type_not_activated() {
    echo "<div class=\"error\">The requested content type to export has not be activated. Please activate the appropriate content type and try again.</div>";
    remove_action('admin_notices', array( &$this, 'content_type_not_activated' ));
  }

  public function unable_to_create_backup() {
    echo "<div class=\"error\">Unable to create a backup of the content exported, as requested. Please make sure that the web server has write access to the " . self::backup_dir() . " directory.</div>";
    remove_action('admin_notices', array( &$this, 'unable_to_create_backup' ));
  }
}

?>
