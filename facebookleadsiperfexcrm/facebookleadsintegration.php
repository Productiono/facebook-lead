<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Facebook leads integration
Module URI: https://codecanyon.net/item/facebook-leads-integration-sync-module-for-perfex-crm/25623248
Description: Sync leads between Facebook Leads and Perfex Leads
Version: 1.0.0
Requires at least: 2.3.*
Author: Themesic Interactive
Author URI: https://codecanyon.net/user/themesic/portfolio
*/

define('FACEBOOKLEADSINTEGRATION_MODULE', 'facebookleadsintegration');
require_once __DIR__.'/vendor/autoload.php';
modules\facebookleadsintegration\core\Apiinit::the_da_vinci_code(FACEBOOKLEADSINTEGRATION_MODULE);
modules\facebookleadsintegration\core\Apiinit::ease_of_mind(FACEBOOKLEADSINTEGRATION_MODULE);

hooks()->add_action('admin_init', 'facebookleadsintegration_module_init_menu_items');
hooks()->add_action('admin_init', 'add_settings_tab');
hooks()->add_action('admin_init', 'exclude_uri');


// exclude urls from csrf
function exclude_uri() {

    $CI = &get_instance();
    $CI->load->config('migration');
    $update_info = $CI->config->item('migration_version');
    if(!get_option('current_perfex_version'))
    {
        update_option('current_perfex_version',$update_info);
    }
    if(!get_option('excluded_uri_for_facebookleadsintegration_once') || get_option('current_perfex_version') != $update_info)
    {
        
        
        $myfile = fopen(APPPATH."config/config.php", "a") or die("Unable to open file!");
        $txt = "if(!isset(\$config['csrf_exclude_uris']))
        {
            \$config['csrf_exclude_uris']=[];
        }";
        fwrite($myfile, "\n". $txt);
        $txt = "\$config['csrf_exclude_uris'] = array_merge(\$config['csrf_exclude_uris'],array('facebookleadsintegration/webhook'));";
        fwrite($myfile, "\n". $txt);
        $txt = "\$config['csrf_exclude_uris'] = array_merge(\$config['csrf_exclude_uris'],array('facebookleadsintegration/get_lead_data'));";
        fwrite($myfile, "\n". $txt);
        fclose($myfile);
        update_option('current_perfex_version',$update_info);
        update_option('excluded_uri_for_facebookleadsintegration_once', 1);
    }
    
    
}

function add_settings_tab()
{
    $CI = &get_instance();
    $CI->app_tabs->add_settings_tab('facebookleadsintegration', [
        'name'     => _l('facebookleadsintegration'),
        'view'     => FACEBOOKLEADSINTEGRATION_MODULE . '/facebookleadsintegration_view',
        'position' => 101,
    ]);
}


/**
 * Register activation module hook
 */
register_activation_hook(FACEBOOKLEADSINTEGRATION_MODULE, 'facebookleadsintegration_module_activation_hook');

function facebookleadsintegration_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
 * Register language files, must be registered if the module is using languages
 */
register_language_files(FACEBOOKLEADSINTEGRATION_MODULE, [FACEBOOKLEADSINTEGRATION_MODULE]);

/**
 * Init FACEBOOK LEADS INTEGRATION module menu items in setup in admin_init hook
 * @return null
 */
function facebookleadsintegration_module_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
        'name'       => _l('facebookleadsintegration'),
        'permission' => 'facebookleadsintegration',
        'url'        => 'facebookleadsintegration',
        'position'   => 69,
    ]);
}

hooks()->add_action('app_init', FACEBOOKLEADSINTEGRATION_MODULE.'_actLib');
function facebookleadsintegration_actLib()
{
    $CI = &get_instance();
    $CI->load->library(FACEBOOKLEADSINTEGRATION_MODULE.'/Facebookleadsintegration_aeiou');
    $envato_res = $CI->facebookleadsintegration_aeiou->validatePurchase(FACEBOOKLEADSINTEGRATION_MODULE);
    if (!$envato_res) {
        set_alert('danger', 'One of your modules failed its verification and got deactivated. Please reactivate or contact support.');
    }
}

hooks()->add_action('pre_activate_module', FACEBOOKLEADSINTEGRATION_MODULE.'_sidecheck');
function facebookleadsintegration_sidecheck($module_name)
{
    if (FACEBOOKLEADSINTEGRATION_MODULE == $module_name['system_name']) {
        modules\facebookleadsintegration\core\Apiinit::activate($module_name);
    }
}

hooks()->add_action('pre_deactivate_module', FACEBOOKLEADSINTEGRATION_MODULE.'_deregister');
function facebookleadsintegration_deregister($module_name)
{
    if (FACEBOOKLEADSINTEGRATION_MODULE == $module_name['system_name']) {
        delete_option(FACEBOOKLEADSINTEGRATION_MODULE.'_verification_id');
        delete_option(FACEBOOKLEADSINTEGRATION_MODULE.'_last_verification');
        delete_option(FACEBOOKLEADSINTEGRATION_MODULE.'_product_token');
        delete_option(FACEBOOKLEADSINTEGRATION_MODULE.'_heartbeat');
    }
}

