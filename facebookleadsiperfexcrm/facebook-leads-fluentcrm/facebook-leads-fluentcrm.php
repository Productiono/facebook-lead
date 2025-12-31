<?php
/**
 * Plugin Name: Facebook Leads for FluentCRM
 * Plugin URI: https://example.com
 * Description: Connects Facebook Lead Ads to FluentCRM.
 * Version: 1.0.0
 * Author: Your Company
 * Author URI: https://example.com
 * Text Domain: facebook-leads-fluentcrm
 * Requires PHP: 8.4
 * Requires at least: 6.0
 */

declare(strict_types=1);

if ( ! defined('ABSPATH') ) {
	exit;
}

require_once __DIR__ . '/includes/Autoloader.php';

FacebookLeadsFluentCRM\Autoloader::register();

FacebookLeadsFluentCRM\Plugin::boot(__FILE__);
