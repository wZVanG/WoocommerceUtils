<?php

if (!defined('ABSPATH')) exit;

foreach($wau_settings AS $setting_id => $value){
	$file_action = isset($value['file']) ? $value['file'] : null;
	if ($value["type"] === "checkbox" && get_option($setting_id) == "on") require_once WAU_PLUGIN_DIR . "/includes/actions/" . $file_action . ".php";
}