# Instrucción para Claude: Constructor de Formulario Dropi Ecuador

## ¿Qué es este proyecto?

Es un **constructor visual de landing pages** para dropshipping en Ecuador usando el sistema Dropi + WooCommerce + WordPress. El constructor es un único archivo HTML autocontenido (`constructor_formulario.html`) que permite a un vendedor configurar su formulario de pedido sin tocar código, y descargarlo listo para subir a Hostinger.

---

## Arquitectura del sistema completo

```
Cliente llena el formulario HTML (landing page)
        ↓
Formulario envía JSON a WordPress via REST API (POST)
        ↓
WordPress (WooCommerce) crea el pedido
        ↓
Plugin Dropi (Dropify) sincroniza el pedido automáticamente
        ↓
Dropi gestiona el envío al cliente (pago contra entrega)
```

El endpoint de WordPress que recibe los pedidos es un snippet PHP instalado vía WPCode Lite:
- **URL**: `https://tudominio.com/wp-json/mi-tienda/v1/pedido`
- **Método**: POST
- **Body JSON**: `{ nombre, telefono, provincia, ciudad, direccion, product_id, variation_id }`

---

## Qué hace el constructor (`constructor_formulario.html`)

Es un archivo HTML de **dos paneles**:

### Panel izquierdo — Configuración (380px, fondo oscuro `#0f172a`)
El vendedor configura aquí sin tocar código:

1. **Conexión WordPress**
   - URL del endpoint API
   - ID del producto en WooCommerce

2. **Información del producto**
   - Nombre, subtítulo, badge superior

3. **Diseño Visual** *(sección nueva)*
   - Color del botón CTA (color picker + campo hex)
   - Color de fondo de la página (color picker + campo hex)
   - Color de fondo del formulario/tarjeta (color picker + campo hex)
   - Al cambiar el color del botón, se actualiza todo automáticamente: bordes de selección, sombras, precios, badge

4. **Colores / Variantes**
   - Agregar/eliminar colores del producto
   - Cada color tiene: color picker hex, nombre del color
   - Por cada color se ingresan los Variation IDs de WooCommerce (uno por pack)

5. **Packs y Precios**
   - Agregar/eliminar packs (ej: "1 Unidad", "2 Unidades")
   - Cada pack tiene: etiqueta, precio, badge de ahorro (opcional), **URL de foto** (opcional, con vista previa)

6. **Campos del formulario**
   - Siempre incluidos: Nombre+Apellido, Teléfono, Provincia→Ciudad, Calle Principal
   - Opcionales con toggle: Calle Secundaria, Barrio/Referencia, Número de casa, Cédula, Email, Instrucciones adicionales

### Panel derecho — Vista Previa en tiempo real
- Un `<iframe>` con `srcdoc` que se actualiza en cada cambio de configuración
- Muestra exactamente cómo quedará el formulario final

### Botón "Generar y Descargar Formulario"
- Descarga un archivo `formulario_pedido.html` listo para producción
- El archivo generado es completamente autocontenido (no depende del constructor)

---

## Estado del JavaScript (estructura de datos interna)

```javascript
// Colores del producto
let colors = [
  {id: 1, name: 'Rojo',  hex: '#e74c3c'},
  {id: 2, name: 'Azul',  hex: '#3498db'},
  {id: 3, name: 'Negro', hex: '#2c3e50'}
];

// Packs de cantidad/precio
let packs = [
  {id: 1, label: '1 Unidad',   price: '29.99', savings: '',              img: '', vars: {1:'101', 2:'103', 3:'105'}},
  {id: 2, label: '2 Unidades', price: '50.00', savings: 'Ahorras $9.98', img: '', vars: {1:'102', 2:'104', 3:'106'}}
];
// vars[colorId] = variation_id de WooCommerce para ese pack
```

**Mapa de variaciones** generado en `genHTML()`:
```javascript
const vmap = {};
// vmap["colorId-packId"] = variation_id
// Ejemplo: vmap["1-2"] = "102"  →  color Rojo + Pack 2 Unidades = variation 102
```

---

## El formulario generado (`formulario_pedido.html`)

Cuando el constructor descarga el formulario, genera un HTML standalone con:

### Funcionalidades del formulario final
- **Selector de color**: radio buttons estilizados con swatches de color
- **Selector de pack**: tarjetas tipo grid (1x2 o 1x3 según cantidad de packs), con foto si se configuró
- **Campos de dirección**: Nombre, Apellido, Teléfono, Provincia, Ciudad (cascading dropdown), Calle Principal, y los opcionales que se activaron
- **Dropdown de ciudades cascading**: al cambiar la provincia, se llena automáticamente con las ciudades válidas de Dropi
- **Total dinámico**: se actualiza al seleccionar otro pack
- **Botón CTA**: color personalizado con gradiente y sombra
- **Envío asíncrono**: fetch POST al endpoint de WordPress, muestra spinner, mensaje de éxito o error
- **Validación básica**: campos obligatorios verificados antes de enviar

### Datos de ciudades
El formulario incluye hardcodeado el objeto `CIUDADES` con todas las provincias de Ecuador y sus ciudades válidas según la base de datos de Dropi:

```javascript
const CIUDADES = {
  "EC-P": ["QUITO", "SANGOLQUI", "CUMBAYA", ...],  // Pichincha
  "EC-G": ["GUAYAQUIL", "DURAN", "SAMBORONDON", ...],  // Guayas
  "EC-SD": ["SANTO DOMINGO", "LA CONCORDIA", ...],  // Santo Domingo
  // ... 23 provincias completas
};
```

**IMPORTANTE**: Los `value` de las provincias son códigos WooCommerce (`EC-P`, `EC-G`, `EC-SD`, etc.), NO nombres. Dropi necesita que `state` sea el código de provincia correcto.

---

## Flujo del envío (JavaScript en el formulario generado)

```javascript
async function send() {
  // 1. Leer campos del formulario
  // 2. Construir dirección concatenando campos opcionales
  // 3. Obtener variation_id del mapa: VM["colorId-packId"]
  // 4. POST a WordPress:
  fetch(API, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      nombre: fn + ' ' + ln,
      telefono: tel,
      provincia: prov,   // ← código EC-P, EC-G, etc.
      ciudad: city,       // ← nombre en MAYÚSCULAS exacto de Dropi
      direccion: dir,
      product_id: PID,
      variation_id: varId
    })
  })
  // 5. Mostrar éxito o error
}
```

---

## Snippet PHP en WordPress (WPCode Lite, snippet #74)

```php
add_action('rest_api_init', function() {
    register_rest_route('mi-tienda/v1', '/pedido', array(
        'methods' => 'POST',
        'callback' => 'procesar_pedido_custom',
        'permission_callback' => '__return_true',
    ));
});

function procesar_pedido_custom(WP_REST_Request $request) {
    $nombre       = sanitize_text_field($request->get_param('nombre'));
    $telefono     = sanitize_text_field($request->get_param('telefono'));
    $provincia    = sanitize_text_field($request->get_param('provincia'));
    $ciudad       = sanitize_text_field($request->get_param('ciudad'));
    $direccion    = sanitize_text_field($request->get_param('direccion'));
    $product_id   = intval($request->get_param('product_id'));
    $variation_id = intval($request->get_param('variation_id'));

    $order = wc_create_order();

    if ($variation_id) {
        $order->add_product(wc_get_product($product_id), 1, array('variation_id' => $variation_id));
    } else {
        $order->add_product(wc_get_product($product_id), 1);
    }

    $address = array(
        'first_name' => $nombre, 'last_name' => '.',
        'phone'      => $telefono,
        'email'      => $telefono . '@pedido.com',
        'address_1'  => $direccion,
        'city'       => $ciudad,
        'state'      => $provincia,
        'country'    => 'EC',
        'postcode'   => '000000',
    );

    $order->set_address($address, 'billing');
    $order->set_address($address, 'shipping');
    $order->set_payment_method('cod');
    $order->set_payment_method_title('Contra entrega');
    $order->set_created_via('checkout');
    $order->calculate_totals();
    $order->update_status('processing');
    $order->save();

    return array('success' => true, 'order_id' => $order->get_id());
}
```

---

## Lo que ya funciona correctamente

- ✅ Vista previa en tiempo real via `iframe srcdoc`
- ✅ Agregar/eliminar colores y packs dinámicamente
- ✅ Foto por pack con preview en el constructor
- ✅ Color picker para botón CTA (con sincronización hex ↔ picker)
- ✅ Color picker para fondo de página y fondo del formulario
- ✅ Todos los colores del botón propagan a sombras, bordes y precio usando funciones `darken()` y `hexAlpha()`
- ✅ Cascading dropdown provincia → ciudad con datos reales de Dropi
- ✅ Campos opcionales con toggle (Calle Secundaria, Barrio, Número, Cédula, Email, Notas)
- ✅ Descarga del formulario generado como HTML standalone
- ✅ Validación de campos obligatorios antes de enviar
- ✅ Feedback visual: spinner mientras procesa, mensaje de éxito o error
- ✅ Diseño responsive para móvil

---

## Mejoras posibles (ideas para implementar)

A continuación algunas direcciones de mejora que puedes pedirle a Claude:

### UX del constructor
- Guardar configuración en `localStorage` para no perder el trabajo al recargar
- Botón de "Vista previa en nueva pestaña" para ver el formulario a tamaño completo
- Drag & drop para reordenar colores y packs
- Presets de temas de color (verde/azul/naranja/rojo)
- Exportar/importar configuración en JSON

### Formulario generado
- Página de confirmación con resumen del pedido tras el envío exitoso
- Contador de pedidos o urgencia ("Solo quedan 5 unidades")
- Campo de cantidad manual (input numérico) además de los packs fijos
- Sección de testimonios o reviews debajo del formulario
- Pixel de Meta Ads / TikTok Ads — campo para ingresar el Pixel ID en el constructor
- Google Analytics / Tag Manager — campo para el ID de medición
- Redirección automática a una URL de "gracias" tras el pedido exitoso
- Opción de producto sin variantes (sin selector de colores)

### Técnicas
- Validación de teléfono ecuatoriano (10 dígitos, empieza en 09 o 02-07)
- Validación de cédula ecuatoriana con algoritmo de verificación
- Modo sin variaciones (producto simple sin colores)
- Soporte para más de un producto en el mismo formulario
- Campo para imagen de encabezado del producto (banner)

---

## Archivos entregados al usuario

| Archivo | Descripción |
|---|---|
| `constructor_formulario.html` | El constructor visual (este archivo) |
| `formulario_pedido.html` | Formulario de ejemplo pre-configurado |
| `guia_landing_dropi.md` | Tutorial completo paso a paso para nuevos usuarios |

---

## Instrucción de uso para Claude

**Si quieres que Claude implemente mejoras a este constructor, dale este contexto:**

> Tengo un constructor de formularios HTML para dropshipping con Dropi Ecuador. Es un único archivo HTML autocontenido con dos paneles: izquierdo de configuración (dark theme) y derecho de vista previa en iframe. El constructor genera y descarga formularios de pedido HTML que envían datos a WordPress/WooCommerce via REST API. El estado interno usa arrays `colors[]` y `packs[]` con IDs cruzados por un mapa de variaciones `vmap["colorId-packId"]`. Las ciudades de Ecuador están hardcodeadas según la base de datos de Dropi. Necesito que [DESCRIBE TU MEJORA AQUÍ] sin romper la funcionalidad existente. Te adjunto el código completo del constructor actual.

**Luego pega el código completo del archivo `constructor_formulario.html`.**

---

*Documento generado para vidaydetalledl.com — Mayo 2026*
