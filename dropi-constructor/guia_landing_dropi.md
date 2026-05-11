# Guía Completa: Landing Page con Formulario → WordPress → Dropi

> Sistema de dropshipping con pago contra entrega en Ecuador.  
> Ideal para vender desde una landing page sin necesidad de tienda visible.

---

## ¿Cómo funciona todo junto?

```
Cliente llena el formulario (HTML)
        ↓
Formulario envía datos a tu WordPress (API REST)
        ↓
WordPress crea un pedido en WooCommerce
        ↓
Dropi recibe el pedido automáticamente
        ↓
Dropi gestiona el envío al cliente
```

Tú solo necesitas: un hosting con WordPress + WooCommerce, el plugin Dropi, y tu landing page HTML.

---

## PARTE 1 — Preparar WordPress y WooCommerce

### Paso 1.1 — Contratar hosting

Recomendado: **Hostinger** (plan Business o superior para tener WordPress).

1. Ir a [hostinger.com](https://hostinger.com)
2. Comprar un plan con WordPress incluido
3. Durante la instalación, elegir **WordPress + WooCommerce**
4. Anotar tu dominio (ej: `tutienda.com`)

### Paso 1.2 — Configurar WooCommerce básico

Una vez dentro de WordPress (`tutienda.com/wp-admin`):

1. Ir a **WooCommerce → Ajustes**
2. En la pestaña **General**, configurar:
   - País/Región de la tienda: **Ecuador**
   - Moneda: **Dólar estadounidense ($)**
3. En la pestaña **Pagos**, habilitar **Contra reembolso (COD)**
4. Guardar cambios

### Paso 1.3 — Crear tu producto en WooCommerce

1. Ir a **Productos → Añadir nuevo**
2. Completar:
   - **Nombre del producto** (ej: Depiladora 5 en 1)
   - **Precio regular** (ej: 29.99)
   - En **Datos del producto** seleccionar tipo: **Producto variable**
3. Ir a la pestaña **Variaciones** y crear:
   - Variación 1: 1 Unidad → $29.99
   - Variación 2: 2 Unidades → $50.00
4. Publicar el producto
5. **Anotar el ID del producto** (aparece en la URL al editarlo, ej: `?post=18`)
6. **Anotar los IDs de las variaciones** (aparecen dentro de cada variación)

---

## PARTE 2 — Instalar y configurar Dropi

### Paso 2.1 — Instalar el plugin Dropi (Dropify)

1. Ir a **Plugins → Añadir nuevo**
2. Buscar: **Dropify** o **Dropi WooCommerce**
3. Instalar y activar

### Paso 2.2 — Configurar el token de Dropi

1. Primero, obtener tu token:
   - Ir a [app.dropi.ec](https://app.dropi.ec) e iniciar sesión
   - Ir a **Integraciones → WooCommerce**
   - Copiar el **Token de integración**

2. En WordPress, ir a **Dropify → Tokens**
3. Pegar el token en el campo correspondiente
4. Guardar

### Paso 2.3 — Habilitar sincronización automática de pedidos

Este es el paso más importante. Sin esto, los pedidos NO llegan a Dropi.

1. En WordPress, ir a **Dropify → Tokens** (o la sección de configuración)
2. Buscar la opción **"Sincronización automática de pedidos"** o **"Autosync orders"**
3. Activar la casilla ✓
4. Guardar

### Paso 2.4 — Sincronizar tus productos con Dropi

1. Ir a **Dropify → Productos**
2. Importar los productos que quieres vender desde el catálogo de Dropi
3. Una vez importados, anotarás los IDs de producto y variación correctos

---

## PARTE 3 — Crear el endpoint de pedidos (WPCode)

Este código permite que tu formulario HTML cree pedidos directamente en WooCommerce.

### Paso 3.1 — Instalar WPCode

1. Ir a **Plugins → Añadir nuevo**
2. Buscar: **WPCode Lite**
3. Instalar y activar

### Paso 3.2 — Crear el snippet PHP

1. Ir a **WPCode → Añadir nuevo**
2. Hacer clic en **+ Add Custom Snippet**
3. Seleccionar tipo: **PHP Snippet**
4. Darle un nombre: `Endpoint pedidos landing`
5. Pegar el siguiente código en el editor:

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
        'first_name' => $nombre,
        'last_name'  => '.',
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

6. En **Estado del snippet**, activar el toggle a **Activo**
7. Método de inserción: **Auto Insert → Run Everywhere**
8. Hacer clic en **Guardar Snippet**

### Paso 3.3 — Verificar que el endpoint funciona

Abre tu navegador y ve a:

```
https://tutienda.com/wp-json/mi-tienda/v1/pedido
```

Deberías ver un mensaje JSON. Si aparece `rest_no_route`, revisa que el snippet esté activo.

---

## PARTE 4 — El formulario HTML (Landing Page)

### Paso 4.1 — Entender la estructura del formulario

El formulario HTML hace lo siguiente:
1. El cliente elige su pack (1 o 2 unidades)
2. Llena sus datos: nombre, teléfono, provincia, ciudad, dirección
3. Al hacer clic en "Realizar Pedido", envía los datos a tu WordPress
4. WordPress crea el pedido y lo envía a Dropi automáticamente

### Paso 4.2 — Personalizar el formulario para tu producto

Abre el archivo `formulario_pedido.html` y busca estas líneas al inicio del bloque `<script>`:

```javascript
const API = 'https://tutienda.com/wp-json/mi-tienda/v1/pedido';
const PRODUCT_ID = 18;  // ← Cambia por el ID de tu producto
```

Y en los radio buttons, actualiza los `data-variation`:

```html
<input type="radio" name="qty" id="q1" value="1"
       data-price="29.99" data-variation="57" checked>
<!-- data-variation="57" ← ID de la variación "1 unidad" en WooCommerce -->

<input type="radio" name="qty" id="q2" value="2"
       data-price="50.00" data-variation="58">
<!-- data-variation="58" ← ID de la variación "2 unidades" en WooCommerce -->
```

**¿Dónde encontrar estos IDs?**
- `PRODUCT_ID`: en WordPress → Productos → editar el producto → mira la URL: `?post=18`
- `data-variation`: dentro del producto en la pestaña Variaciones, cada variación muestra su ID

### Paso 4.3 — Personalizar textos y precios

Dentro del HTML puedes cambiar:

```html
<h2>Completa tu pedido</h2>         ← Título del formulario
<span class="qty-units">1 Unidad</span>
<span class="qty-price">$29.99</span>  ← Precio mostrado
<span class="qty-save">Ahorras $9.98</span>  ← Etiqueta de ahorro
```

### Paso 4.4 — Las ciudades y provincias (¡MUY IMPORTANTE!)

El formulario ya incluye un **dropdown de ciudades que se actualiza automáticamente** al seleccionar la provincia. Las ciudades están sacadas directamente de la base de datos de Dropi, así que siempre serán válidas.

**NO cambies los `value` de las provincias** (como `EC-P`, `EC-SD`, etc.) — esos códigos son los que WooCommerce necesita internamente.

```html
<option value="EC-P">Pichincha</option>   ← value="EC-P" es obligatorio
<option value="EC-SD">Santo Domingo de los Tsáchilas</option>
```

---

## PARTE 5 — Publicar la Landing Page

### Opción A — Subir el HTML a Hostinger (recomendado)

1. Entrar al panel de Hostinger → **Administrador de archivos**
2. Navegar a la carpeta `public_html`
3. Crear una carpeta nueva, ej: `pedido`
4. Subir el archivo `formulario_pedido.html` dentro de esa carpeta
5. Renombrarlo a `index.html`
6. Tu formulario estará en: `https://tutienda.com/pedido/`

### Opción B — Crear una página en WordPress

1. Ir a **Páginas → Añadir nueva**
2. Añadir un bloque de tipo **HTML personalizado**
3. Pegar todo el contenido del archivo HTML (sin las etiquetas `<html>`, `<head>`, `<body>`)
4. Publicar la página

### Opción C — Usar en Meta Ads / TikTok Ads

Si usas la landing para publicidad, sube el HTML a Hostinger (Opción A) y usa esa URL directamente en tus campañas como página de destino.

---

## PARTE 6 — Prueba completa del sistema

### Paso 6.1 — Hacer un pedido de prueba

1. Abre tu landing page en el navegador
2. Elige un pack
3. Llena los datos con información real:
   - Provincia: **Pichincha**
   - Ciudad: **QUITO** (se llena automáticamente)
   - Dirección: cualquier dirección
4. Haz clic en **REALIZAR PEDIDO**
5. Deberías ver: ✅ "¡Pedido realizado con éxito!"

### Paso 6.2 — Verificar en WooCommerce

1. Ir a **WooCommerce → Pedidos**
2. El pedido nuevo debe aparecer en estado **"Procesando"**
3. En la columna de Dropi debe aparecer **"Yes"** (sincronizado)

### Paso 6.3 — Verificar en Dropi

1. Ir a [app.dropi.ec](https://app.dropi.ec)
2. Ir a **Pedidos**
3. El pedido debe aparecer listo para gestionar

---

## Errores comunes y soluciones

| Error | Causa | Solución |
|-------|-------|----------|
| "No se encontró ruta" al hacer pedido | El snippet de WPCode no está activo | Ir a WPCode y activar el snippet |
| El pedido aparece en WooCommerce pero NO en Dropi | Autosync no está habilitado | Ir a Dropify → configuración y activar autosync |
| Error "La ciudad no existe en el departamento" | Ciudad no corresponde a la provincia seleccionada | El formulario actualizado previene esto con el dropdown de ciudades |
| El pedido muestra "NO SYNC" en WooCommerce | Error al conectar con Dropi | Verificar que el token esté correcto en Dropify → Tokens |
| El formulario no envía | URL del API incorrecta | Verificar que `const API` tenga tu dominio correcto |

---

## Resumen rápido — Checklist

- [ ] WordPress + WooCommerce instalado en Hostinger
- [ ] WooCommerce configurado con moneda USD y país Ecuador
- [ ] Pago "Contra reembolso" activado en WooCommerce
- [ ] Producto creado con sus variaciones (anotar IDs)
- [ ] Plugin Dropi (Dropify) instalado y activado
- [ ] Token de Dropi configurado en Dropify → Tokens
- [ ] Autosync de pedidos activado en la configuración de Dropi
- [ ] Plugin WPCode instalado y activado
- [ ] Snippet PHP del endpoint creado y activo en WPCode
- [ ] Formulario HTML personalizado con tu dominio, product_id y variation IDs
- [ ] Formulario subido a Hostinger o publicado en WordPress
- [ ] Pedido de prueba exitoso visible en WooCommerce y en Dropi

---

*Guía generada para vidaydetalledl.com — Mayo 2026*
