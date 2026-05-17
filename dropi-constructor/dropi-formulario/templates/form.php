<?php if ( ! defined( 'ABSPATH' ) ) exit;

$has_config  = ! empty( $config );
$form_style  = $settings['form_style'];
$color_boton = esc_attr( $settings['color_boton'] );
$texto_boton = esc_html( $settings['texto_boton'] );

// CSS inline vars for theming
$css_vars = sprintf(
    '--df-btn:%s;--df-badge:%s;--df-text:%s;',
    esc_attr( $settings['color_boton'] ),
    esc_attr( $settings['badge_color'] ),
    esc_attr( $settings['text_color'] )
);

// Province list for select
$first_pack_price = '';
if ( $has_config && ! empty( $packs ) ) {
    $first_pack_price = '$' . number_format( floatval( $packs[0]['price'] ?? 0 ), 2 );
} elseif ( ! $has_config && ! empty( $packs ) ) {
    $first_pack_price = $packs[0]['price_html'] ?? '';
}

// Field config helpers (config mode)
$field_map  = array();
if ( $fields ) {
    foreach ( $fields as $f ) {
        $field_map[ $f['key'] ] = $f;
    }
}
$f_en  = function( $key ) use ( $field_map ) { return isset($field_map[$key]) ? (bool)$field_map[$key]['enabled'] : true; };
$f_req = function( $key ) use ( $field_map ) { return isset($field_map[$key]) ? (bool)$field_map[$key]['required'] : false; };
$f_lbl = function( $key, $default ) use ( $field_map ) { return esc_html( $field_map[$key]['label'] ?? $default ); };
$f_plc = function( $key, $default ) use ( $field_map ) { return esc_attr( $field_map[$key]['placeholder'] ?? $default ); };
?>

<?php
// Inject pixel base codes inline (wp_head already fired when shortcode runs)
static $dropi_meta_pixel_done   = false;
static $dropi_tiktok_pixel_done = false;

if ( ! empty( $pixel_cfg['meta'] ) && ! $dropi_meta_pixel_done ) :
    $dropi_meta_pixel_done = true; ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init','<?php echo esc_js( $pixel_cfg['meta'] ); ?>');
fbq('track','PageView');
</script>
<?php endif;

if ( ! empty( $pixel_cfg['tiktok'] ) && ! $dropi_tiktok_pixel_done ) :
    $dropi_tiktok_pixel_done = true; ?>
<script>
!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],
ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},
ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";
ttq._i=ttq._i||{},ttq._i[e]=[],ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};
var s=document.createElement("script");s.type="text/javascript",s.async=!0,s.src=r+"?sdkid="+e+"&lib="+t;
var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(s,a)};
ttq.load('<?php echo esc_js( $pixel_cfg['tiktok'] ); ?>');ttq.page()}(window,document,'ttq');
</script>
<?php endif; ?>
<style>
.site-title,.site-title a,.site-description,
.entry-title,.page-title,.post-title,
.wp-block-post-title,h1.entry-title,
.page-header,.page-header .page-title,
header.site-header .site-branding h1,
header.site-header .site-branding p,
#masthead .site-title,
#masthead .site-description
{display:none!important}
</style>
<div class="dropi-wrap" id="<?php echo esc_attr($form_id); ?>"
     style="background:<?php echo esc_attr($settings['color_fondo']); ?>;<?php echo $css_vars; ?>"
     data-product-id="<?php echo esc_attr($product_id); ?>"
     data-form-style="<?php echo esc_attr($form_style); ?>">

  <div class="df-header">
    <div class="df-badge"><?php echo esc_html( $settings['badge'] ); ?></div>
    <h2 class="df-title"><?php echo esc_html( $settings['product_name'] ); ?></h2>
    <?php if ( $settings['subtitle'] ) : ?>
    <p class="df-sub"><?php echo esc_html( $settings['subtitle'] ); ?></p>
    <?php endif; ?>
  </div>

  <?php // ── COLORES ── ?>
  <?php if ( ! empty( $colors ) ) : ?>
  <div class="df-section">
    <div class="df-section-label">Color:</div>
    <div class="df-colors" id="colors_<?php echo esc_attr($form_id); ?>">
      <?php foreach ( $colors as $i => $col ) : ?>
      <button type="button"
              class="df-color-swatch <?php echo $i === 0 ? 'selected' : ''; ?>"
              data-color-id="<?php echo esc_attr($col['id']); ?>"
              style="background:<?php echo esc_attr($col['hex']); ?>"
              title="<?php echo esc_attr($col['name']); ?>">
        <span class="df-color-name"><?php echo esc_html($col['name']); ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php // ── TALLAS ── ?>
  <?php if ( ! empty( $sizes ) ) : ?>
  <div class="df-section">
    <div class="df-section-label">Talla:</div>
    <div class="df-sizes" id="sizes_<?php echo esc_attr($form_id); ?>">
      <?php foreach ( $sizes as $i => $sz ) : ?>
      <button type="button"
              class="df-size-btn <?php echo $i === 0 ? 'selected' : ''; ?>"
              data-size-id="<?php echo esc_attr($sz['id']); ?>">
        <?php echo esc_html($sz['name']); ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php // ── PACKS ── ?>
  <?php if ( $has_config && ! empty( $packs ) ) : ?>
  <div class="df-section">
    <?php if ( count($packs) > 1 ) : ?>
    <div class="df-section-label">Elige tu pack:</div>
    <?php endif; ?>
    <div class="df-packs <?php echo $form_style === 'oferta' ? 'df-packs-oferta' : ''; ?>"
         <?php if ( $form_style !== 'oferta' ) : ?>style="grid-template-columns:<?php echo count($packs) > 2 ? 'repeat(3,1fr)' : '1fr 1fr'; ?>"<?php endif; ?>>
      <?php foreach ( $packs as $i => $pack ) :
        $vars_json = esc_attr( wp_json_encode( $pack['vars'] ?? array() ) );
        $first_color_id = ! empty( $colors ) ? $colors[0]['id'] : 0;
        $first_size_id  = ! empty( $sizes )  ? $sizes[0]['id']  : 0;
        $var_key        = $first_color_id . '-' . $first_size_id;
        $var_id         = $pack['vars'][ $var_key ] ?? ( $pack['vars'][ array_key_first($pack['vars'] ?? []) ] ?? '0' );
      ?>
      <?php if ( $form_style === 'oferta' ) : ?>
      <div class="df-pack-option df-pack-oferta">
        <input type="radio"
               name="df_var_<?php echo esc_attr($form_id); ?>"
               id="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>"
               value="<?php echo esc_attr($var_id); ?>"
               data-price="<?php echo esc_attr($pack['price'] ?? '0'); ?>"
               data-price-html="$<?php echo esc_attr(number_format(floatval($pack['price']??0),2)); ?>"
               data-pack-vars="<?php echo $vars_json; ?>"
               data-pack-label="<?php echo esc_attr($pack['label']); ?>"
               <?php echo $i === 0 ? 'checked' : ''; ?>>
        <label for="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>">
          <?php if ( ! empty($pack['ofertaBadge']) ) : ?>
          <span class="df-oferta-badge" style="background:<?php echo esc_attr($settings['badge_color']); ?>"><?php echo esc_html($pack['ofertaBadge']); ?></span>
          <?php endif; ?>
          <?php if ( ! empty($pack['img']) ) : ?>
          <img src="<?php echo esc_url($pack['img']); ?>" alt="<?php echo esc_attr($pack['label']); ?>" class="df-pack-img" onerror="this.style.display='none'">
          <?php else : ?>
          <div class="df-pack-img-ph">📦</div>
          <?php endif; ?>
          <div class="df-oferta-info">
            <span class="df-pack-label"><?php echo esc_html($pack['label']); ?></span>
            <div class="df-oferta-prices">
              <?php if ( ! empty($pack['precioAntes']) ) : ?>
              <span class="df-price-before">$<?php echo esc_html($pack['precioAntes']); ?></span>
              <?php endif; ?>
              <span class="df-pack-price">$<?php echo esc_html(number_format(floatval($pack['price']??0),2)); ?></span>
            </div>
            <?php if ( ! empty($pack['savings']) ) : ?>
            <span class="df-pack-savings"><?php echo esc_html($pack['savings']); ?></span>
            <?php endif; ?>
          </div>
        </label>
      </div>
      <?php else : // clasico style ?>
      <div class="df-pack-option">
        <input type="radio"
               name="df_var_<?php echo esc_attr($form_id); ?>"
               id="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>"
               value="<?php echo esc_attr($var_id); ?>"
               data-price="<?php echo esc_attr($pack['price'] ?? '0'); ?>"
               data-price-html="$<?php echo esc_attr(number_format(floatval($pack['price']??0),2)); ?>"
               data-pack-vars="<?php echo $vars_json; ?>"
               data-pack-label="<?php echo esc_attr($pack['label']); ?>"
               <?php echo $i === 0 ? 'checked' : ''; ?>>
        <label for="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>">
          <?php if ( ! empty($pack['img']) ) : ?>
          <img src="<?php echo esc_url($pack['img']); ?>" alt="<?php echo esc_attr($pack['label']); ?>" class="df-pack-img" onerror="this.style.display='none'">
          <?php endif; ?>
          <span class="df-pack-label"><?php echo esc_html($pack['label']); ?></span>
          <span class="df-pack-price">$<?php echo esc_html(number_format(floatval($pack['price']??0),2)); ?></span>
          <?php if ( ! empty($pack['savings']) ) : ?>
          <span class="df-pack-savings"><?php echo esc_html($pack['savings']); ?></span>
          <?php endif; ?>
        </label>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <hr class="df-divider">

  <?php elseif ( ! $has_config && count( $packs ) > 1 ) : // fallback WC variations ?>
  <div class="df-section">
    <div class="df-section-label">Elige tu pack:</div>
    <div class="df-packs">
      <?php foreach ( $packs as $i => $var ) :
        $label = implode( ' · ', array_values( $var['attributes'] ) ) ?: 'Pack ' . ($i+1);
      ?>
      <div class="df-pack-option">
        <input type="radio"
               name="df_var_<?php echo esc_attr($form_id); ?>"
               id="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>"
               value="<?php echo esc_attr($var['id']); ?>"
               data-price="<?php echo esc_attr($var['price']); ?>"
               data-price-html="<?php echo esc_attr($var['price_html']); ?>"
               data-pack-vars="{}"
               <?php echo $i === 0 ? 'checked' : ''; ?>>
        <label for="v_<?php echo esc_attr($form_id); ?>_<?php echo $i; ?>">
          <span class="df-pack-label"><?php echo esc_html($label); ?></span>
          <span class="df-pack-price"><?php echo esc_html($var['price_html']); ?></span>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <hr class="df-divider">
  <?php endif; ?>

  <form class="df-form" id="form_<?php echo esc_attr($form_id); ?>" novalidate>

    <?php // ── FIELDS: config mode ── ?>
    <?php if ( $fields ) : ?>

      <?php if ( $f_en('nombre') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('nombre','Nombre'); ?><?php if($f_req('nombre')) echo ' *'; ?></label>
        <input type="text" name="nombre" placeholder="<?php echo $f_plc('nombre','María'); ?>" <?php if($f_req('nombre')) echo 'required'; ?>>
      </div>
      <?php endif; ?>

      <?php if ( $f_en('apellido') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('apellido','Apellido'); ?><?php if($f_req('apellido')) echo ' *'; ?></label>
        <input type="text" name="apellido" placeholder="<?php echo $f_plc('apellido','García'); ?>" <?php if($f_req('apellido')) echo 'required'; ?>>
      </div>
      <?php endif; ?>

      <?php if ( $f_en('telefono') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('telefono','Teléfono / WhatsApp'); ?><?php if($f_req('telefono')) echo ' *'; ?></label>
        <input type="tel" name="telefono" placeholder="<?php echo $f_plc('telefono','0987654321'); ?>" <?php if($f_req('telefono')) echo 'required'; ?>>
      </div>
      <?php endif; ?>

      <div class="df-field">
        <label><?php echo $f_lbl('prov','Provincia'); ?> *</label>
        <select name="provincia" required>
          <option value="">Selecciona tu provincia</option>
          <?php foreach ( $provincias_tpl as $prov ) : ?>
          <option value="<?php echo esc_attr($prov['code']); ?>"><?php echo esc_html($prov['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="df-field">
        <label><?php echo $f_lbl('city','Ciudad / Cantón'); ?> *</label>
        <select name="ciudad" required disabled>
          <option value="">Primero selecciona una provincia</option>
        </select>
      </div>

      <?php if ( $f_en('calle1') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('calle1','Dirección exacta'); ?><?php if($f_req('calle1')) echo ' *'; ?></label>
        <input type="text" name="direccion" placeholder="<?php echo $f_plc('calle1','Av. Amazonas N23-45 y Veintimilla'); ?>" <?php if($f_req('calle1')) echo 'required'; ?>>
      </div>
      <?php endif; ?>

      <?php if ( $f_en('calle2') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('calle2','Calle Secundaria'); ?></label>
        <input type="text" name="calle2" placeholder="<?php echo $f_plc('calle2','Calle secundaria o referencia'); ?>">
      </div>
      <?php endif; ?>

      <?php if ( $f_en('barrio') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('barrio','Barrio / Sector'); ?></label>
        <input type="text" name="barrio" placeholder="<?php echo $f_plc('barrio','Ej: La Mariscal'); ?>">
      </div>
      <?php endif; ?>

      <?php if ( $f_en('numero') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('numero','Número de Casa / Apto'); ?></label>
        <input type="text" name="numero" placeholder="<?php echo $f_plc('numero','Ej: 3B'); ?>">
      </div>
      <?php endif; ?>

      <?php if ( $f_en('cedula') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('cedula','Cédula'); ?></label>
        <input type="text" name="cedula" placeholder="<?php echo $f_plc('cedula','1712345678'); ?>" maxlength="10">
      </div>
      <?php endif; ?>

      <?php if ( $f_en('email') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('email','Email'); ?></label>
        <input type="email" name="email" placeholder="<?php echo $f_plc('email','tucorreo@gmail.com'); ?>">
      </div>
      <?php endif; ?>

      <?php if ( $f_en('notas') ) : ?>
      <div class="df-field">
        <label><?php echo $f_lbl('notas','Notas'); ?></label>
        <textarea name="notas" rows="3" placeholder="<?php echo $f_plc('notas','Instrucciones especiales...'); ?>"></textarea>
      </div>
      <?php endif; ?>

      <?php foreach ( $fields as $cf ) : ?>
      <?php if ( strpos( $cf['key'], 'custom_' ) !== 0 || empty( $cf['enabled'] ) ) continue; ?>
      <div class="df-field">
        <label><?php echo esc_html( $cf['label'] ?? '' ); ?><?php if ( ! empty( $cf['required'] ) ) echo ' *'; ?></label>
        <input type="text" name="<?php echo esc_attr( $cf['key'] ); ?>"
               placeholder="<?php echo esc_attr( $cf['placeholder'] ?? '' ); ?>"
               data-label="<?php echo esc_attr( $cf['label'] ?? $cf['key'] ); ?>"
               <?php if ( ! empty( $cf['required'] ) ) echo 'required'; ?>>
      </div>
      <?php endforeach; ?>

    <?php else : // ── FIELDS: fallback / global settings mode ── ?>

      <div class="df-field"><label>Nombre completo *</label>
        <input type="text" name="nombre" placeholder="Ej: María García" required></div>

      <div class="df-field"><label>Teléfono / WhatsApp *</label>
        <input type="tel" name="telefono" placeholder="Ej: 0987654321" required></div>

      <?php if ( $settings['campo_cedula'] === '1' ) : ?>
      <div class="df-field"><label>Cédula</label>
        <input type="text" name="cedula" placeholder="1712345678"></div>
      <?php endif; ?>

      <?php if ( $settings['campo_email'] === '1' ) : ?>
      <div class="df-field"><label>Email</label>
        <input type="email" name="email" placeholder="tucorreo@gmail.com"></div>
      <?php endif; ?>

      <div class="df-field"><label>Provincia *</label>
        <select name="provincia" required>
          <option value="">Selecciona tu provincia</option>
          <?php foreach ( $provincias_tpl as $prov ) : ?>
          <option value="<?php echo esc_attr($prov['code']); ?>"><?php echo esc_html($prov['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="df-field"><label>Ciudad / Cantón *</label>
        <select name="ciudad" required disabled>
          <option value="">Primero selecciona una provincia</option>
        </select>
      </div>

      <div class="df-field"><label>Dirección exacta *</label>
        <input type="text" name="direccion" placeholder="Ej: Av. Amazonas N23-45 y Veintimilla" required></div>

      <?php if ( $settings['campo_calle2'] === '1' ) : ?>
      <div class="df-field"><label>Calle Secundaria</label>
        <input type="text" name="calle2" placeholder="Calle secundaria o referencia"></div>
      <?php endif; ?>

      <?php if ( $settings['campo_barrio'] === '1' ) : ?>
      <div class="df-field"><label>Barrio / Sector</label>
        <input type="text" name="barrio" placeholder="Ej: La Mariscal"></div>
      <?php endif; ?>

      <?php if ( $settings['campo_numero'] === '1' ) : ?>
      <div class="df-field"><label>Número de Casa / Apto</label>
        <input type="text" name="numero" placeholder="Ej: 3B"></div>
      <?php endif; ?>

      <?php if ( $settings['campo_notas'] === '1' ) : ?>
      <div class="df-field"><label>Notas</label>
        <textarea name="notas" rows="3" placeholder="Instrucciones especiales..."></textarea></div>
      <?php endif; ?>

    <?php endif; ?>

    <?php if ( $first_pack_price ) : ?>
    <div class="df-total-box">
      <span class="df-total-label">Total a pagar al recibir:</span>
      <span class="df-total-price"><?php echo esc_html($first_pack_price); ?></span>
    </div>
    <?php endif; ?>

    <button type="submit" class="df-btn-submit"
            style="background:<?php echo $color_boton; ?>;box-shadow:0 6px 24px <?php echo $color_boton; ?>55">
      <?php echo $texto_boton; ?>
    </button>

    <div class="df-trust">
      <span>✔ Envío gratis</span>
      <span>✔ Pago al recibir</span>
      <span>✔ Garantía 100%</span>
    </div>

    <div class="df-msg df-ok" style="display:none"><?php echo esc_html($settings['msg_exito']); ?></div>
    <div class="df-msg df-err" style="display:none"><?php echo esc_html($settings['msg_error']); ?></div>
  </form>
</div>
