<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Dropi_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
    }

    public function add_menu() {
        add_options_page( 'Dropi Formulario', 'Dropi Formulario', 'manage_options', 'dropi-formulario', array( $this, 'page' ) );
    }

    public function register() {
        register_setting( 'dropi_opts', 'dropi_color_boton',  array( 'default' => '#1D9E75' ) );
        register_setting( 'dropi_opts', 'dropi_color_fondo',  array( 'default' => '#ffffff' ) );
        register_setting( 'dropi_opts', 'dropi_texto_boton',  array( 'default' => '🛵 REALIZAR PEDIDO — Pago al recibir' ) );
        register_setting( 'dropi_opts', 'dropi_msg_exito',    array( 'default' => '✅ ¡Pedido realizado! Te contactaremos pronto. 🎉' ) );
        register_setting( 'dropi_opts', 'dropi_msg_error',    array( 'default' => '❌ Error al procesar. Intenta de nuevo.' ) );
        register_setting( 'dropi_opts', 'dropi_campo_calle2', array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_campo_barrio', array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_campo_numero', array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_campo_cedula', array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_campo_email',  array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_campo_notas',  array( 'default' => '0' ) );
        register_setting( 'dropi_opts', 'dropi_pixel_meta',      array( 'default' => '1' ) );
        register_setting( 'dropi_opts', 'dropi_pixel_tiktok',   array( 'default' => '1' ) );
        register_setting( 'dropi_opts', 'dropi_pixel_google',   array( 'default' => '1' ) );
        register_setting( 'dropi_opts', 'dropi_meta_pixel_id',  array( 'default' => '' ) );
        register_setting( 'dropi_opts', 'dropi_tiktok_pixel_id',array( 'default' => '' ) );
        register_setting( 'dropi_opts', 'dropi_google_ga4_id',  array( 'default' => '' ) );
        register_setting( 'dropi_opts', 'dropi_api_key',        array( 'default' => '' ) );
    }

    private function get_or_create_api_key() {
        $key = get_option( 'dropi_api_key', '' );
        if ( ! $key ) {
            $key = bin2hex( random_bytes( 24 ) );
            update_option( 'dropi_api_key', $key );
        }
        return $key;
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Handle API key regeneration
        if ( isset( $_POST['dropi_regen_key'] ) && check_admin_referer( 'dropi_regen_key' ) ) {
            $new_key = bin2hex( random_bytes( 24 ) );
            update_option( 'dropi_api_key', $new_key );
            echo '<div class="notice notice-success"><p>API Key regenerada correctamente.</p></div>';
        }

        $api_key = $this->get_or_create_api_key();
        ?>
        <div class="wrap">
        <h1>Dropi Formulario — Ajustes</h1>

        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;margin-bottom:20px;border-radius:0 4px 4px 0">
          <strong>🔑 API Key del Constructor</strong><br>
          <p style="margin:6px 0 4px">Copia esta clave en el campo "API Key" del Constructor de Formulario para poder guardar configuraciones desde el constructor.</p>
          <code id="dropi-api-key-display" style="display:inline-block;background:#f8f9fa;border:1px solid #dee2e6;padding:6px 12px;border-radius:4px;font-size:13px;letter-spacing:.5px;user-select:all"><?php echo esc_html( $api_key ); ?></code>
          <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('dropi-api-key-display').textContent).then(()=>{this.textContent='✅ Copiada!';setTimeout(()=>this.textContent='📋 Copiar',1500)})" style="margin-left:8px;padding:5px 12px;font-size:12px;cursor:pointer">📋 Copiar</button>
          <form method="post" style="display:inline;margin-left:8px">
            <?php wp_nonce_field( 'dropi_regen_key' ); ?>
            <button type="submit" name="dropi_regen_key" onclick="return confirm('¿Regenerar la API Key? El constructor necesitará la nueva clave.')" style="padding:5px 12px;font-size:12px;cursor:pointer;background:#dc3545;color:#fff;border:none;border-radius:3px">🔄 Regenerar</button>
          </form>
        </div>

        <form method="post" action="options.php">
        <?php settings_fields( 'dropi_opts' ); ?>
        <h2>General (valores por defecto para productos sin configuración)</h2>
        <table class="form-table">
          <tr><th>Color del botón</th><td><input type="color" name="dropi_color_boton" value="<?php echo esc_attr( get_option('dropi_color_boton','#1D9E75') ); ?>"></td></tr>
          <tr><th>Color de fondo</th><td><input type="color" name="dropi_color_fondo" value="<?php echo esc_attr( get_option('dropi_color_fondo','#ffffff') ); ?>"></td></tr>
          <tr><th>Texto del botón</th><td><input type="text" class="regular-text" name="dropi_texto_boton" value="<?php echo esc_attr( get_option('dropi_texto_boton','🛵 REALIZAR PEDIDO — Pago al recibir') ); ?>"></td></tr>
          <tr><th>Mensaje de éxito</th><td><textarea name="dropi_msg_exito" class="large-text" rows="3"><?php echo esc_textarea( get_option('dropi_msg_exito') ); ?></textarea></td></tr>
          <tr><th>Mensaje de error</th><td><textarea name="dropi_msg_error" class="large-text" rows="3"><?php echo esc_textarea( get_option('dropi_msg_error') ); ?></textarea></td></tr>
        </table>
        <h2>Campos opcionales</h2>
        <table class="form-table">
          <?php
          $campos = array(
            'dropi_campo_calle2' => 'Calle Secundaria',
            'dropi_campo_barrio' => 'Barrio / Sector',
            'dropi_campo_numero' => 'Número de Casa',
            'dropi_campo_cedula' => 'Cédula',
            'dropi_campo_email'  => 'Email',
            'dropi_campo_notas'  => 'Notas',
          );
          foreach ( $campos as $k => $l ) {
            echo '<tr><th>' . esc_html($l) . '</th><td><label><input type="checkbox" name="' . esc_attr($k) . '" value="1" ' . checked(get_option($k,'0'),'1',false) . '> Mostrar</label></td></tr>';
          }
          ?>
        </table>
        <h2>Pixel Tracking — IDs Globales</h2>
        <p style="color:#555;margin-top:-8px">Ingresa los IDs para que el pixel se cargue en <strong>todas las páginas</strong> del sitio (PageView automático). Si tienes IDs configurados por producto en el constructor, esos tienen prioridad para los eventos de conversión.</p>
        <table class="form-table">
          <tr>
            <th>Meta Pixel ID</th>
            <td>
              <input type="text" class="regular-text" name="dropi_meta_pixel_id" value="<?php echo esc_attr( get_option('dropi_meta_pixel_id','') ); ?>" placeholder="Ej: 1234567890123456">
              <p class="description">Solo el número. Lo encuentras en Meta Business Suite → Administrador de eventos → tu pixel.</p>
            </td>
          </tr>
          <tr>
            <th>TikTok Pixel ID</th>
            <td>
              <input type="text" class="regular-text" name="dropi_tiktok_pixel_id" value="<?php echo esc_attr( get_option('dropi_tiktok_pixel_id','') ); ?>" placeholder="Ej: CXXXXXXXXXXXXXXXX">
              <p class="description">TikTok Ads Manager → Activos → Eventos → tu pixel → ID.</p>
            </td>
          </tr>
          <tr>
            <th>Google GA4 ID</th>
            <td>
              <input type="text" class="regular-text" name="dropi_google_ga4_id" value="<?php echo esc_attr( get_option('dropi_google_ga4_id','') ); ?>" placeholder="Ej: G-XXXXXXXXXX">
              <p class="description">Google Analytics → Administrar → Flujos de datos → tu sitio → ID de medición.</p>
            </td>
          </tr>
        </table>
        <h2>Pixel Tracking — Eventos de conversión</h2>
        <table class="form-table">
          <tr><th>Meta Pixel</th><td><label><input type="checkbox" name="dropi_pixel_meta" value="1" <?php checked(get_option('dropi_pixel_meta','1'),'1'); ?>> Disparar ViewContent, InitiateCheckout y Purchase</label></td></tr>
          <tr><th>TikTok Pixel</th><td><label><input type="checkbox" name="dropi_pixel_tiktok" value="1" <?php checked(get_option('dropi_pixel_tiktok','1'),'1'); ?>> Disparar ViewContent, InitiateCheckout y CompletePayment</label></td></tr>
          <tr><th>Google / GA4</th><td><label><input type="checkbox" name="dropi_pixel_google" value="1" <?php checked(get_option('dropi_pixel_google','1'),'1'); ?>> Disparar view_item, begin_checkout y purchase</label></td></tr>
        </table>
        <?php submit_button('Guardar'); ?>
        </form>
        </div>
        <?php
    }

    public static function get( $key, $default = '' ) {
        return get_option( $key, $default );
    }
}

