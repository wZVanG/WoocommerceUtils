<?php

if (!defined('ABSPATH')) exit;

// Función para desactivar la sincronización de webhooks si está habilitado

add_action('init', function(){
	add_filter('woocommerce_webhook_deliver_async', '__return_false');
});
