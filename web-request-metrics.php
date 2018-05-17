<?php

/**
Plugin Name: Web Request Metrics
Plugin URI: https://wordpress.org/plugins/web-request-metrics
Description: Plugin to measure the HTTP connection metrics for key pages on your site
Version: 0.1.0
Author: Ross Golder <ross@golder.org>
Author URI: http://www.golder.org/
License: GPLv2
 */

// TODO: Make configurable...
global $uris_to_check;
$uris_to_check = array(
  "/",
  "/wp-login.php"
);

function fetch_stats($uri) {
  $url = get_site_url().$uri;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_exec($ch);
  $stats = curl_getinfo($ch);
  curl_close($ch);

  return $stats;
}

function metrics_handler_init() {
  add_rewrite_rule('^metrics/?', 'index.php?__metrics=1', 'top');
}
add_action('init', 'metrics_handler_init');

function metrics_query_vars($vars) {
  $vars[] = '__metrics';
  return $vars;
}
add_action('query_vars', 'metrics_query_vars');

function metrics_request_parser($wp_query) {
  global $wp;

  if(isset($wp->query_vars['__metrics'])) {
    metrics_handler__handle_request($wp_query);
    die(); // stop default WP behavior
  }
}
add_action("parse_request", "metrics_request_parser");

function output_metric($id, $uri, $desc, $type, $value) {
  echo "# HELP ".$id." ".$desc."\n";
  echo "# TYPE ".$id." ".$type."\n";
  echo $id."{uri=\"".$uri."\"} ".$value."\n\n";
}
function metrics_handler__handle_request($wp_query) {
  global $uris_to_check;

  header("Content-Type: text/plain");

  foreach($uris_to_check as $uri) {
    $stats = fetch_stats($uri);

    output_metric("web_request_header_size", $uri,
      "The number of bytes in the HTTP header.",
      "gauge",
      $stats['header_size']
    );

    output_metric("web_request_namelookup_time", $uri,
      "The number of milliseconds taken in the hostname lookup.",
      "gauge",
      intval($stats['namelookup_time'] * 1000)
    );

    output_metric("web_request_connect_time", $uri,
      "The number of milliseconds taken in the TCP connection.",
      "gauge",
      intval($stats['connect_time'] * 1000)
    );

    output_metric("web_request_pretransfer_time", $uri,
      "The number of milliseconds taken in the pretransfer stage.",
      "gauge",
      intval($stats['pretransfer_time'] * 1000)
    );

    output_metric("web_request_starttransfer_time", $uri,
      "The number of milliseconds taken in the start transfer stage.",
      "gauge",
      intval($stats['starttransfer_time'] * 1000)
    );

    output_metric("web_request_total_time", $uri,
      "The number of milliseconds taken in total.",
      "gauge",
      intval($stats['total_time'] * 1000)
    );
  }
}
