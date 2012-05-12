<?php

// Include the plugin constants.
require_once( plugin_dir_path( __FILE__ ) . 'constants.php' );

// Defines the interface required for all supported content type exporters.
interface iGenericExporter {
  // Return: Array with the first element being the result ('success' or 'activiation_failed'). 
  //         If it failed, the second element should be an array of error messages. 
  public function activate();

  // Return: Array with the first element being the result ('success' or 'deactiviation_failed'). 
  //         If it failed, the second element should be an array of error messages. 
  public function deactivate();

  // Return: Array of entry IDs.
  public function get_unexported_entries();

  // Return: Array of entry IDs.
  public function get_all_entries();

  // Return: String of CSV entries.
  public function export_entries(Array $entry_ids = NULL);
  public function mark_entries_exported(Array $entry_ids = NULL);
}

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
   *  BEGIN: Content Exporting
   */

  // Handles user action for exporting content.
  public function export_content($content_type, $content_to_export = 'non-exported', 
				 $mark_as_exported = FALSE, $backup_output = TRUE) {
    $exporter_config = self::$supported_content_types[$content_type];
    if(!isset($exporter_config))
      return array('content_type_not_found');

    $exporter_class = $exporter_config[1];
    if(!isset($exporter_class))
      return array('content_type_not_supported');

    $exporter = new $exporter_class();

    $activated_types = get_option('generic-export-active-content-types');
    if(!in_array($content_type, $activated_types))
      return array('content_type_not_activated');

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
	  return array('unable_to_create_backup');
	}
      }

      // Only update entries, if told to do so.
      if($mark_as_exported)
        $exporter->mark_entries_exported($entry_ids);

      return array('success', $filename, $output);
    } else {
      return array('no_content_to_export');
    }
  }

  /**
   *  END: Content Exporting
   */


  /**
   *  BEGIN: Exporter Activation/Deactivation
   */

  // Handles user action for activating a content type. Returns true if successful, and false othewise.
  public function activate_content_type($content_type) {
    $exporter_config = self::$supported_content_types[$content_type];
    if(!isset($exporter_config))
      return array('activation_failed', array('The specified content type (' . $content_type . ') was not found.'));

    $exporter_class = $exporter_config[1];
    if(!isset($exporter_class))
      return array('activation_failed', array('The specified content type (' . $content_type . ') was not found.'));

    $exporter = new $exporter_class();

    $activation_result = $exporter->activate();

    switch($activation_result[0]) {
    case 'success':
      // Update the plugin option.
      $activated_types = get_option('generic-export-active-content-types');
      if(!in_array($content_type, $activated_types)) {
	array_push($activated_types, $content_type);
	update_option('generic-export-active-content-types', $activated_types);
      }
      break;
    }

    return $activation_result;
  }

  // Handles user action for deactivating a content type.
  public function deactivate_content_type($content_type) {
    $exporter_config = self::$supported_content_types[$content_type];
    if(!isset($exporter_config))
      return array('deactivation_failed', array('The specified content type (' . $content_type . ') was not found.'));

    $exporter_class = $exporter_config[1];
    if(!isset($exporter_class))
      return array('deactivation_failed', array('The specified content type (' . $content_type . ') was not found.'));

    $exporter = new $exporter_class();

    $deactivation_result = $exporter->deactivate();

    switch($deactivation_result[0]) {
    case 'success':
      // Update the plugin options.
      $activated_types = get_option('generic-export-active-content-types');
      if(in_array($content_type, $activated_types)) {
	$keys = array_keys($activated_types, $content_type);
	foreach($keys as $key) {
	  unset($activated_types[$key]);
	}
	update_option('generic-export-active-content-types', $activated_types);
      }
      break;
    }

    return $deactivation_result;
  }

  /**
   *  END: Exporter Activation/Deactivation
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
}

?>
