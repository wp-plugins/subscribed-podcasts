<?php
  defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
  /**
   * Plugin Name: Subscribed Podcasts
   * Plugin URI: https://github.com/sethiele/subscribed-podcasts
   * Description: Display your subscribed podcasts
   * Version: 0.1.2
   * Author: Sebastian
   * Author URI: http://sebastian-thiele.net
   * Text Domain: subpod
   * Domain Path: /locale/
   * License: MIT
   */

  // Load I18n
  add_action( 'plugins_loaded', 'subpod_load_textdomain' );
  function subpod_load_textdomain() {
    load_plugin_textdomain('subpod', false, basename( dirname( __FILE__ ) ) . '/locale' );
  }

  // Upload OPML-Files
  add_filter('upload_mimes', 'subpod_upload_xml');
  function subpod_upload_xml($mimes) {
      $mimes = array_merge($mimes, array('opml' => 'application/xml'));
      return $mimes;
  }

  if (!defined('CACHE_DIR')){
    $upload_dir = wp_upload_dir();
    $cache_dir = $upload_dir['basedir'] . '/cache/subpod/';
    define("CACHE_DIR", $cache_dir);
  }
  

  // Installation
  function subpod_activate() {
    update_option( 'subpod_opml_attachment_id', '0' );
    update_option( 'subpod_opml_filename', '' );
    update_option( 'subpod_podcasts', '' );
    wp_mkdir_p(CACHE_DIR) or print_r('<div class="error"><p>%s</p></div>', esc_html__('Can not create Directory %s', 'subpod'));
    wp_schedule_event( time(), 'daily', 'subpod_daily_cache_hook' );
  }
  register_activation_hook( __FILE__, 'subpod_activate' );

  // Uninstallation
  function subpod_deactivation(){
    if ( get_option('subpod_opml_attachment_id')){
      wp_delete_attachment( get_option('subpod_opml_attachment_id'), true );
    }
    delete_option( 'subpod_opml_attachment_id' );
    delete_option( 'subpod_opml_filename' );
    delete_option( 'subpod_podcasts' );
    empty_cache();
    wp_clear_scheduled_hook( 'subpod_daily_cache_hook' );
  }
  register_deactivation_hook( __FILE__, 'subpod_deactivation' );

  // Chron Job
  add_action( 'subpod_daily_cache_hook', 'subpod_daily_cache' );
  /**
   * On the scheduled action hook, run the function.
   */
  function subpod_daily_cache() {
    $podcasts = unserialize(get_option('subpod_podcasts'));
    foreach ($podcasts as $key => $podcast){
      cache_feed($podcast['url'], $podcast['url']);
    }
  }

  // Settings Link
  add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'subpod_action_links' );

  function subpod_action_links( $links ) {
     $links[] = '<a href="'. get_admin_url(null, 'options-general.php?page=subpod') .'">Settings</a>';
     $links[] = '<a href="https://github.com/sethiele/subscribed-podcasts/issues" target="_blank">Issues</a>';
     return $links;
  }

  // Read Source File
  function read_source_xml($path){
    $feeds = array();
    $podcasts = simplexml_load_file($path);
    $podcasts_items = $podcasts->body->outline->outline;
    foreach ($podcasts_items as $key => $item) {
      cache_feed((string)$item->attributes()->xmlUrl, (string)$item->attributes()->xmlUrl);
      array_push($feeds, 
        array(
          'title' => (string)$item->attributes()->text,
          'url' => (string)$item->attributes()->xmlUrl
        )
      );
    }
    update_option( 'subpod_podcasts', serialize($feeds) );
  }

  // feedmd5
  function feed_md5($filename){
    return md5($filename);
  }

  // Caching File
  function cache_feed($feed_url, $filename){
    file_put_contents ( cache_path($filename) , fopen($feed_url, 'r'), LOCK_EX);;
  }

  // Cache path
  function cache_path($url){
    return CACHE_DIR . feed_md5($url) . ".xml";
  }

  // Empty Cache
  function empty_cache(){
    $files = glob(CACHE_DIR . '*');
    foreach($files as $file){
      if(is_file($file))
        unlink($file);
    }
  }

  function get_podcast($podcast){
    $casts = simplexml_load_file(CACHE_DIR . feed_md5($podcast['url']) . '.xml');
    $cast = array();
    $cast['podcast_name'] = (string)$casts->channel->title;
    $cast['podcast_link'] = (string)$casts->channel->link;
    $cast['podcast_description'] = (string)$casts->channel->description;
    $cast['podcast_image'] = ($casts->channel->image->url)?
      (string)$casts->channel->image->url:
      (string)$casts->channel->children('http://www.itunes.com/dtds/podcast-1.0.dtd')->image->attributes()->href;
    $cast['podcast_date'] =  date('d.m.Y H:i', strtotime((string)$casts->channel->lastBuildDate));
    $cast['podcast_items'] = array();
    $item_num = 0;
    foreach ($casts->channel->item as $key => $item) {
      $item_link = ($item->link)?(string)$item->link:$cast['podcast_link'];
      array_push($cast['podcast_items'], array(
        'title' => (string)$item->title, 
        'link' => $item_link, 
        'pubDate' => date('d.m.Y H:i', strtotime((string)$item->pubDate))
        ));
      $item_num++;
      if($item_num == 10){
        break;
      }
    }
    return json_encode($cast);
  }


  // wp_register_script('subpod-js', WP_PLUGIN_URL.'/subscribed-podcasts/assets/js/subpod.js', array('jquery'));
  // wp_enqueue_script('subpod-js');

  include('subpod-admin.inc.php');
  include('subpod-template.inc.php');
?>