<?php

if (!defined('ABSPATH')) exit;

// Función para registrar el endpoint personalizado si está habilitado

add_action('rest_api_init', function(){
	register_rest_route('wc/v3', '/products_advanced', array(
		'methods' => 'GET',
		'callback' => 'custom_get_advanced_products',
		'permission_callback' => '__return_true'
	));

	register_rest_route('wc/v3', '/upsert_categories', array(
		'methods' => 'POST',
		'callback' => 'custom_upsert_categories',
		'permission_callback' => '__return_true'
	));

	// Api para exportar productos sin foto
    register_rest_route('wau/v1', '/sinfoto', array(
        'methods' => 'GET',
        'callback' => 'export_products_csv',
		'permission_callback' => '__return_true'
        // 'permission_callback' => function () {
        //     if (!current_user_can('manage_woocommerce')) {
        //         return new WP_Error('rest_forbidden', 'Usuario no tiene permisos suficientes', array('status' => 403));
        //     }
        //     return true;
        // }
    ));
	

	register_rest_route('wc/v3', '/categories_by_local_code', array(
        'methods' => 'GET',
        'callback' => 'custom_get_categories_by_local_code',
		'permission_callback' => '__return_true'
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

    // Query to get the products (ID, name, sku, stock, etc.) with the given SKUs
    $query = $wpdb->prepare("
        SELECT 
            p.ID, 
            p.post_title, 
            sku.meta_value as sku, 
            stock.meta_value as stock_quantity, 
            price.meta_value as regular_price,
            ean.meta_value as ean,
            low_stock.meta_value as low_stock_amount,
            p.post_excerpt as short_description,
            (
                SELECT JSON_ARRAYAGG(t.slug)
                FROM wp0h_term_relationships tr
                INNER JOIN wp0h_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN wp0h_terms t ON tt.term_id = t.term_id
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_visibility'
            ) as catalog_visibility,
            (
                SELECT JSON_ARRAYAGG(JSON_OBJECT('id', t.term_id, 'name', t.name, 'cod_cat_local', IFNULL(tm.meta_value, null)))
                FROM wp0h_term_relationships tr
                INNER JOIN wp0h_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN wp0h_terms t ON tt.term_id = t.term_id
                LEFT JOIN wp0h_termmeta tm ON t.term_id = tm.term_id AND tm.meta_key = 'cod_cat_local'
                WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat'
            ) as product_categories
        FROM wp0h_posts p
        INNER JOIN wp0h_postmeta sku ON p.ID = sku.post_id AND sku.meta_key = '_sku'
        INNER JOIN wp0h_postmeta stock ON p.ID = stock.post_id AND stock.meta_key = '_stock'
        INNER JOIN wp0h_postmeta price ON p.ID = price.post_id AND price.meta_key = '_regular_price'
        LEFT JOIN wp0h_postmeta ean ON p.ID = ean.post_id AND ean.meta_key = '_alg_ean'
        LEFT JOIN wp0h_postmeta low_stock ON p.ID = low_stock.post_id AND low_stock.meta_key = '_low_stock_amount'
        WHERE sku.meta_value IN ($placeholders)
        AND p.post_type = 'product'
    ", $skus);

    $results = $wpdb->get_results($query);

    if (empty($results)) return new WP_REST_Response([], 200);

    $items = [];
    foreach ($results as $result) {
        $catalog_visibility_array = !empty($result->catalog_visibility) ? json_decode($result->catalog_visibility, true) : [];
        $categories_array = !empty($result->product_categories) ? json_decode($result->product_categories, true) : [];

        $items[] = [
            'id' => (int) $result->ID,
            'name' => $result->post_title,
            'sku' => $result->sku,
            'stock_quantity' => round((float) $result->stock_quantity, 4),
            'regular_price' => round((float) $result->regular_price, 4),
            'ean' => $result->ean ? $result->ean : null,
            'low_stock_amount' => $result->low_stock_amount ? round((float) $result->low_stock_amount, 4) : null,
            'short_description' => is_string($result->short_description) ? trim($result->short_description) : null,
            'catalog_visibility' => $catalog_visibility_array,
            'categories' => $categories_array
        ];
    }

    return new WP_REST_Response($items, 200);
}


function custom_upsert_categories($request) {
    $parameters = $request->get_params();

    if (!isset($parameters['categories']) || !is_array($parameters['categories'])) {
        return new WP_Error('invalid_categories', 'No has especificado un array válido de categorías', array('status' => 400));
    }

	//Validar que las categorías tengan los campos requeridos
	foreach ($parameters['categories'] as $category){
		if (!isset($category['name']) || !is_string($category['name'])) {
			return new WP_Error('invalid_categories', 'Las categorías deben tener un nombre', array('status' => 400));
		}
		if (!isset($category['cod_cat_local']) || !is_numeric($category['cod_cat_local'])) {
			return new WP_Error('invalid_categories', 'Las categorías deben tener un código de categoría local', array('status' => 400));
		}
	}

    $categories = array_map(function($category) {
        return [
            'id' => !empty($category['id']) ? intval($category['id']) : null,
            'name' => sanitize_text_field($category['name']),
			'cod_cat_local' => !empty($category['cod_cat_local']) ? intval($category['cod_cat_local']) : null
        ];
    }, $parameters['categories']);

    $results = [];

    foreach ($categories as $category) {
        $id = $category['id'];
        $name = $category['name'];
		$cod_cat_local = $category['cod_cat_local'];
        $normalized_name = wau_normalize_text($name);

        if ($id) {
            // Intentar obtener la categoría por ID
            $existing_term_by_id = get_term_by('id', $id, 'product_cat');

            if ($existing_term_by_id && !is_wp_error($existing_term_by_id)) {
                // Si la categoría existe, actualizarla
                $term = wp_update_term($id, 'product_cat', ['name' => $name, 'slug' => sanitize_title($normalized_name)]);
                
                if (is_wp_error($term)) {
                    $results[] = [
                        'name' => $name,
                        'id' => $id,
						'cod_cat_local' => $cod_cat_local,
                        'error' => $term->get_error_message(),
                        'status' => 'error'
                    ];
                } else {
					
					//Guardar el cod_cat_local
					update_term_meta($id, 'cod_cat_local', $cod_cat_local);

                    $results[] = [
                        'name' => $name,
                        'id' => $id,
						'cod_cat_local' => $cod_cat_local,
                        'status' => 'updated'
                    ];
                }
            } else {
                // Si la categoría no existe, crear una nueva con el nombre proporcionado
                $new_term = wp_insert_term($name, 'product_cat', array('slug' => sanitize_title($normalized_name)));
                
                if (is_wp_error($new_term)) {
                    $results[] = [
                        'name' => $name,
						'cod_cat_local' => $cod_cat_local,
                        'error' => $new_term->get_error_message(),
                        'status' => 'error'
                    ];
                } else {

					//Guardar el cod_cat_local
					update_term_meta($new_term['term_id'], 'cod_cat_local', $cod_cat_local);

                    $results[] = [
                        'name' => $name,
                        'id' => $new_term['term_id'],
						'cod_cat_local' => $cod_cat_local,
                        'status' => 'created'
                    ];
                }
            }
        } else {
            // Si no se proporciona un ID, buscar o crear la categoría
            $existing_term = get_term_by('slug', sanitize_title($normalized_name), 'product_cat');

            if ($existing_term && !is_wp_error($existing_term)) {

				//Guardar el cod_cat_local
				update_term_meta($existing_term->term_id, 'cod_cat_local', $cod_cat_local);

                $results[] = [
                    'name' => $name,
                    'id' => $existing_term->term_id,
					'cod_cat_local' => $cod_cat_local,
                    'status' => 'exists'
                ];
            } else {
                $new_term = wp_insert_term($name, 'product_cat', array('slug' => sanitize_title($normalized_name)));

                if (is_wp_error($new_term)) {
                    $results[] = [
                        'name' => $name,
                        'error' => $new_term->get_error_message(),
						'cod_cat_local' => $cod_cat_local,
                        'status' => 'error'
                    ];
                } else {

					//Guardar el cod_cat_local
					update_term_meta($new_term['term_id'], 'cod_cat_local', $cod_cat_local);

                    $results[] = [
                        'name' => $name,
                        'id' => $new_term['term_id'],
						'cod_cat_local' => $cod_cat_local,
                        'status' => 'created'
                    ];
                }
            }
        }
    }

    return new WP_REST_Response($results, 200);
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
		AND pm_thumbnail.meta_value IS NULL
    ORDER BY 
        pm_sku.meta_value ASC 
    LIMIT 15000
	";

    $results = $wpdb->get_results($query, ARRAY_A);

    if (empty($results)) {
        return new WP_Error('no_products', 'No products found', array('status' => 404));
    }

    $csv_output = "Tiene Foto,SKU,Producto,ID,Link Editar,Imagen\n";

	//Obtener productos del archivo test/productos_con_stock_01.csv. El csv solo debe exportar los productos de este archivo.
	$csv_file = fopen(WAU_PLUGIN_DIR . "/test/productos_con_stock_01.csv", 'r');
	$skus = [];
	while (($row = fgetcsv($csv_file)) !== false) {
		if(isset($row[0])) $skus[] = trim((string) $row[0]);
	}
	fclose($csv_file);

    foreach ($results as $row) {
		if (!in_array((string) $row['sku'], $skus)) continue;
        $csv_output .= '"' . implode('","', $row) . '"' . "\n";
    }

    // Obtener la fecha actual en el formato YYYY-MM-DD
    $fecha_actual = date('Y-m-d');

    // Forzar la descarga del archivo CSV con nombre dinámico
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=sinfoto_y_constock_' . $fecha_actual . '.csv');
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
            t.term_id AS category_id, 
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
            'category_id' => (int) $result->category_id,  // ID de categoría
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

function wau_normalize_text($text) {
    $unwanted_array = array(
        'Á'=>'A', 'À'=>'A', 'Â'=>'A', 'Ä'=>'A', 'á'=>'a', 'à'=>'a', 'ä'=>'a', 'â'=>'a',
        'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'é'=>'e', 'è'=>'e', 'ë'=>'e', 'ê'=>'e',
        'Í'=>'I', 'Ì'=>'I', 'Ï'=>'I', 'Î'=>'I', 'í'=>'i', 'ì'=>'i', 'ï'=>'i', 'î'=>'i',
        'Ó'=>'O', 'Ò'=>'O', 'Ö'=>'O', 'Ô'=>'O', 'ó'=>'o', 'ò'=>'o', 'ö'=>'o', 'ô'=>'o',
        'Ú'=>'U', 'Ù'=>'U', 'Ü'=>'U', 'Û'=>'U', 'ú'=>'u', 'ù'=>'u', 'ü'=>'u', 'û'=>'u',
        'Ñ'=>'N', 'ñ'=>'n', 'Ç'=>'C', 'ç'=>'c'
    );
    return strtr($text, $unwanted_array);
}

function update_product_category_name($value, $object, $field_name) {
    // Esta función se usa solo para registrar el campo, no necesita implementación adicional
}

