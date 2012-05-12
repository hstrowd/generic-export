<div class="generic_export_admin">
<div id="icon-tools" class="icon32"><br></div>
<h2>Generic Export - Data Export</h2>

<?php
  if(count($activated_types) > 0) { 
 ?>
<div class="export">
  <h3>Data Export</h3>
  <p>Select the appropriate export options:</p>

  <form id="export-content" action method="post">
    <input name="page" type="hidden" value="generic-export">
    <input name="action" type="hidden" value="export-content">
    <input type="hidden" name="_wp_http_referer" 
      value="<?php echo admin_url('options-general.php?page=generic-export'); ?>">

    <div id="content_type" class="export_option">
      <div class="label">Type of Content: </div>
      <div class="content_type_selection">
        <select size="1" name="content-type">
          <?php
            foreach($activated_types as $content_type_key) {
           ?>
            <option value="<?php echo $content_type_key; ?>">
              <?php echo GenericExporter::$supported_content_types[$content_type_key][0]; ?>
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
  <form id="generic-export-delete-backup-files" method="post">
    <input name="page" type="hidden" value="generic-export">
    <input name="action" type="hidden" value="delete-backup-files">
    <input type="hidden" name="_wp_http_referer" 
      value="/restore_dev/wp-admin/options-general.php?page=generic-export">
    <ul>
      <?php
  	foreach($export_backups as $backup_filename) {
       ?>
        <li>
          <input type="checkbox" name="backup-files-to-delete[]" value="<?php echo $backup_filename; ?>">
          <a href="<?php echo GENERIC_EXPORT_URL . '/export_backups/' . $backup_filename; ?>"><?php echo $backup_filename; ?></a>
        </li>
      <?php
        }
       ?>
    </ul>
    <div class="actions">
      <input type="button" value="Check All" class="button"
             onClick="checkAll('backup-files-to-delete[]')" /> |
      <input type="submit" value="Delete" class="button" />
    </div>
  </form>
</div>
<?php
  }
 ?>

<div class="configuration">
  <h3>Plugin Configuration</h3>
  <p>Use the options below to configure the content types that you would like to be exportable:</p>
  <div class="warning">WARNING: Deactivating a content type will delete the data stored in the database that tracks the content that has already been exported. This will mean that if you reactivate the plugin in the future, your first export will include all records and not just those that were not previously exported. Proceed with caution!</div>
  <?php
    foreach(GenericExporter::$supported_content_types as $content_type_key => $content_type_array) {
   ?>
  <div class="content_type">
    <div class="label"><?php echo $content_type_array[0]; ?></div>
    <?php 
      if(!in_array($content_type_key, $activated_types)) { 
     ?>
    <div class="activation_button">
      <a href="?page=generic-export&action=activate-content-type&content-type=<?php echo $content_type_key; ?>" class="button">Activate</a>
    </div>
    <?php
      } else {
     ?>
    <div class="deactivation_button">
      <a href="?page=generic-export&action=deactivate-content-type&content-type=<?php echo $content_type_key; ?>" class="button">Deactivate</a>
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
