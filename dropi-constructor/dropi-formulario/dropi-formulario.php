<?php
/*
Plugin Name: Dropi Formulario
Plugin URI: https://vidaydetalledl.com
Description: Formulario de pedido para dropshipping con Dropi Ecuador. Uso: [dropi_form id="116"]
Version: 1.2.1
Author: LandingAI Builder
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'DROPI_VERSION' ) ) define( 'DROPI_VERSION', '1.2.1' );
if ( ! defined( 'DROPI_DIR' ) )     define( 'DROPI_DIR',     plugin_dir_path( __FILE__ ) );
if ( ! defined( 'DROPI_URL' ) )     define( 'DROPI_URL',     plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'dropi_init' ) ) :
function dropi_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Dropi Formulario</strong> requiere WooCommerce activo.</p></div>';
        });
        return;
    }
    require_once DROPI_DIR . 'includes/ciudades.php';
    require_once DROPI_DIR . 'includes/settings.php';
    require_once DROPI_DIR . 'includes/rest-api.php';
    require_once DROPI_DIR . 'includes/shortcode.php';
    require_once DROPI_DIR . 'includes/ajax.php';
    new Dropi_Settings();
    new Dropi_Rest_Api();
    new Dropi_Shortcode();
    new Dropi_Ajax();
    add_action( 'wp_head', 'dropi_inject_pixels', 1 );
}
add_action( 'plugins_loaded', 'dropi_init' );
endif;

if ( ! function_exists( 'dropi_inject_pixels' ) ) :
function dropi_inject_pixels() {
    $meta_id   = trim( get_option( 'dropi_meta_pixel_id',   '' ) );
    $tiktok_id = trim( get_option( 'dropi_tiktok_pixel_id', '' ) );
    $ga4_id    = trim( get_option( 'dropi_google_ga4_id',   '' ) );

    if ( $meta_id && preg_match( '/^\d+$/', $meta_id ) ) {
        $mid = esc_js( $meta_id );
        echo "<!-- Meta Pixel -->\n<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$mid}');fbq('track','PageView');</script>\n<noscript><img height='1' width='1' style='display:none' src='https://www.facebook.com/tr?id={$mid}&ev=PageView&noscript=1'/></noscript>\n";
    }

    if ( $tiktok_id && preg_match( '/^[A-Z0-9]+$/i', $tiktok_id ) ) {
        $tid = esc_js( $tiktok_id );
        echo "<!-- TikTok Pixel -->\n<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{},ttq._i[e]=[],ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var s=document.createElement('script');s.type='text/javascript',s.async=!0,s.src=r+'?sdkid='+e+'&lib='+t;var a=document.getElementsByTagName('script')[0];a.parentNode.insertBefore(s,a)};ttq.load('{$tid}');ttq.page()}(window,document,'ttq');</script>\n";
    }

    if ( $ga4_id && preg_match( '/^G-[A-Z0-9]+$/i', $ga4_id ) ) {
        $gid = esc_js( $ga4_id );
        echo "<!-- Google GA4 -->\n<script async src='https://www.googletagmanager.com/gtag/js?id={$gid}'></script>\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{$gid}');</script>\n";
    }
}
endif;
