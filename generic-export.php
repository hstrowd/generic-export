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
    - Separate exporting and formatting.
    - Add global configuration for how long the backups should be kept for and the directory on the server to use.
    - Add different levels of permissions for activating and exporting.
*/

// Include the plugin constants.
require_once( plugin_dir_path( __FILE__ ) . 'constants.php' );

// Require internal files.
require_once( GENERIC_EXPORT_DIR . '/generic-exporter.php' );

/**
 *  BEGIN: Handle user actions.
 */

// Loads a GenericExporter and verifies that in was initialized properly.
function load_generic_exporter() {
  $exporter = new GenericExporter();

  // Notify the user that the backups directory could not be created, if necessary.
  if($exporter->unable_to_create_backups_dir)
      add_action('admin_notices', 'unable_to_create_backups_dir');

  return $exporter;
}

if($_POST['page'] == 'generic-export') {
  $generic_exporter = load_generic_exporter();

  // POST actions
  switch($_POST['action']) {
  case 'export-content': 
    $content_type = $_POST['content-type'];
    $content_to_export = $_POST['content-to-export'];
    $mark_as_exported = $_POST['mark-as-exported'];
    $backup_output = $_POST['backup-output'];
    $export_result = $generic_exporter->export_content($content_type, $content_to_export, $mark_as_exported, $backup_output); 
    switch($export_result[0]) {
    case 'success':
      $filename = $export_result[1];
      $output = $export_result[2];

      // Change our header so the browser spits out a CSV file to download.
      header('Content-type: text/csv');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      ob_clean();

      echo $output;

      die();
      break;
    default:
      // If the export did not succeed, notify the user of the result as a notice.
      add_action('admin_notices', $export_result[0]);      
      break;
    }
    break;
  case 'delete-backup-files':
    $generic_exporter->clean_backup_files($_POST['backup-files-to-delete']);

    // TODO: I don't like pushing the notice parameters through the session, but I 
    // don't know of any other way to do it.
    if(count($generic_exporter->files_deleted) > 0) {
      $_SESSION['generic_export_files_deleted'] = $generic_exporter->files_deleted;
      add_action('admin_notices', 'generic_export_files_deleted');
    }

    if(count($generic_exporter->files_not_found) > 0) {
      $_SESSION['generic_export_files_not_found'] = $generic_exporter->files_not_found;
      add_action('admin_notices', 'generic_export_files_not_found');
    }

    break;
  }
}

if($_GET['page'] == 'generic-export') {
  $generic_exporter = load_generic_exporter();

  // GET actions
  switch ($_GET['action']) {
  case 'activate-content-type': 
    $content_type = $_GET['content-type'];

    $activation_result = $generic_exporter->activate_content_type($content_type);

    switch($activation_result[0]) {
    case 'activation_failed':
      $_SESSION['activation_errors'] = $activation_result[1];
      add_action('admin_notices', 'activation_failed');
    }
    break;
  case 'deactivate-content-type': 
    $content_type = $_GET['content-type'];

    $deactivation_result = $generic_exporter->deactivate_content_type($content_type);

    switch($deactivation_result[0]) {
    case 'deactivation_failed':
      $_SESSION['deactivation_errors'] = $deactivation_result[1];
      add_action('admin_notices', 'deactivation_failed');
      break;
    }
    break;
  }
}

/**
 *  END: Handle user actions.
 */


/**
 *  BEGIN: Required WordPress Hooks
 */

if( is_admin() ) {
  //Actions
  add_action( 'admin_menu', 'generic_export_menu' );
  add_action( 'admin_init', 'register_generic_export_settings' );
  add_action( 'admin_head', 'generic_export_styles' );
} else {
  // non-admin enqueues, actions, and filters
}

function generic_export_menu() {
  add_management_page( __('Generic Export Options', 'generic export'), 
		    __('Generic Export', 'generic export'),
		    'manage_options', 
		    'generic-export',
		    'generic_export_options',
		    '',
		    '' );
}

function register_generic_export_settings() {
  add_option( 'generic-export-active-content-types', array() );
}

// Defines the content for the admin page.
function generic_export_options() {
  if ( !current_user_can( 'export' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

  $activated_types = get_option('generic-export-active-content-types');

  // Build a list of all backup files.
  $export_backups = array();
  if($backup_handle = opendir(GenericExporter::backup_dir())) {
    while (false !== ($backup_filename = readdir($backup_handle))) {
	// Don't include directories.
      if(!is_dir(GenericExporter::backup_dir() . '/' . $backup_filename)) {
	  $export_backups[] = $backup_filename;
	}
    }
  }

  // Defines the structure of the admin page.
  require_once(GENERIC_EXPORT_DIR . '/pages/admin.php');
}

// Basic CSS styling for the admin page.
function generic_export_styles() {
  wp_enqueue_style( 'generic_export_admin_css', GENERIC_EXPORT_URL .'/css/generic_export_admin.css');
}

function generic_export_scripts() {
  wp_enqueue_script( 'generic_export_admin_js', GENERIC_EXPORT_URL .'/js/generic_export_admin.js', 
		     array('jquery') );
}

/**
 *  END: Required WordPress Hooks
 */


/**
 *  BEGIN: Admin Notices
 */

function unable_to_create_backups_dir() {
  echo "<div class=\"warning notice\">Unable to create the export backup directory at '" . GenericExporter::backup_dir() . "'. Please make sure that the web server has write access to this directory.</div>";
  remove_action('admin_notices', 'unable_to_create_backups_dir');
}

function content_type_not_supported() {
  echo "<div class=\"error notice\">The requested content type to export ('" . $_POST['content-type'] . "') is not currently supported. Please contact your site administrator to resolve this issue..</div>";
  remove_action('admin_notices', 'content_type_not_supported');
}

function content_type_not_activated() {
  echo "<div class=\"error notice\">The requested content type to export has not be activated. Please activate the appropriate content type and try again.</div>";
  remove_action('admin_notices', 'content_type_not_activated');
}

function no_content_to_export() {
  echo "<div class=\"error notice\">No content found to export.</div>";
  remove_action('admin_notices', 'no_content_to_export');
}

function unable_to_create_backup() {
  echo "<div class=\"error notice\">Unable to create a backup of the content exported. Please make sure that the web server has write access to the " . GenericExporter::backup_dir() . " directory.</div>";
  remove_action('admin_notices', 'unable_to_create_backup');
}

function generic_export_files_deleted() {
  // Identify the files that were successfully deleted.
  $filenames = $_SESSION['generic_export_files_deleted'];
  unset($_SESSION['generic_export_files_deleted']);

  echo "<div class=\"success notice\">The following files were successfully deleted from the backup directory: <ul><li>" . join('</li><li>', $filenames) . "</li></ul></div>";
  remove_action('admin_notices', 'generic_export_files_deleted');
}

function generic_export_files_not_found() {
  // Identify the files that were not able to be found.
  $filenames = $_SESSION['generic_export_files_not_found'];
  unset($_SESSION['generic_export_files_not_found']);

  echo "<div class=\"error notice\">The following files could not be found in the backup directory in order to delete them: <ul><li>" . join('</li><li>', $filenames) . "</li></ul></div>";
  remove_action('admin_notices', 'generic_export_files_not_found');
}

function activation_failed() {
  $errors = $_SESSION['activation_errors'];
  echo "<div class=\"error notice\">Unable to activate the requested content type due to the following errors. Please try again or contact your site administrator.
  <ul><li>" . join('</li><li>', $errors) . "</li></ul>
</div>";
  remove_action('admin_notices', 'activation_failed');
}

function deactivation_failed() {
  $errors = $_SESSION['deactivation_errors'];
  echo "<div class=\"error notice\">Unable to deactivate the requested content type due to the following errors. Please try again or contact your site administrator.
  <ul><li>" . join('</li><li>', $errors) . "</li></ul>
</div>";
  remove_action('admin_notices', 'deactivation_failed');
}

/**
 *  END: Admin Notices
 */

?>