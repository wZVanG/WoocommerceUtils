<?php

/**
 * Woo Advanced Utils
 *
 *
 * @link              https://github.com/wZVanG/WoocommerceUtils
 * @package           Woo Advanced Utils
 * @author            Walter Chapilliquen
 * @wordpress-plugin
 * Plugin Name:       Woo Advanced Utils
 * Plugin URI:        https://github.com/wZVanG/WoocommerceUtils
 * Description:       Plugin para extender funcionalidades de WooCommerce
 * Version:           1.0.0
 * Author:            Walter Chapilliquen
 * Author URI:        https://github.com/wzVanG/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.5.4
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) exit;


define('WAU_PLUGIN_DIR', dirname(__FILE__));
define('WAU_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WAU_CONFIG_MENUNAME', 'Chang Sistema Local');
define('WAU_CONFIG_SUPPORTURL', 'https://wai.technology/');

$wau_settings = [
	"wau_webhookasync_checkbox" => [
		"type" => "checkbox",
		"file" => "wau_action_webhookasync"
	], 
	"wau_customendpoint_checkbox" => [
		"type" => "checkbox",
		"file" => "wau_action_customendpoint"
	], 
	"wau_format_checkbox" => [
		"type" => "select",
		"file" => null
	]
];

require WAU_PLUGIN_DIR . "/includes/wau_admin_setup.php";
require WAU_PLUGIN_DIR . "/includes/wau_admin_page.php";
require WAU_PLUGIN_DIR . "/includes/wau_start.php";