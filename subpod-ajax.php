<?php
  $wp_path = explode('wp-content', dirname(__FILE__)); require($wp_path[0].'wp-load.php');
  // include('functions.php');
  // echo json_encode(CACHE_DIR);

  if(isset($_GET['action'])){
    if($_GET['action'] == 'update-podcasts'){
      empty_cache();
      read_source_xml(get_attached_file(get_option('subpod_opml_attachment_id')));
      echo json_encode(array('status' => 'OK'));
    }
  }
?>