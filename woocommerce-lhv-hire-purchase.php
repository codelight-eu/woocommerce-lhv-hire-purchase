<?php
/**
 * Plugin Name:       WooCommerce LHV hire-purchase
 * Plugin URI:        https://github.com/codelight-eu/woocommerce-lhv-hire-purchase
 * Description:       WordPress and WooCommerce plugin for LHV Bank hire-purchase payment gateway.
 * Version:           1.1.0
 * Author:            Codelight
 * Author URI:        https://codelight.eu/
 * License:           MIT
 * Text Domain:       lhv
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Initialize autoloader, run the plugin
 */
function lhv_hire_purchase_init()
{
    load_plugin_textdomain('lhv-hire-purchase', false, dirname(plugin_basename(__FILE__)) . '/i18n/');

    // Check if composer autoloader is created
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        lhv_admin_notice('error', __("You are running the development version of LHV hire-purchase which requires you to run 'composer install'", 'lhv-hire-purchase'));
        return;
    }

    // Check if WooCommerce is installed and active
    if (!class_exists('WooCommerce') || !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        lhv_admin_notice('error', __('WooCommerce is not installed or activated. You\'ll need to install and activate WooCommerce to use LHV hire-purchase gateway.', 'lhv-hire-purchase'));
        return;
    }

    // Check if we're running a supported version of WooCommerce
    if (!version_compare(WC()->version, '3.0.0', ">=")) {
        lhv_admin_notice('error', __('LHV hire-purchase gateway requires WooCommerce 3.0.0 or newer. Please update your WooCommerce installation and try again. Make sure you have a backup, though!', 'lhv-hire-purchase'));
        return;
    }

    // Composer autoloader
    require __DIR__ . '/vendor/autoload.php';
    require 'WC_Gateway_LHV_Hire_Purchase.php';

    // Run the plugin
    Codelight\LHV\GatewayManager::getInstance(__FILE__);
}
// Run the plugin after all plugins are loaded
add_action('plugins_loaded', 'lhv_hire_purchase_init');


/**
 * Display a notice in WP admin
 *
 * @param $type
 * @param $message
 * @param bool $displayOnSettingsPage
 */
function lhv_admin_notice($type, $message)
{
    // This filter allows hiding all admin notices
    if (!apply_filters('lhv/hire-purchase/show-admin-notices', true, $_REQUEST)) {
        return;
    }

    add_action('admin_notices', function () use ($type, $message) {
        include('templates/admin-notice.php');
    });
}
