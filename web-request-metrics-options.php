<?php

function web_request_metrics_options_page() {
	global $web_request_metrics_options;

	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.') );
	}

	if (isset($_POST['submit']) && isset($_POST['metricsoptions'])) {
		check_admin_referer('web-request-metrics-options');
		web_request_metrics_options_update();
	}

	?>
<style type="text/css">
p.error {
	color: red;
}
</style>

<div class="wrap">

<h2>Web Request Metrics Settings</h2>

<p>Metrics Endpoint: <tt><a href="/?__metrics=1"><?php echo get_home_url() ?>/?__metrics=1</a></tt></p>

<form method="post" action="">
<?php
if(function_exists('wp_nonce_field') )
	wp_nonce_field('web-request-metrics-options');
?>
<input type="hidden" name="metricsoptions" value="true"/>

<h3>Authentication</h3>

<p>If you want to protect your metrics endpoint, please supply a username/password for Basic Authentication to be applied. Leave blank for no authentication to apply.</p>

<table class="form-table">
	<tr valign="top">
		<th scope="row">Username</th>
		<td>
      <input type="text" name="metrics_auth_username" value="<?php echo get_option('metrics_auth_username'); ?>" />
		</td>
  </tr>
	<tr valign="top">
		<th scope="row">Password</th>
		<td>
      <input type="text" name="metrics_auth_password" value="<?php echo get_option('metrics_auth_password'); ?>" />
		</td>
  </tr>
</table>

<h3>Site tags</h3>

<p>Extra tags to help group sites (i.e. 'Main site', 'Client site') and their variants (i.e. 'Production' or 'QA').</p>

<table class="form-table">
	<tr valign="top">
		<th scope="row">Site</th>
		<td>
			<input type="text" name="metrics_site" value="<?php echo get_option('metrics_site'); ?>" />
		</td>
	</tr>
	<tr>
		<th scope="row">Variant</th>
		<td>
			<input type="text" name="metrics_variant" value="<?php echo get_option('metrics_variant'); ?>" />
		</td>
  </tr>
</table>


<h3>URIs to check</h3>

<p>List the URIs you wish to provide metrics for in your metrics requests, one URI per line.</p>

<table class="form-table">
	<tr valign="top">
		<th scope="row">URIs</th>
		<td>
      <textarea name="metrics_uris_to_check" cols="50" rows="6"><?php echo get_option('metrics_uris_to_check'); ?></textarea>
		</td>
  </tr>
</table>

<p class="submit">
	<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
</p>

</form>
</div>
	<?php
}

function web_request_metrics_options_update() {
	update_option('metrics_auth_username', sanitize_text_field($_REQUEST['metrics_auth_username']));
	update_option('metrics_auth_password', sanitize_text_field($_REQUEST['metrics_auth_password']));
	update_option('metrics_site', sanitize_text_field($_REQUEST['metrics_site']));
	update_option('metrics_variant', sanitize_text_field($_REQUEST['metrics_variant']));
	update_option('metrics_uris_to_check', sanitize_textarea_field($_REQUEST['metrics_uris_to_check']));

	?>
	<div class="updated">
	<p>Configuration updated successfully.</p>
	</div>
	<?php
}

function web_request_metrics_admin_init() {
	// Default settings
	if(!get_option('metrics_auth_username')) {
		update_option('metrics_auth_username', '');
	}
	if(!get_option('metrics_auth_password')) {
		update_option('metrics_auth_password', '');
	}
	if(!get_option('metrics_auth_site')) {
		update_option('metrics_auth_site', '');
	}
	if(!get_option('metrics_auth_variant')) {
		update_option('metrics_auth_variant', '');
	}
	if(!get_option('metrics_uris_to_check')) {
		update_option('metrics_uris_to_check', implode("\n", array("/", "/wp-login.php")));
	}
}

function web_request_metrics_admin_menu() {
	global $web_request_metrics_options_page;

	$web_request_metrics_options_page = add_options_page(
		__('Web Metrics', 'web-request-metrics'),
		__('Web Metrics', 'web-request-metrics'),
		'manage_options',
		__FILE__,
		'web_request_metrics_options_page');
}

// Hooks to allow Web Request Metrics configuration settings and options to be set
add_action('admin_init', 'web_request_metrics_admin_init');
add_action('admin_menu', 'web_request_metrics_admin_menu');
