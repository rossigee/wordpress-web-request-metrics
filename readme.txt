=== Web Request Metrics ===

* Contributors: rossigee
* Tags: wordpress
* Requires at least: 4.7.2
* Tested up to: 4.9.5
* Stable tag: 0.2
* License: GPLv2

This plugin provides a mechanism for checking the main connection statistics for page requests to key pages on your site.

It does this by connecting to the key pages you configure every minute, using 'curl' PHP functions, gathering the metrics from the connection into a JSON statistics file to be supplied to your monitoring systems.

In our case, we run Prometheus, so a metrics endpoint is provided. We gather the metrics with the following section of Prometheus configuration:

```
- job_name: 'WebRequestMetrics'
  scrape_interval: 60s
  honor_labels: true
  scheme: 'https'
  basic_auth:
    username: 'prometheus'
    password: 'secret_token_known_to_your_monitoring_system'
  metrics_path: '/'
  params:
    __metrics: [1]
  static_configs:
    - targets:
      - www.golder.org
      - www.myothersite.com

```


== Changelog ==

= 0.2 =

* Basic settings page. Configurable list of URIs to check. Optional Basic Auth.

= 0.1 =

* Initial version.
