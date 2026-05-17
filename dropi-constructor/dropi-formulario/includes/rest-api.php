<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Dropi_Rest_Api {

    public function __construct() {
        // AJAX handlers — no CORS preflight because FormData is a "simple" request
        add_action( 'wp_ajax_dropi_save_config',        array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_nopriv_dropi_save_config', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_dropi_get_config',         array( $this, 'ajax_get' ) );
        add_action( 'wp_ajax_nopriv_dropi_get_config',  array( $this, 'ajax_get' ) );
    }

    private function cors() {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );
    }

    /**
     * Fixes garbled unicode sequences caused by old saves stripping backslashes.
     * e.g. "u00f3" → "ó", "u00ed" → "í", "ud83cudf89" → "🎉"
     */
    public static function fix_garbled_unicode( $data ) {
        if ( is_string( $data ) ) {
            // Normalize literal \n / \r\n sequences that were stored without proper JSON escaping
            $data = str_replace( array( '\r\n', '\r', '\n' ), array( "\r\n", "\r", "\n" ), $data );
            // Fix surrogate pairs first (emoji): ud83c+udf89 → 🎉
            $data = preg_replace_callback(
                '/ud([89ab][0-9a-f]{2})ud([c-f][0-9a-f]{2})/i',
                function ( $m ) {
                    $hi = hexdec( 'd' . $m[1] );
                    $lo = hexdec( 'd' . $m[2] );
                    if ( $hi >= 0xD800 && $hi <= 0xDBFF && $lo >= 0xDC00 && $lo <= 0xDFFF ) {
                        $cp = 0x10000 + ( $hi - 0xD800 ) * 0x400 + ( $lo - 0xDC00 );
                        $hi2 = 0xD800 + ( ( $cp - 0x10000 ) >> 10 );
                        $lo2 = 0xDC00 + ( ( $cp - 0x10000 ) & 0x3FF );
                        return json_decode( '"\\u' . sprintf( '%04x', $hi2 ) . '\\u' . sprintf( '%04x', $lo2 ) . '"' );
                    }
                    return $m[0];
                },
                $data
            );
            // Fix BMP escapes: u00ed → í, u00f3 → ó, u2014 → —, etc.
            $data = preg_replace_callback(
                '/u([0-9a-f]{4})/i',
                function ( $m ) {
                    $cp = hexdec( $m[1] );
                    if ( $cp >= 0x80 && $cp < 0xD800 ) {
                        return json_decode( '"\\u' . $m[1] . '"' );
                    }
                    return $m[0];
                },
                $data
            );
            return $data;
        }
        if ( is_array( $data ) ) {
            return array_map( array( 'Dropi_Rest_Api', 'fix_garbled_unicode' ), $data );
        }
        return $data;
    }

    public function ajax_save() {
        $this->cors();

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        $stored  = Dropi_Settings::get( 'dropi_api_key', '' );
        if ( ! $api_key || ! $stored || ! hash_equals( $stored, $api_key ) ) {
            wp_send_json_error( array( 'message' => 'API Key incorrecta. Ve a Ajustes → Dropi Formulario para copiarla.' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'product_id requerido.' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Producto ID ' . $product_id . ' no encontrado en WooCommerce.' ) );
        }

        $config_raw = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
        $config     = json_decode( $config_raw, true );
        if ( ! is_array( $config ) ) {
            wp_send_json_error( array( 'message' => 'Config JSON inválido.' ) );
        }

        // Auto-fix any garbled unicode from old saves before storing
        $config = self::fix_garbled_unicode( $config );

        update_post_meta( $product_id, '_dropi_config', wp_json_encode( $config ) );
        wp_send_json_success( array(
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
        ) );
    }

    public function ajax_get() {
        $this->cors();

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'product_id requerido.' ) );
        }

        $raw = get_post_meta( $product_id, '_dropi_config', true );
        if ( ! $raw ) {
            wp_send_json_error( array( 'message' => 'no_config', 'code' => 'no_config' ) );
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => 'Configuración inválida.' ) );
        }

        // Fix garbled unicode before returning to constructor
        $data = self::fix_garbled_unicode( $data );

        wp_send_json_success( $data );
    }
}

