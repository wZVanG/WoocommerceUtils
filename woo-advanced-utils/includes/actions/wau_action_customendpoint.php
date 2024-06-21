<?php

if (!defined('ABSPATH')) exit;

// Función para registrar el endpoint personalizado si está habilitado

add_action('rest_api_init', function(){
	register_rest_route('wc/v3', '/products_advanced', array(
		'methods' => 'GET',
		'callback' => 'custom_get_advanced_products'
	));

	// Api para exportar productos sin foto
    register_rest_route('wau/v1', '/sinfoto', array(
        'methods' => 'GET',
        'callback' => 'export_products_csv',
        'permission_callback' => function () {
			return true;
            /*if (!current_user_can('manage_woocommerce')) {
                return new WP_Error('rest_forbidden', 'Usuario no tiene permisos suficientes', array('status' => 403));
            }
            return true;*/
        }
    ));

	register_rest_route('wc/v3', '/categories_by_local_code', array(
        'methods' => 'GET',
        'callback' => 'custom_get_categories_by_local_code'
    ));

	// Registrar los campos para la creación/actualización de productos, agregar el campo 'cod_cat_local', 'category_name'
	register_rest_field('product', 'cod_cat_local', array(
		'get_callback' => null,
		'update_callback' => 'update_product_cod_cat_local',
		'schema' => array(
			'description' => __('Código de categoría local'),
			'type' => 'string',
			'context' => array('edit')
		)
	));

	register_rest_field('product', 'category_name', array(
		'get_callback' => null,
		'update_callback' => 'update_product_category_name',
		'schema' => array(
			'description' => __('Nombre de la categoría'),
			'type' => 'string',
			'context' => array('edit')
		)
	));

});


function custom_get_advanced_products($request) {
    global $wpdb;

    // Obtén los parámetros de la solicitud
    $parameters = $request->get_params();

    // Verifica si 'skus' está presente y es una cadena
    if (!isset($parameters['skus']) || !is_string($parameters['skus'])) return new WP_Error('invalid_skus', 'No has especificado los SKUs', array('status' => 400));

    // Divide la cadena en un array usando la coma como delimitador
    $skus = explode(',', $parameters['skus']);

    // Verifica si la división resultó en un array no vacío
    if (!is_array($skus) || empty($skus)) return new WP_Error('invalid_skus', 'Invalid SKUs parameter', array('status' => 400));

    // Procede con la consulta a la base de datos
    $placeholders = implode(',', array_fill(0, count($skus), '%s'));

	// Query to get the products (ID, name, sku, stock) with the given SKUs
	// Obtener también low_stock_amount, short_description

	$query = $wpdb->prepare("
       	SELECT 
			p.ID, 
			p.post_title, 
			sku.meta_value as sku, 
			stock.meta_value as stock_quantity, 
			price.meta_value as regular_price,
			ean.meta_value as ean,
			low_stock.meta_value as low_stock_amount,
			p.post_excerpt as short_description

        FROM wp0h_posts p
        INNER JOIN wp0h_postmeta sku ON p.ID = sku.post_id
        INNER JOIN wp0h_postmeta stock ON p.ID = stock.post_id
        INNER JOIN wp0h_postmeta price ON p.ID = price.post_id

        LEFT JOIN wp0h_postmeta ean ON p.ID = ean.post_id AND ean.meta_key = '_alg_ean'
		LEFT JOIN wp0h_postmeta low_stock ON p.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'

        WHERE sku.meta_key = '_sku' 
        AND sku.meta_value IN ($placeholders)
        AND stock.meta_key = '_stock'
        AND price.meta_key = '_regular_price'
        AND p.post_type = 'product'
	", $skus);

    $results = $wpdb->get_results($query);

    if (empty($results)) return new WP_REST_Response([], 200);

    $items = [];
    foreach ($results as $result) {
		$items[] = [
            'id' => (int) $result->ID,
            'name' => $result->post_title,
            'sku' => $result->sku,
            'stock_quantity' => round((float) $result->stock_quantity, 4),
            'regular_price' => round((float) $result->regular_price, 4),
            'ean' => $result->ean ? $result->ean : null,
			'low_stock_amount' => $result->low_stock_amount ? round((float) $result->low_stock_amount, 4) : null,
			'short_description' => is_string($result->short_description) ? trim($result->short_description) : null
        ];
    }

    return new WP_REST_Response($items, 200);
}

function export_products_csv() {
    global $wpdb;

	$query = "
    SELECT 
        IF(pm_thumbnail.meta_value IS NULL, 'NO', 'SI') AS tiene_foto,
        pm_sku.meta_value AS sku, 
        p.post_title AS producto, 
        p.ID, 
        CONCAT('" . site_url('/wp-admin/post.php?post=') . "', p.ID, '&action=edit') AS link_editar,
        IFNULL(
            CONCAT('" . site_url('/wp-content/uploads/') . "', 
            (SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = pm_thumbnail.meta_value AND meta_key = '_wp_attached_file')), 
            ''
        ) AS url_imagen
    FROM 
        {$wpdb->prefix}posts p 
    LEFT JOIN 
        {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id' 
    LEFT JOIN 
        {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku' 
    WHERE 
        p.post_type = 'product' 
        AND pm_sku.meta_value IS NOT NULL 
    ORDER BY 
        pm_sku.meta_value ASC 
    LIMIT 15000
	";

    $results = $wpdb->get_results($query, ARRAY_A);

    if (empty($results)) {
        return new WP_Error('no_products', 'No products found', array('status' => 404));
    }

    $csv_output = "Tiene Foto,SKU,Producto,ID,Link Editar,Imagen\n";

    foreach ($results as $row) {
        $csv_output .= '"' . implode('","', $row) . '"' . "\n";
    }

    // Obtener la fecha actual en el formato YYYY-MM-DD
    $fecha_actual = date('Y-m-d');

    // Forzar la descarga del archivo CSV con nombre dinámico
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=sinfoto_' . $fecha_actual . '.csv');
    echo $csv_output;
    exit;
}

function custom_get_categories_by_local_code($request) {
    global $wpdb;

    // Obtén los parámetros de la solicitud
    $parameters = $request->get_params();

    // Verifica si 'cod_cat_locals' está presente y es una cadena
    if (!isset($parameters['cod_cat_locals']) || !is_string($parameters['cod_cat_locals'])) {
        return new WP_Error('invalid_cod_cat_locals', 'No has especificado los códigos de categoría locales', array('status' => 400));
    }

    // Divide la cadena en un array usando la coma como delimitador
    $cod_cat_locals = explode(',', $parameters['cod_cat_locals']);

    // Verifica si la división resultó en un array no vacío
    if (!is_array($cod_cat_locals) || empty($cod_cat_locals)) {
        return new WP_Error('invalid_cod_cat_locals', 'Parámetro de códigos de categoría locales no válido', array('status' => 400));
    }

    // Procede con la consulta a la base de datos
    $placeholders = implode(',', array_fill(0, count($cod_cat_locals), '%s'));

    // Query para obtener las categorías con los cod_cat_locals dados
    $query = $wpdb->prepare("
        SELECT 
            t.term_id, 
            t.name, 
            t.slug, 
            tm.meta_value AS cod_cat_local
        FROM wp0h_terms t
        INNER JOIN wp0h_term_taxonomy tt ON t.term_id = tt.term_id
        LEFT JOIN wp0h_termmeta tm ON t.term_id = tm.term_id AND tm.meta_key = 'cod_cat_local'
        WHERE tt.taxonomy = 'product_cat'
        AND tm.meta_value IN ($placeholders)
    ", $cod_cat_locals);

    $results = $wpdb->get_results($query);

    if (empty($results)) {
        return new WP_REST_Response([], 200);
    }

    $items = [];
    foreach ($results as $result) {
        $items[] = [
            'term_id' => (int) $result->term_id,  // ID de categoría
            'name' => $result->name,
            'slug' => $result->slug,
            'cod_cat_local' => $result->cod_cat_local
        ];
    }

    return new WP_REST_Response($items, 200);
}

function update_product_cod_cat_local($value, $object, $field_name) {
    if (!empty($value)) {
        global $wpdb;

        // Buscar la categoría por cod_cat_local
        $term_id = $wpdb->get_var($wpdb->prepare("
            SELECT t.term_id 
            FROM wp0h_terms t
            INNER JOIN wp0h_term_taxonomy tt ON t.term_id = tt.term_id
            LEFT JOIN wp0h_termmeta tm ON t.term_id = tm.term_id AND tm.meta_key = 'cod_cat_local'
            WHERE tt.taxonomy = 'product_cat'
            AND tm.meta_value = %s
        ", $value));

        // Si la categoría existe, asignarla al producto
        if ($term_id) {
            wp_set_object_terms($object->get_id(), (int) $term_id, 'product_cat', false);
        } elseif (isset($_POST['category_name'])) {
            // Si no existe, y se proporcionó un nombre de categoría, crear una nueva categoría
            $category_name = sanitize_text_field($_POST['category_name']);

            // Generar un slug basado en el nombre de la categoría
            $slug = sanitize_title($category_name);

            // Crear la nueva categoría con el slug
            $new_term = wp_insert_term($category_name, 'product_cat', array('slug' => $slug));
            if (!is_wp_error($new_term)) {
                $term_id = $new_term['term_id'];

                // Guardar el cod_cat_local para la nueva categoría
                update_term_meta($term_id, 'cod_cat_local', $value);

                // Asignar la nueva categoría al producto
                wp_set_object_terms($object->get_id(), (int) $term_id, 'product_cat', false);
            } else {
                // Manejar el error si falla la creación de la categoría
                return new WP_Error('category_creation_failed', 'Error al crear la categoría: ' . $new_term->get_error_message(), array('status' => 500));
            }
        }
    }
}

function update_product_category_name($value, $object, $field_name) {
    // Esta función se usa solo para registrar el campo, no necesita implementación adicional
}
