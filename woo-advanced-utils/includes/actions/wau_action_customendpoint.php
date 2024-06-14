<?php

if (!defined('ABSPATH')) exit;

// Función para registrar el endpoint personalizado si está habilitado

add_action('rest_api_init', function(){
	register_rest_route('wc/v3', '/products_advanced', array(
		'methods' => 'GET',
		'callback' => 'custom_get_advanced_products'
	));
});


function custom_get_advanced_products($request) {
    global $wpdb;

    // Obtén los parámetros de la solicitud
    $parameters = $request->get_params();

    // Verifica si 'skus' está presente y es una cadena
    if (!isset($parameters['skus']) || !is_string($parameters['skus'])) {
        return new WP_Error('invalid_skus', 'No has especificado los SKUs', array('status' => 400));
    }

    // Divide la cadena en un array usando la coma como delimitador
    $skus = explode(',', $parameters['skus']);

    // Verifica si la división resultó en un array no vacío
    if (!is_array($skus) || empty($skus)) {
        return new WP_Error('invalid_skus', 'Invalid SKUs parameter', array('status' => 400));
    }

    // Procede con la consulta a la base de datos
    $placeholders = implode(',', array_fill(0, count($skus), '%s'));
    $query = $wpdb->prepare("
        SELECT p.ID, p.post_title, pm.meta_value as sku
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE pm.meta_key = '_sku' AND pm.meta_value IN ($placeholders)
        AND p.post_type = 'product' AND p.post_status = 'publish'
    ", $skus);

    $results = $wpdb->get_results($query);

    if (empty($results)) {
        return new WP_REST_Response([], 200);
    }

    $products = [];
    foreach ($results as $result) {
        $products[] = [
            'id' => $result->ID,
            'name' => $result->post_title,
            'sku' => $result->sku,
        ];
    }

    return new WP_REST_Response($products, 200);
}
