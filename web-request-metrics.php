<?php

/**
Plugin Name: Web Request Metrics
Plugin URI: https://wordpress.org/plugins/web-request-metrics
Description: Plugin to measure the HTTP connection metrics for key pages on your site
Version: 0.4.0
Author: Ross Golder <ross@golder.org>
Author URI: http://www.golder.org/
License: GPLv2
 */

require_once(dirname(__FILE__) . "/web-request-metrics-options.php");

function metrics_curl_handle($uri) {
  $url = get_site_url().$uri;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_exec($ch);
  return $ch;
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

function metrics_output_metric($id, $uri, $desc, $type, $all_stats, $key, $tags) {
  echo "# HELP ".$id." ".$desc."\n";
  echo "# TYPE ".$id." ".$type."\n";

  foreach($all_stats as $uri => $stats) {
    $value = $stats[$key];
    if(substr($key, -5) == "_time") {
      $value = intval($value * 1000);
    }
    $tagstrs = array();
    foreach($tags as $tagkey => $tagvalue) {
      if($tagkey != "" && $tagvalue != "") {
        array_push($tagstrs, $tagkey."=\"".$tagvalue."\"");
      }
    }
    array_push($tagstrs, "uri=\"".$uri."\"");
    echo $id."{".join(",",$tagstrs)."} ".$value."\n";
  }

  echo "\n";
}

function metrics_handler__handle_request($wp_query) {
  global $uris_to_check;

  // If basic auth configured, apply it
  $auth_username = get_option("metrics_auth_username");
  $auth_password = get_option("metrics_auth_password");
  if($auth_username != "" && $auth_password != "") {
    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    if($auth_username != $username || $auth_password != $password) {
      header("HTTP/1.1 401 Unauthorized");
      header('WWW-Authenticate: Basic realm="Metrics"');
      echo "Authorisation required.";
      exit(0);
    }
  }

  // Create array of curl handles, one per URI to fetch
  $uris_to_check_opt = get_option("metrics_uris_to_check");
  $uris_to_check = explode("\n", $uris_to_check_opt);
  $chs = array();
  $mh = curl_multi_init();
  foreach($uris_to_check as $uri) {
    $uri = rtrim($uri);
    if($uri == "") {
      continue;
    }
    $chs[$uri] = metrics_curl_handle($uri);
    curl_multi_add_handle($mh, $chs[$uri]);
  }

  // Process the curl requests in parallel
  $running = null;
  do {
    curl_multi_exec($mh, $running);
  } while ($running);

  // Close curl handles and extract results
  $stats = array();
  foreach($chs as $uri => $ch) {
    curl_multi_remove_handle($mh, $ch);
    $stats[$uri] = curl_getinfo($ch);
  }
  curl_multi_close($mh);

  // Extract stats from each
  if(count($stats) < 1) {
    // Nothing to see here, move along.
    header("HTTP/1.1 503 Service Unavailable");
    exit(0);
  }

  header("Content-Type: text/plain");
  header('Cache-Control: no-cache');

  $tags = array(
    'site' => get_option("metrics_site"),
    'variant' => get_option("metrics_variant")
  );
  metrics_output_metric("web_request_header_size", $uri,
    "The number of bytes in the HTTP header.",
    "gauge",
    $stats, 'header_size', $tags
  );

  metrics_output_metric("web_request_namelookup_time", $uri,
    "The number of milliseconds taken in the hostname lookup.",
    "gauge",
    $stats, 'namelookup_time', $tags
  );

  metrics_output_metric("web_request_connect_time", $uri,
    "The number of milliseconds taken in the TCP connection.",
    "gauge",
    $stats, 'connect_time', $tags
  );

  metrics_output_metric("web_request_pretransfer_time", $uri,
    "The number of milliseconds taken in the pretransfer stage.",
    "gauge",
    $stats, 'pretransfer_time', $tags
  );

  metrics_output_metric("web_request_starttransfer_time", $uri,
    "The number of milliseconds taken in the start transfer stage.",
    "gauge",
    $stats, 'starttransfer_time', $tags
  );

  metrics_output_metric("web_request_total_time", $uri,
    "The number of milliseconds taken in total.",
    "gauge",
    $stats, 'total_time', $tags
  );

  exit(0);
}
