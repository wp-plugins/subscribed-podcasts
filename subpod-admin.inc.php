<?php
  if ( is_admin() ){ // admin actions
    add_action( 'admin_menu', 'subpod_admin_menu' );
    add_action( 'admin_init', 'subpod_register_settings' );
    add_action( 'admin_enqueue_scripts', 'subpod_admin_scripts');
  } else {
    // non-admin enqueues, actions, and filters
  }
   
  function subpod_admin_scripts() {
      if (isset($_GET['page']) && $_GET['page'] == 'subpod') {
          wp_enqueue_media();
          wp_register_script('subpod-admin-js', WP_PLUGIN_URL.'/subscribed-podcasts/assets/js/admin-script.js', array('jquery'));
          wp_enqueue_script('subpod-admin-js');
          
          wp_register_style( 'subpod-admin-css', WP_PLUGIN_URL.'/subscribed-podcasts/assets/css/subpod-admin-style.css', false, '0.1.0' );
          wp_enqueue_style( 'subpod-admin-css' );
      }
  }

  function subpod_admin_menu() {
    add_options_page( __('Your Subscribed Podcasts', 'subpod'), __('Subscribed Podcasts', 'subpod'), 'manage_options', 'subpod', 'subpod_admin_output' );
  }

  function subpod_register_settings() { // whitelist options
    register_setting( 'subpod-settings-group', 'subpod_opml_attachment_id', 'subpod_opml_attachment_id_callback' );
    register_setting( 'subpod-settings-group', 'subpod_opml_filename', 'subpod_opml_attachment_filename_callback' );
  }

  function subpod_opml_attachment_filename_callback($path){
    $opml_path = get_attached_file(get_option('subpod_opml_attachment_id'));
    $opml = simplexml_load_file($opml_path);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $filetype = (finfo_file($finfo, $opml_path));
    finfo_close($finfo);

    if($filetype != 'application/xml'){
      add_settings_error(
        'subpod_opml_filename',
        'subpod_opml_filename',
        __('Please upload a XML File', 'subpod'),
        'error'
      );
      wp_delete_attachment( get_option('subpod_opml_attachment_id'), true );
      update_option('subpod_opml_attachment_id', '');
      return '';
    }

    if($opml->getName() != 'opml'){
      add_settings_error(
        'subpod_opml_filename',
        'subpod_opml_filename',
        __('Please upload a OPML File', 'subpod'),
        'error'
      );
      wp_delete_attachment( get_option('subpod_opml_attachment_id'), true );
      update_option('subpod_opml_attachment_id', '');
      return '';
    }
    return $path;
  }

  function subpod_opml_attachment_id_callback($id){
    $opml_path = get_attached_file($id);
    if ( get_option('subpod_opml_attachment_id') != $id ){
      wp_delete_attachment( get_option('subpod_opml_attachment_id'), true );
    }

    return $id;
  }

  function subpod_admin_output() {
    if ( !current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    if (!file_exists(CACHE_DIR)) {
       wp_mkdir_p(CACHE_DIR) or print_r('<div class="error"><p>%s</p></div>', esc_html__('Can not create Directory %s', 'subpod'));
    }


    // if(isset($_GET['action'])){
    //   if ($_GET['action'] == 'refresh'){
    //     read_source_xml(get_attached_file(get_option('subpod_opml_attachment_id')));
    //   }
    // }

    if(isset($_GET['settings-updated'])){
      empty_cache();
      // read_source_xml(get_attached_file(get_option('subpod_opml_attachment_id')));
    }

    $podcasts = unserialize(get_option('subpod_podcasts'));
    ?>
    <script type="text/javascript">
      var ajax_path = "<?php echo WP_PLUGIN_URL.'/subscribed-podcasts/subpod-ajax.php'; ?>";
    </script>
    <div class="wrap">
      <h2 class="wphead"><?php _e('Your Subscribed Podcasts Settings', 'subpod'); ?></h2>
      <form method="post" action="options.php"> 
        <?php settings_fields( 'subpod-settings-group' ); ?>
        <?php do_settings_sections( 'subpod-settings-group' ); ?>
        <p class="subpod-steps"><?php _e('1. Choose or upload an OPML XML file.', 'subpod'); ?></p>
        <input id="subpod_opml_filename" type="hidden" size="36" name="subpod_opml_filename" value="<?php echo esc_attr( get_option('subpod_opml_filename') ); ?>" /> 
        <input id="upload_image_button" class="button" type="button" value="<?php _e('Upload/Select OPML XML File', 'subpod'); ?>" />
        <span class="subpod-opml-upload"></span>
        <p class="subpod-steps"><?php _e('2. Save your selection', 'subpod');?></p>
        <input id="subpod_opml_attachment_id" type="hidden" name="subpod_opml_attachment_id" value="<?php echo esc_attr( get_option('subpod_opml_attachment_id') ); ?>" />
        <?php submit_button(__('Save selection', 'subpod'), 'primary', 'subpod-submit', false, array('disabled' => 'true')); ?>
      </form>
      
      <hr>

      <p>
        <?php printf(__('Next feed refresh will be at %s', 'subpod'), date('d.m.Y H:i', wp_next_scheduled('subpod_daily_cache_hook'))); ?>
        <a href="<?php admin_url('options-general.php'); ?>?page=subpod" class="button button-secondary" id="subpod-refresh">
          <?php _e('refresh now', 'subpod'); ?>
        </a>
        <span id="reloader"></span>
      </p>
      <?php foreach ($podcasts as $key => $podcast): ?>
        <?php if (file_exists(cache_path($podcast['url']))): ?>
          <div style="width:150px; height: 200px; float: left; margin: 5px 10px 5px 0; overflow: hidden">
            <?php $podcast_data = json_decode( get_podcast($podcast) ); ?>
            <div style="width: 150px; height: 150px;">
              <a href="http://noreferer.de/?<?php echo $podcast_data->podcast_link; ?>" target="_blank">
                <img src="<?php echo $podcast_data->podcast_image; ?>" alt="<?php echo $podcast_data->podcast_name; ?>" width="150">
              </a>
            </div>
            <b><a href="http://noreferer.de/?<?php echo $podcast_data->podcast_link; ?>" target="_blank"><?php echo $podcast_data->podcast_name; ?></a></b>
          </div>
        <?php else: ?>
          <p class="notice"><?php _e('No Podcasts fetched now. Upload/choose an OPML File or use the reload button above.', 'subpod'); ?></p>
          <?php break; ?>
        <?php endif; ?>
      <?php endforeach; ?>


    </div>

    <?php
  }
?>