<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Dropi_Shortcode {

    public function __construct() {
        add_shortcode( 'dropi_form', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets() {
        wp_register_style( 'dropi-form', DROPI_URL . 'assets/css/form.css', array(), DROPI_VERSION );
        wp_register_script( 'dropi-form', DROPI_URL . 'assets/js/form.js', array(), DROPI_VERSION, true );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'dropi_form' );
        $product_id = intval( $atts['id'] );

        // Allow ?id=116 in the URL when shortcode has no id (single-page mode)
        if ( ! $product_id && isset( $_GET['id'] ) ) {
            $product_id = intval( $_GET['id'] );
        }

        if ( ! $product_id ) {
            return '<p style="color:red">Dropi Form: falta el ID del producto. Usa [dropi_form id="116"] o abre la página con ?id=116 en la URL.</p>';
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '<p style="color:red">Dropi Form: producto ID ' . $product_id . ' no encontrado.</p>';
        }

        // Load per-product config saved from constructor
        $config = null;
        $raw = get_post_meta( $product_id, '_dropi_config', true );
        if ( $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                $config = Dropi_Rest_Api::fix_garbled_unicode( $decoded );
            }
        }

        // Build settings: config overrides globals
        $ui = $config ? ( $config['ui'] ?? array() ) : array();
        $settings = array(
            'color_boton'  => $ui['btnColor']    ?? Dropi_Settings::get( 'dropi_color_boton',  '#1D9E75' ),
            'color_fondo'  => $ui['formBg']      ?? Dropi_Settings::get( 'dropi_color_fondo',  '#ffffff' ),
            'bg_color'     => $ui['bgColor']      ?? '#f0f4f8',
            'badge_color'  => $ui['badgeColor']   ?? '#f59e0b',
            'text_color'   => $ui['textColor']    ?? '#374151',
            // When per-product config exists, bypass global WP settings to avoid interference
            'texto_boton'  => $config ? ( $ui['btnText']  ?? '🛵 REALIZAR PEDIDO — Pago al recibir' )                 : Dropi_Settings::get( 'dropi_texto_boton', '🛵 REALIZAR PEDIDO — Pago al recibir' ),
            'msg_exito'    => $config ? ( $ui['msgExito'] ?? '✅ ¡Pedido realizado! Te contactaremos pronto. 🎉' )      : Dropi_Settings::get( 'dropi_msg_exito',   '✅ ¡Pedido realizado! Te contactaremos pronto. 🎉' ),
            'msg_error'    => $config ? ( $ui['msgError'] ?? '❌ Error al procesar. Intenta de nuevo.' )                : Dropi_Settings::get( 'dropi_msg_error',   '❌ Error al procesar. Intenta de nuevo.' ),
            'badge'        => $ui['badge']        ?? '🚚 Pago al recibir — Envío gratis',
            'subtitle'     => $ui['subtitle']     ?? 'Elige tu pack y llena tus datos. ¡Te contactamos!',
            'product_name' => $ui['productName']  ?? $product->get_name(),
            'form_style'   => $config['formStyle'] ?? 'clasico',
            // optional fields fallback for products without config
            'campo_calle2' => Dropi_Settings::get( 'dropi_campo_calle2', '0' ),
            'campo_barrio' => Dropi_Settings::get( 'dropi_campo_barrio', '0' ),
            'campo_numero' => Dropi_Settings::get( 'dropi_campo_numero', '0' ),
            'campo_cedula' => Dropi_Settings::get( 'dropi_campo_cedula', '0' ),
            'campo_email'  => Dropi_Settings::get( 'dropi_campo_email',  '0' ),
            'campo_notas'  => Dropi_Settings::get( 'dropi_campo_notas',  '0' ),
        );

        // Packs: from config or WC variations
        if ( $config && ! empty( $config['packs'] ) ) {
            $packs  = $config['packs'];
            $colors = $config['colors'] ?? array();
            $sizes  = $config['sizes']  ?? array();
            $fields = $config['fields'] ?? null;
        } else {
            $packs  = $this->get_variations( $product );
            $colors = array();
            $sizes  = array();
            $fields = null;
        }

        // Province/city data: provinces from config if available, cities always from ciudades.php
        $ciudades_map = dropi_get_ciudades();
        if ( $config && ! empty( $config['provincias'] ) ) {
            $provincias_tpl = array();
            foreach ( $config['provincias'] as $p ) {
                $provincias_tpl[] = array( 'code' => $p['code'], 'name' => $p['name'] );
            }
        } else {
            $provincias_tpl = array();
            foreach ( dropi_get_provincias() as $code => $name ) {
                $provincias_tpl[] = array( 'code' => $code, 'name' => $name );
            }
        }

        // Pixel config: per-product if available, else global pixel IDs + toggles
        $global_meta_id   = Dropi_Settings::get( 'dropi_meta_pixel_id',   '' );
        $global_tiktok_id = Dropi_Settings::get( 'dropi_tiktok_pixel_id', '' );
        $global_ga4_id    = Dropi_Settings::get( 'dropi_google_ga4_id',   '' );
        $pixel_cfg = array(
            'meta'   => ( $ui['pixelMeta']   ?? '' ) ?: $global_meta_id,
            'tiktok' => ( $ui['pixelTiktok'] ?? '' ) ?: $global_tiktok_id,
            'google' => ( $ui['pixelGoogle'] ?? '' ) ?: $global_ga4_id,
            'meta_on'   => Dropi_Settings::get( 'dropi_pixel_meta',   '1' ),
            'tiktok_on' => Dropi_Settings::get( 'dropi_pixel_tiktok', '1' ),
            'google_on' => Dropi_Settings::get( 'dropi_pixel_google', '1' ),
        );

        static $instance = 0;
        $instance++;
        $form_id = 'dropi-' . $product_id . '-' . $instance;

        wp_enqueue_style( 'dropi-form' );
        wp_enqueue_script( 'dropi-form' );
        wp_localize_script( 'dropi-form', 'dropiConfig', array(
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'dropi_nonce' ),
            'ciudades' => $ciudades_map,
            'pixels'   => $pixel_cfg,
            'config'   => $config,
        ) );

        ob_start();
        include DROPI_DIR . 'templates/form.php';
        return ob_get_clean();
    }

    private function get_variations( $product ) {
        $out = array();
        if ( $product->get_type() === 'variable' ) {
            foreach ( $product->get_children() as $vid ) {
                $v = wc_get_product( $vid );
                if ( ! $v || ! $v->is_in_stock() ) continue;
                $attrs = array();
                foreach ( $v->get_variation_attributes() as $k => $val ) {
                    $label = wc_attribute_label( str_replace( 'attribute_', '', $k ) );
                    $attrs[ $label ] = $val;
                }
                $out[] = array(
                    'id'         => $vid,
                    'price'      => $v->get_price(),
                    'price_html' => '$' . number_format( floatval( $v->get_price() ), 2 ),
                    'attributes' => $attrs,
                );
            }
        } else {
            $out[] = array(
                'id'         => 0,
                'price'      => $product->get_price(),
                'price_html' => '$' . number_format( floatval( $product->get_price() ), 2 ),
                'attributes' => array(),
            );
        }
        return $out;
    }
}

