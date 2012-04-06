<?php
/*
Plugin Name: Generic Exporter
Plugin URI: TODO
Description: Provides an interface for exporting a variety of data from your WordPress site.
Version: 0.01
Author: Harrison Strowd
Author URI: http://www.hstrowd.com/
License: GPL2
*/

/*  Copyright 2012  Harrison Strowd  (email : h.strowd@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* TODO List:
    - Add documentation for how to add new exporters
    - Add support for multiple export formats
    - Add support for exporting without marking content as exported
    - Add support for exporting all content or just the non-exported content
    - Add support for storing copies of all exported content on the server and making them available for download (include configuration values for how often this should be cleaned).
    - Add support for verifying that a plugin is installed before it is deemed a "supported" content type.
*/

$generic_exporter = new GenericExporter();

// Handlers for user actions
if($_GET['action']); {
  $content_type = $_GET['content-type'];
  switch ($_GET['action']) {
  case 'activate-content-type': 
    $generic_exporter->activate_content_type($content_type); 
    break;
  case 'deactivate-content-type': 
    $generic_exporter->deactivate_content_type($content_type); 
    break;
    //TODO: Add support for different types of exports (e.g. all, without update, etc.).
  case 'export-content': 
    $generic_exporter->export_content($content_type); 
    break;
  }
}

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

  /* Required WordPress Hooks -- BEGIN */

  public function __construct() {
    if ( is_admin() ){
      //Actions
      add_action( 'admin_menu', array( &$this, 'generic_exporter_menu' ) );
      add_action( 'admin_init', array( &$this, 'register_generic_exporter_settings' ) );
      add_action( 'admin_head', array( &$this, 'generic_exporter_styles' ) );
    } else {
      // non-admin enqueues, actions, and filters
    }

    // Loads supported exporters.
    foreach(self::$supported_content_types as $content_type_key => $content_type_array) {
      require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . '/exporters/' . 
		    $content_type_key . '-exporter.php' );
    }

  }

  public function generic_exporter_menu() {
    add_options_page( __('Generic Exporter Options', 'generic exporter'), 
		      __('Generic Exporter', 'generic exporter'),
		      'manage_options', 
		      'generic-exporter',
		      array( &$this, 'generic_exporter_options' ),
		      '',
		      '' );
  }

  public function register_generic_exporter_settings() {
    add_option( 'generic-exporter-active-content-types', array() );
  }

  /* Required WordPress Hooks -- END */

  // Defines the content for the admin page.
  public function generic_exporter_options() {
    if ( !current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $activated_types = get_option('generic-exporter-active-content-types');

    // TODO: This is really ugly! Find another way of producing this content.
    ?>

    <div class="generic_exporter_admin">
    <div id="icon-options-general" class="icon32"><br></div>
    <h2>Generic Exporter - Data Export</h2>
  
    <?php
      if(count($activated_types) > 0) { 
     ?>
    <div class="export">
       <h3>Data Export</h3>
       <p>Select the content type you would like to export:</p>
       <?php
         foreach($activated_types as $content_type_key) {
        ?>
       <div class="content_type">
         <div class="name"><?php echo self::$supported_content_types[$content_type_key][0]; ?></div>
         <div class="export_link">
           <a href="?page=generic-exporter&action=export-content&content-type=<?php echo $content_type_key; ?>" class="button">Export</a>
         </div>
         <div class="clear"></div>
       </div>
       <?php
         }
        ?>
    </div>
    <?php
      }
     ?>

    <div class="configuration">
      <h3>Plugin Configuration</h3>
      <p>Use the options below to configure the content types that you would like to be exportable:</p>
      <div class="warning">WARNING: Deactivating a content type will delete the data stored in the database that tracks the content that has already been exported. This will mean that if you reactivate the plugin in the future, your first export will include all records and not just those that were not previously exported. Proceed with caution!</div>
      <?php
        foreach(self::$supported_content_types as $content_type_key => $content_type_array) {
       ?>
      <div class="content_type">
        <div class="name"><?php echo $content_type_array[0]; ?></div>
        <?php 
          if(!in_array($content_type_key, $activated_types)) { 
         ?>
        <div class="activation_link">
          <a href="?page=generic-exporter&action=activate-content-type&content-type=<?php echo $content_type_key; ?>" class="button">Activate</a>
        </div>
        <?php
          } else {
         ?>
        <div class="deactivation_link">
          <a href="?page=generic-exporter&action=deactivate-content-type&content-type=<?php echo $content_type_key; ?>" class="button">Deactivate</a>
        </div>
        <?php
      	   }
         ?>
         <div class="clear"></div>
      </div>
      <?php
        }
       ?>
    </div>
  
    <?php
  }

  /* Basic CSS styling for the admin page. */
  function generic_exporter_styles() {
    // TODO: This is ugly! find a better way to isolate this and pull it into the admin page.
    ?>
    <style type="text/css">
      .generic_exporter_admin h3 {
        margin-top: 35px;
      }
      .error, .warning {
        max-width: 600px;
        margin: 10px 0 20px 0;
        padding: 6px 10px;
        border: 1px solid #D8D8D8;
        -webkit-border-radius: 4px
        -moz-border-radius: 4px;
        border-radius: 4px;
        font-size: 11px;
      }
      .error {
        border-color: #EED3D7;
        background-color: #F2DEDE;
      }
      .warning {
        background-color: #FFFFCC;
      }
      .content_type {
        margin: 10px;
      }
      .content_type .name {
        margin-right: 10px;
        float: left;
        font-weight: bold;
      }
      .export_link, .activation_link, .deactivation_link {
        float: left;
      }
      .clear {
        clear:both;
      }
    </style>
    <?php
  }

  // Handles user action for activating a content type.
  public function activate_content_type($content_type) {
    $exporter_class = self::$supported_content_types[$content_type][1];
    $exporter = new $exporter_class();

    /* Update the plugin option. */
    $activated_types = get_option('generic-exporter-active-content-types');
    if(!in_array($content_type, $activated_types)) {
      array_push($activated_types, $content_type);
      update_option('generic-exporter-active-content-types', $activated_types);
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
    $activated_types = get_option('generic-exporter-active-content-types');
    if(in_array($content_type, $activated_types)) {
      $keys = array_keys($activated_types, $content_type);
      foreach($keys as $key) {
	unset($activated_types[$key]);
      }
      update_option('generic-exporter-active-content-types', $activated_types);
    }

    $content_table_name = $exporter->content_table_name();

    /* Drop exported column from the appropriate table */
    global $wpdb;
    $wpdb->query("ALTER TABLE " . $content_table_name . " DROP COLUMN exported;");
  }

  // Handles user action for exporting content.
  public function export_content($content_type) {
    $exporter_class = self::$supported_content_types[$content_type][1];
    $exporter = new $exporter_class();
    $entry_ids = $exporter->get_unexported_entries();

    if(count($entry_ids) > 0) {
      $output = $exporter->export_entries($entry_ids);
      $exporter->mark_entries_exported($entry_ids);

      /* Change our header so the browser spits out a CSV file to download */
      header('Content-type: text/csv');
      header('Content-Disposition: attachment; filename="' . date( 'Y-m-d' ) . '-entries.csv"');
      ob_clean();

      echo $output;

      die();
    } else {
      add_action('admin_notices', array( &$this, 'no_content_to_export_notice' ));
    }
  }

  public function no_content_to_export_notice() {
    echo "<div class=\"error\">No content found to export.</div>";
    remove_action('admin_notices', array( &$this, 'no_content_to_export_notice' ));
  }
}

?>
