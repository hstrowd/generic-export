<?php

// Include the plugin constants.
require_once( plugin_dir_path( __FILE__ ) . 'constants.php' );

class GenericExporter {

  /**
   *  BEGIN: Static Content
   */

  /**
   *  Dictionary of supported content types for export.
   *  The keys in this dictionary should be the internal IDs used for these content types.
   *  The values in this dictionary should be an arry in which the first element is the user
   *  facing name for each content type and the second is the class name used to export this
   *  content.
   *
   *  For each key, a file should exist in the exporters directory that is the key appended 
   *  with '-exporter.php'.
   */
  public static $supported_content_types = 
    array( 'visual-form-builder' => array('Visual Form Builder', 'VisualFormBuilderExporter') );

  // The directory into which backups of executed exports will be saved. I wanted to make this
  // a constant but since it is a calculated value, I couldn't get it to work.
  public static function backup_dir() {
    return GENERIC_EXPORT_DIR . '/export_backups';
  }

  /**
   *  END: Static Content
   */


  public function __construct() {

    // Loads supported exporters.
    foreach(self::$supported_content_types as $content_type_key => $content_type_array) {
      require_once( GENERIC_EXPORT_DIR . '/exporters/' . $content_type_key . '-exporter.php' );
    }

    // Ensure the export backup directory exists.
    if(!is_dir(self::backup_dir()) && !mkdir(self::backup_dir())) {
      $this->unable_to_create_backups_dir = true;
    }
  }


  /**
   *  BEGIN: Exporter Activation/Deactivation
   */

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

  /**
   *  END: Exporter Activation/Deactivation
   */


  /**
   *  BEGIN: Content Exporting
   */

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

  /**
   *  END: Content Exporting
   */


  /**
   *  BEGIN: Delete Old Backup Files
   */

  public function clean_backup_files($filenames) {
    // Track the files that were deleted and those that could not be found.
    $this->files_deleted = array();
    $this->files_not_found = array();

    // Check if each file exists. If so, delete it. If not, mark it as unknown.
    foreach($filenames as $filename) {
      $full_path = self::backup_dir() . '/' . $filename;
      if(file_exists($full_path)) {
	unlink($full_path);
	$this->files_deleted[] = $filename;
      } else
	$this->files_not_found[] = $filename;
    }
  }

  /**
   *  END: Delete Old Backupo Files
   */



  public function no_content_to_export_notice() {
    echo "<div class=\"error\">No content found to export.</div>";
    remove_action('admin_notices', array( &$this, 'no_content_to_export_notice' ));
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
