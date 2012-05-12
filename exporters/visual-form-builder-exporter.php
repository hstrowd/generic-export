<?php

// Provides support for exporting content created by the Visual Form Builder plugin.
class VisualFormBuilderExporter implements iGenericExporter {
  public function __construct() {
    global $wpdb;
    
    // Setup global database table names
    $this->form_table_name = $wpdb->prefix . 'visual_form_builder_forms';
    $this->entries_table_name = $wpdb->prefix . 'visual_form_builder_entries';
  }

  public function activate(){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    if(!is_plugin_active('visual-form-builder/visual-form-builder.php'))
      return array('activation_failed', array('The Visual Form Builder plugin must be installed and active to export content of this type.'));

    // Add an exported column to the appropriate table.
    global $wpdb;
    $wpdb->query("ALTER TABLE " . $this->entries_table_name . 
		 " ADD COLUMN exported BOOLEAN NOT NULL DEFAULT FALSE;");

    return array('success');
  }

  public function deactivate(){
    // Drop exported column from the appropriate table.
    global $wpdb;
    $wpdb->query("ALTER TABLE " . $this->entries_table_name . " DROP COLUMN exported;");

    return array('success');
  }

  public function get_unexported_entries() {
    global $wpdb;
    
    $entry_ids = $wpdb->get_col( "SELECT entries.entries_id FROM $this->entries_table_name AS entries WHERE entries.exported = FALSE" );
    return $entry_ids;
  }

  public function get_all_entries() {
    global $wpdb;
    
    $entry_ids = $wpdb->get_col( "SELECT entries.entries_id FROM $this->entries_table_name AS entries" );
    return $entry_ids;
  }

  // Copied directly out of visual-form-builder/class-entries-list.php
  public function export_entries( Array $entry_ids = NULL ) {    
    global $wpdb;
    
    /* Setup our query to accept selected entry IDs */	
    if ( is_array( $entry_ids ) && !empty( $entry_ids ) )
      $selection = " WHERE entries.entries_id IN (" . implode( ',', $entry_ids ) . ")";

  
    $entries = $wpdb->get_results( "SELECT entries.*, forms.form_title FROM $this->entries_table_name AS entries JOIN $this->form_table_name AS forms USING(form_id) $selection ORDER BY entries_id DESC" );
    
    /* If there's entries returned, do our CSV stuff */
    if ( $entries ) {
      
      /* Setup our default columns */
      $cols = array(
        'entries_id' => array(
          'header' => __( 'Entries ID' , 'visual-form-builder'),
          'data' => array()
          ),
        'form_title' => array(
          'header' => __( 'Form' , 'visual-form-builder'),
          'data' => array()
          ),
        'date_submitted' => array(
          'header' => __( 'Date Submitted' , 'visual-form-builder'),
          'data' => array()
          ),
        'ip_address' => array(
          'header' => __( 'IP Address' , 'visual-form-builder'),
          'data' => array()
          ),
        'subject' => array(
          'header' => __( 'Email Subject' , 'visual-form-builder'),
          'data' => array()
          ),
        'sender_name' => array(
          'header' => __( 'Sender Name' , 'visual-form-builder'),
          'data' => array()
          ),
        'sender_email' => array(
          'header' => __( 'Sender Email' , 'visual-form-builder'),
          'data' => array()
          ),
        'emails_to' => array(
          'header' => __( 'Emailed To' , 'visual-form-builder'),
          'data' => array()
          )
      );
      
      /* Initialize row index at 0 */
      $row = 0;
      
      /* Loop through all entries */
      foreach ( $entries as $entry ) {
        /* Loop through each entry and its fields */
        foreach ( $entry as $key => $value ) {
          /* Handle each column in the entries table */
          switch ( $key ) {
            case 'entries_id':
            case 'form_title':
            case 'date_submitted':
            case 'ip_address':
            case 'subject':
            case 'sender_name':
            case 'sender_email':
              $cols[ $key ][ 'data' ][ $row ] = $value;
            break;
            
            case 'emails_to':
              $cols[ $key ][ 'data' ][ $row ] = implode( ',', maybe_unserialize( $value ) );
            break;
            
            case 'data':
              /* Unserialize value only if it was serialized */
              $fields = maybe_unserialize( $value );
              
              /* Loop through our submitted data */
              foreach ( $fields as $field_key => $field_value ) {
                if ( !is_array( $field_value ) ) {
                  /* Replace quotes for the header */
                  $header = str_replace( '"', '""', ucwords( $field_key ) );
                  
                  /* Replace all spaces for each form field name */
                  $field_key = preg_replace( '/(\s)/i', '', $field_key );
                  
                  /* Find new field names and make a new column with a header */
                  if ( !array_key_exists( $field_key, $cols ) ) {
                    $cols[$field_key] = array(
                      'header' => $header,
                      'data' => array()
                      );                  
                  }
                  
                  /* Get rid of single quote entity */
                  $field_value = str_replace( '&#039;', "'", $field_value );
                  
                  /* Load data, row by row */
                  $cols[ $field_key ][ 'data' ][ $row ] = str_replace( '"', '""', stripslashes( html_entity_decode( $field_value ) ) );
                }
                else {
                  /* Cast each array as an object */
                  $obj = (object) $field_value;
                  
                  switch ( $obj->type ) {
                    case 'fieldset' :
                    case 'section' :
                    case 'instructions' :
                    case 'submit' :
                    break;
                    
                    default :
                      /* Replace quotes for the header */
                      $header = str_replace( '"', '""', $obj->name );
                      
                      /* Find new field names and make a new column with a header */
                      if ( !array_key_exists( $obj->name, $cols ) ) {
                        $cols[$obj->name] = array(
                          'header' => $header,
                          'data' => array()
                          );                  
                      }
                      
                      /* Get rid of single quote entity */
                      $obj->value = str_replace( '&#039;', "'", $obj->value );
                      
                      /* Load data, row by row */
                      $cols[ $obj->name ][ 'data' ][ $row ] = str_replace( '"', '""', stripslashes( html_entity_decode( $obj->value ) ) );

                    break;
                  }
                }
              }
            break;
          }
            
        }
        
        $row++;
      }
    }      

    /* Setup our CSV vars */
    $csv_headers = NULL;
    $csv_rows = array();
    
    /* Loop through each column */
    foreach ( $cols as $data ) {
      /* End our header row, if needed */
      if ( $csv_headers )
        $csv_headers .= ',';
      
      /* Build our headers */
      $csv_headers .= "{$data['header']}";
      
      /* Loop through each row of data and add to our CSV */
      for ( $i = 0; $i < $row; $i++ ) {
        /* End our row of data, if needed */
        if ( $csv_rows[$i] )
          $csv_rows[$i] .= ',';
        
        /* Add a starting quote for this row's data */
        $csv_rows[$i] .= '"';
        
        /* If there's data at this point, add it to the row */
        if ( array_key_exists( $i, $data['data'] ) )
          $csv_rows[$i] .=  $data['data'][$i];
        
        /* Add a closing quote for this row's data */
        $csv_rows[$i] .= '"';        
      }      
    }
    
    /* Return headers for the CSV */
    $output = $csv_headers . "\n";
    
    /* Return each row of data for the CSV */
    foreach ( $csv_rows as $row ) {
      $output .= $row . "\n";
    }

    return $output;
  }

  public function mark_entries_exported( Array $entry_ids = NULL ) {
    global $wpdb;
    
    /* Setup our query to accept selected entry IDs */	
    if ( is_array( $entry_ids ) && !empty( $entry_ids ) )
      $selection = " WHERE entries.entries_id IN (" . implode( ',', $entry_ids ) . ")";
  
    $entries = $wpdb->query( "UPDATE $this->entries_table_name AS entries SET exported = TRUE $selection" );
  }
}
