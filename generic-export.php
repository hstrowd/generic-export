<?php
/*
Plugin Name: Generic Export
Plugin URI: https://github.com/hstrowd/generic-export
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
    - Add support for cleaning out the backed up exports. 
    - Add global configuration for how long the backups should be kept for and the directory on the server to use.
    - Add support for verifying that a plugin is installed before it is deemed a "supported" content type.
    - Give the plugin a top level menu item and icon.
    - Add written explanation of each config value.
    - Make backup a requirement of marking as exported.
    - Add different levels of permissions for activating and exporting.
    - Allow per form export.
*/

if(false) {
  echo "<br/><br/>";
}

if ( is_admin() ){
  //Actions
  add_action( 'admin_menu', array( 'generic_exporter_menu' ) );
  add_action( 'admin_init', array( 'register_generic_exporter_settings' ) );
  add_action( 'admin_head', array( 'generic_exporter_styles' ) );
} else {
  // non-admin enqueues, actions, and filters
}


function generic_exporter_menu() {
  add_options_page( __('Generic Exporter Options', 'generic exporter'), 
		    __('Generic Exporter', 'generic exporter'),
		    'manage_options', 
		    'generic-exporter',
		    array( &$this, 'generic_exporter_options' ),
		    '',
		    '' );
}

function register_generic_exporter_settings() {
  add_option( 'generic-exporter-active-content-types', array() );
}

/* Required WordPress Hooks -- END */

// Defines the content for the admin page.
function generic_exporter_options() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

  $activated_types = get_option('generic-exporter-active-content-types');

  // Build a list of all backup files.
  $export_backups = array();
  if($backup_handle = opendir($this->backup_dir)) {
    while (false !== ($backup_filename = readdir($backup_handle))) {
	// Don't include directories.
      if(!is_dir($this->backup_dir . '/' . $backup_filename)) {
	  $export_backups[] = $backup_filename;
	}
    }
  }

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
    <p>Select the appropriate export options:</p>

    <form id="export-content" action method="post">
      <input name="action" type="hidden" value="export-content">
      <input type="hidden" name="_wp_http_referer" 
        value="<?php echo admin_url('options-general.php?page=generic-exporter'); ?>">

      <div id="content_type" class="export_option">
        <div class="label">Type of Content: </div>
        <div class="content_type_selection">
          <select size="1" name="content-type">
            <?php
              foreach($activated_types as $content_type_key) {
             ?>
              <option value="<?php echo $content_type_key; ?>">
                <?php echo self::$supported_content_types[$content_type_key][0]; ?>
              </option>             
            <?php
              }
             ?>
          </select>
        </div>
        <div class="clear"></div>
      </div>

      <div id="content_to_export" class="export_option">
        <div class="label">Content to Export: </div>
        <div class="content_to_export_selection">
          <label for="export-non-exported">
            <input type="radio" name="content-to-export" value="non-exported" checked="true">
            <span>Records Not Previously Exported</span>
          </label>
          <label for="export-all">
            <input type="radio" name="content-to-export" value="all">
            <span>All Records</span>
          </label>
        </div>
      </div>

      <div id="mark_as_exported" class="export_option">
        <div class="label">Mark Content as Exported: </div>
        <div class="mark_as_exported_selection">
          <input type="checkbox" name="mark-as-exported" value="1" checked>
        </div>
      </div>

      <div id="backup_output" class="export_option">
        <div class="label">Store Backup of Result on the Server: </div>
        <div class="backup_output_selection">
          <input type="checkbox" name="backup-output" value="1" checked>
        </div>
      </div>

      <div>
        <input type="submit" value="Export" class="button" />
      </div>
    </form>

  </div>
  <?php
    }
   ?>

  <?php
    if(count($export_backups) > 0) { 
   ?>
  <div class="export">
    <h3>Export Backups</h3>
    <p>The following backup files are available for downloading:</p>
    <ul>
    <?php
	foreach($export_backups as $backup_filename) {
     ?>
      <li>
        <a href="<?php echo plugins_url( 'export_backups/' . $backup_filename , __FILE__ ); ?>"><?php echo $backup_filename; ?></a>
      </li>
    <?php
	}
     ?>
    </ul>
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
      <div class="label"><?php echo $content_type_array[0]; ?></div>
      <?php 
        if(!in_array($content_type_key, $activated_types)) { 
       ?>
      <div class="activation_button">
        <a href="?page=generic-exporter&action=activate-content-type&content-type=<?php echo $content_type_key; ?>" class="button">Activate</a>
      </div>
      <?php
        } else {
       ?>
      <div class="deactivation_button">
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

    .generic_exporter_admin h3 {
      margin-top: 35px;
    }
    .generic_exporter_admin .label {
      margin-right: 10px;
      float: left;
      vertical-align: middle;
      font-weight: bold;
    }

    .export_option {
      margin: 10px;
    }
    .export .button {
      margin: 5px 0 0 20px;
    }

    .activation_button, .deactivation_button {
      float: left;
    }

    .clear {
      clear:both;
    }
  </style>
  <?php
}

require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . '/generic-exporter.php' );
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
  }
}

if($_POST['action']); {
  switch($_POST['action']) {
  case 'export-content': 
    $content_type = $_POST['content-type'];
    $content_to_export = $_POST['content-to-export'];
    $mark_as_exported = $_POST['mark-as-exported'];
    $backup_output = $_POST['backup-output'];
    $output = $generic_exporter->export_content($content_type, $content_to_export, $mark_as_exported, $backup_output); 

    /* Change our header so the browser spits out a CSV file to download */
    header('Content-type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    ob_clean();

    echo $output;

    die();
    break;
  }
}

?>