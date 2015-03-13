<?php
function subpod_template( $atts ){
  $podcasts = unserialize(get_option('subpod_podcasts'));
  $ret .= "<div class=\"subpod-podcasts\">" . PHP_EOL;
  foreach ($podcasts as $key => $podcast) {
    if (file_exists(cache_path($podcast['url']))){
      $podcast_data = json_decode( get_podcast( $podcast ) );
      $ret .= "<div class=\"subpod-podcasts-podcast\">" . PHP_EOL;
      $ret .= "<h2 class=\"subpod-podcast-title\"><a href=\"" . $podcast_data->podcast_link . "\" target=\"_blank\">" . $podcast_data->podcast_name . "</a></h2>" . PHP_EOL;
      $ret .= "<img src=\"" . $podcast_data->podcast_image . "\" alt=\"" . $podcast_data->podcast_name . "\" class=\"subpod-podcast-image\">" . PHP_EOL;
      $ret .= "<p class=\"subpod-podcast-description\">" . $podcast_data->podcast_description . "</p>" . PHP_EOL;
      $ret .= "<ul class=\"subpod-podcast-casts\">" . PHP_EOL;
      foreach ($podcast_data->podcast_items as $episode => $cast) {
        $ret .= "<li><span class=\"subpod-cast-pubdate\">" . $cast->pubDate . "</span> <a href=\"" . $cast->link . "\" tareget=\"_blank\" class=\"subpod-cast-title\">" . $cast->title . "</a></li>" . PHP_EOL;
      }
      $ret .= "</ul>" . PHP_EOL;
      $ret .= "</div>" . PHP_EOL;
    }
  }
  $ret .= "</div>" . PHP_EOL . PHP_EOL;
  return $ret;
}

add_shortcode( 'subscribed-podcasts', 'subpod_template' );
?>