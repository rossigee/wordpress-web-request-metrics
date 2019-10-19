<?php

/**
Plugin Name: Web Request Metrics
Plugin URI: https://wordpress.org/plugins/web-request-metrics
Description: Plugin to measure the HTTP connection metrics for key pages on your site
Version: 0.2.4
Author: Ross Golder <ross@golder.org>
Author URI: http://www.golder.org/
License: GPLv2
 */

require_once(dirname(__FILE__) . "/web-request-metrics-options.php");

function metrics_fetch_stats($uri) {
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

function metrics_output_metric($id, $uri, $desc, $type, $all_stats, $key) {
  echo "# HELP ".$id." ".$desc."\n";
  echo "# TYPE ".$id." ".$type."\n";

  foreach($all_stats as $uri => $stats) {
    $value = $stats[$key];
    if(substr($key, -5) == "_time") {
      $value = intval($value * 1000);
    }
    echo $id."{uri=\"".$uri."\"} ".$value."\n";
  }

  echo "\n";
}

function metrics_handler__handle_request($wp_query) {
  global $uris_to_check;

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

  $uris_to_check_opt = get_option("metrics_uris_to_check");
  $uris_to_check = explode("\n", $uris_to_check_opt);
  $stats = array();
  foreach($uris_to_check as $uri) {
    $uri = rtrim($uri);
    if($uri == "") {
      continue;
    }

    $stats[$uri] = metrics_fetch_stats($uri);
  }

  if(count($stats) < 1) {
    // Nothing to see here, move along.
    header("HTTP/1.1 503 Service Unavailable");
    exit(0);
  }

  header("Content-Type: text/plain");
  header('Cache-Control: no-cache');

  metrics_output_metric("web_request_header_size", $uri,
    "The number of bytes in the HTTP header.",
    "gauge",
    $stats, 'header_size'
  );

  metrics_output_metric("web_request_namelookup_time", $uri,
    "The number of milliseconds taken in the hostname lookup.",
    "gauge",
    $stats, 'namelookup_time'
  );

  metrics_output_metric("web_request_connect_time", $uri,
    "The number of milliseconds taken in the TCP connection.",
    "gauge",
    $stats, 'connect_time'
  );

  metrics_output_metric("web_request_pretransfer_time", $uri,
    "The number of milliseconds taken in the pretransfer stage.",
    "gauge",
    $stats, 'pretransfer_time'
  );

  metrics_output_metric("web_request_starttransfer_time", $uri,
    "The number of milliseconds taken in the start transfer stage.",
    "gauge",
    $stats, 'starttransfer_time'
  );

  metrics_output_metric("web_request_total_time", $uri,
    "The number of milliseconds taken in total.",
    "gauge",
    $stats, 'total_time'
  );
}
