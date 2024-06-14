<?php

if (!defined('ABSPATH')) exit;

function wau_plugin_row_meta( $links, $file ){
	
	if (WAU_PLUGIN_BASENAME === $file) $links[] = '<a target="_blank" href="' . esc_url( WAU_CONFIG_SUPPORTURL ) . '">Soporte</a>';
	return $links;
}

function wau_add_plugin_page_settings_link($links){

	array_unshift($links, '<a href="' . admin_url('admin.php?page=wau_settings&tab=settings') . '">Ajustes</a>');
    return $links;

}

function wau_register_settings(){

	global $wau_settings;

	foreach($wau_settings as $setting_id => $value){
		register_setting('wau_settings_group', $setting_id);
	}

}

// Agrega links en plugins
add_filter( 'plugin_row_meta', 'wau_plugin_row_meta', 10, 2 );

// Agrega link de settings en plugins
add_filter('plugin_action_links_' . WAU_PLUGIN_BASENAME, 'wau_add_plugin_page_settings_link');

//Agregar submenú en WooCommerce al final de los submenús
add_action('admin_menu', function(){
	add_submenu_page('woocommerce', 'Woo Advanced Utils', WAU_CONFIG_MENUNAME, 'manage_woocommerce', 'wau_settings', 'wau_submenu_settings_callback');
    add_action('admin_init', 'wau_register_settings');
});
