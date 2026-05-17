<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Dropi_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_dropi_pedido',        array( $this, 'procesar' ) );
        add_action( 'wp_ajax_nopriv_dropi_pedido', array( $this, 'procesar' ) );
    }

    public function procesar() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dropi_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Error de seguridad. Recarga e intenta de nuevo.' ) );
        }

        $required = array( 'nombre', 'telefono', 'provincia', 'ciudad', 'direccion', 'product_id' );
        foreach ( $required as $f ) {
            if ( empty( $_POST[ $f ] ) ) {
                wp_send_json_error( array( 'message' => 'Completa todos los campos requeridos.' ) );
            }
        }

        $nombre      = sanitize_text_field( wp_unslash( $_POST['nombre'] ) );
        $apellido    = isset( $_POST['apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['apellido'] ) ) : '';
        $telefono    = sanitize_text_field( wp_unslash( $_POST['telefono'] ) );
        $provincia   = sanitize_text_field( wp_unslash( $_POST['provincia'] ) );
        $ciudad      = strtoupper( sanitize_text_field( wp_unslash( $_POST['ciudad'] ) ) );
        $direccion   = sanitize_text_field( wp_unslash( $_POST['direccion'] ) );
        $product_id  = intval( $_POST['product_id'] );
        $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
        $calle2  = isset( $_POST['calle2'] )  ? sanitize_text_field( wp_unslash( $_POST['calle2'] ) )    : '';
        $barrio  = isset( $_POST['barrio'] )  ? sanitize_text_field( wp_unslash( $_POST['barrio'] ) )    : '';
        $numero  = isset( $_POST['numero'] )  ? sanitize_text_field( wp_unslash( $_POST['numero'] ) )    : '';
        $cedula  = isset( $_POST['cedula'] )  ? sanitize_text_field( wp_unslash( $_POST['cedula'] ) )    : '';
        $email   = isset( $_POST['email'] )   ? sanitize_email( wp_unslash( $_POST['email'] ) )          : '';
        $notas   = isset( $_POST['notas'] )   ? sanitize_textarea_field( wp_unslash( $_POST['notas'] ) ) : '';

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
        }

        $dir_parts = array_filter( array( $direccion, $calle2, $barrio, $numero ? 'Casa/Apto: ' . $numero : '' ) );
        $dir_full  = implode( ', ', $dir_parts );

        if ( $apellido ) {
            $fname = $nombre;
            $lname = $apellido;
        } else {
            $parts = explode( ' ', $nombre, 2 );
            $fname = $parts[0];
            $lname = isset( $parts[1] ) ? $parts[1] : '';
        }

        try {
            $order = wc_create_order();

            if ( $variation_id ) {
                $var = wc_get_product( $variation_id );
                $order->add_product( $var ? $var : $product, 1 );
            } else {
                $order->add_product( $product, 1 );
            }

            $addr = array(
                'first_name' => $fname,
                'last_name'  => $lname,
                'phone'      => $telefono,
                'email'      => $email,
                'address_1'  => $dir_full,
                'city'       => $ciudad,
                'state'      => $provincia,
                'country'    => 'EC',
                'postcode'   => '',
            );
            $order->set_address( $addr, 'billing' );
            $order->set_address( $addr, 'shipping' );
            $order->set_payment_method( 'cod' );
            $order->set_payment_method_title( 'Pago contra entrega' );
            if ( $notas )  $order->set_customer_note( $notas );
            if ( $cedula ) $order->update_meta_data( '_cedula_cliente', $cedula );

            // Custom fields → order note
            $custom_lines = array();
            foreach ( $_POST as $key => $val ) {
                if ( strpos( $key, 'custom_' ) !== 0 ) continue;
                $label = isset( $_POST[ '_label_' . $key ] )
                    ? sanitize_text_field( wp_unslash( $_POST[ '_label_' . $key ] ) )
                    : sanitize_key( $key );
                $v = sanitize_text_field( wp_unslash( $val ) );
                if ( $v ) $custom_lines[] = $label . ': ' . $v;
            }
            if ( $custom_lines ) {
                $order->add_order_note( implode( ' | ', $custom_lines ) );
            }

            $order->calculate_totals();
            $order->update_status( 'processing', 'Pedido recibido via Dropi Formulario.' );
            $order->save();

            wp_send_json_success( array(
                'order_id'     => $order->get_id(),
                'total'        => floatval( $order->get_total() ),
                'currency'     => get_woocommerce_currency(),
                'product_id'   => $product_id,
                'product_name' => $product->get_name(),
                'message'      => Dropi_Settings::get( 'dropi_msg_exito', '✅ ¡Pedido realizado! Te contactaremos pronto. 🎉' ),
            ));

        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => 'Error al crear el pedido. Intenta de nuevo.' ) );
        }
    }
}

