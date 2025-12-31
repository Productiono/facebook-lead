<?php
/**
 * Plugin Name: Facebook Lead Ads for FluentCRM
 * Description: Sync Facebook Lead Ads with FluentCRM.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Author: OpenAI
 * Text Domain: fluentcrm-facebook-leads
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FLFBL_FILE', __FILE__);
define('FLFBL_PATH', plugin_dir_path(__FILE__));
define('FLFBL_URL', plugin_dir_url(__FILE__));
define('FLFBL_VERSION', '1.0.0');

require_once FLFBL_PATH . 'includes/class-settings.php';
require_once FLFBL_PATH . 'includes/class-logger.php';
require_once FLFBL_PATH . 'includes/class-facebook-client.php';
require_once FLFBL_PATH . 'includes/class-lead-processor.php';
require_once FLFBL_PATH . 'includes/class-admin.php';
require_once FLFBL_PATH . 'includes/class-webhook-controller.php';
require_once FLFBL_PATH . 'includes/class-plugin.php';

add_action('plugins_loaded', static function () {
    if (!function_exists('fluentcrm')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p>FluentCRM must be active for Facebook Lead Ads for FluentCRM.</p></div>';
        });
        return;
    }
    $settings = new \FLFBL\Settings();
    $logger = new \FLFBL\Logger();
    $client = new \FLFBL\Facebook_Client($settings, $logger);
    $processor = new \FLFBL\Lead_Processor($settings, $client, $logger);
    $admin = new \FLFBL\Admin($settings, $client, $processor, $logger);
    $webhook = new \FLFBL\Webhook_Controller($settings, $processor, $logger);
    new \FLFBL\Plugin($settings, $client, $processor, $admin, $webhook, $logger);
});

register_activation_hook(__FILE__, static function () {
    $settings = new \FLFBL\Settings();
    $logger = new \FLFBL\Logger();
    $settings->bootstrap();
    $logger->bootstrap();
});
