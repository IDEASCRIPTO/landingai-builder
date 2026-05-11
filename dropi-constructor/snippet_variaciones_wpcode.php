<?php
/**
 * ENDPOINT: Leer variaciones de producto
 * ─────────────────────────────────────────────────────────────────
 * Agrega este código al snippet existente en WPCode (snippet #74)
 * O crea un snippet nuevo llamado "Endpoint variaciones producto"
 *
 * Endpoint resultante:
 *   GET https://tutienda.com/wp-json/mi-tienda/v1/producto/{id}
 *
 * CORS habilitado: permite que el constructor HTML (abierto localmente
 * en tu computadora) consulte este endpoint directamente.
 * ─────────────────────────────────────────────────────────────────
 */

// ── Habilitar CORS para el endpoint de producto ──────────────────
add_filter('rest_pre_serve_request', function($served, $result, $request) {
    if (strpos($request->get_route(), '/mi-tienda/v1/producto') !== false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    return $served;
}, 10, 3);

// ── Responder a preflight OPTIONS ────────────────────────────────
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/mi-tienda/v1/producto') !== false) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('HTTP/1.1 200 OK');
            exit();
        }
    }
});

// ── Registrar el endpoint ─────────────────────────────────────────
add_action('rest_api_init', function() {
    register_rest_route('mi-tienda/v1', '/producto/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_producto_variaciones',
        'permission_callback' => '__return_true',
        'args'                => array(
            'id' => array(
                'validate_callback' => function($v) { return is_numeric($v); }
            )
        ),
    ));
});

function get_producto_variaciones(WP_REST_Request $request) {
    $product_id = intval($request['id']);
    $product    = wc_get_product($product_id);

    if (!$product) {
        return new WP_Error('not_found', 'Producto no encontrado', array('status' => 404));
    }

    $result = array(
        'id'         => $product_id,
        'name'       => $product->get_name(),
        'type'       => $product->get_type(),
        'price'      => $product->get_price(),
        'attributes' => array(),
        'variations' => array(),
    );

    if ($product->get_type() === 'variable') {

        // Atributos del producto con sus valores posibles
        foreach ($product->get_attributes() as $attr_key => $attr_obj) {
            $values = $attr_obj->get_terms()
                ? array_map(fn($t) => $t->name, $attr_obj->get_terms())
                : $attr_obj->get_options();

            $result['attributes'][] = array(
                'key'    => $attr_key,
                'label'  => wc_attribute_label($attr_key),
                'values' => array_values($values),
            );
        }

        // Todas las variaciones con sus atributos e IDs reales
        foreach ($product->get_children() as $var_id) {
            $variation = wc_get_product($var_id);
            if (!$variation) continue;

            $attrs = array();
            foreach ($variation->get_variation_attributes() as $k => $v) {
                $clean_key      = str_replace('attribute_', '', $k);
                $label          = wc_attribute_label($clean_key);
                $attrs[$label]  = $v ?: '(Cualquiera)';
            }

            $result['variations'][] = array(
                'id'         => $var_id,
                'price'      => $variation->get_price(),
                'attributes' => $attrs,
                'in_stock'   => $variation->is_in_stock(),
            );
        }

    } else {
        // Producto simple — sin variaciones
        $result['variations'][] = array(
            'id'         => 0,
            'price'      => $product->get_price(),
            'attributes' => array(),
            'in_stock'   => $product->is_in_stock(),
        );
    }

    return rest_ensure_response($result);
}
