# Traspaso de Proyecto — Constructor de Formularios Dropi Ecuador

> Este documento es para continuar el desarrollo en un nuevo chat de Claude.
> El usuario ya tiene su perfil definido contigo. Este documento cubre exclusivamente
> la herramienta que estamos construyendo.

---

## Qué es esta herramienta

Un **constructor visual de formularios de pedido** para dropshipping con Dropi Ecuador.
Es un archivo HTML único (`constructor_formulario.html`) que el usuario abre en su computadora,
configura su producto, y genera un formulario HTML listo para pegar en una página de WordPress.

El usuario trabaja así:
- Construye sus páginas HTML en sus herramientas
- Va a WordPress y pega el código en una página
- El cliente entra a esa página y hace su pedido

No hay tienda visible. Solo landing page + formulario de pedido.

---

## El problema que resuelve

### Problema 1 — Órdenes que no llegaban a Dropi
Los pedidos llegaban a WooCommerce pero Dropi los rechazaba con el error:
> "La ciudad no existe en el departamento ingresado"

**Causa raíz**: el formulario anterior usaba un campo de texto libre para la ciudad.
Los clientes escribían nombres que no coincidían exactamente con la base de datos de Dropi.

**Solución aplicada**: formulario con dropdown de provincias y ciudades en cascada,
usando los nombres exactos de la base de datos de Dropi (`places/EC.php`).

### Problema 2 — IDs de variaciones manuales
Cada producto de Dropi tiene variaciones (color, talla, cantidad) con IDs únicos en WooCommerce.
El vendedor tenía que buscar esos IDs a mano y copiarlos al formulario.

**Solución aplicada**: endpoint PHP en WPCode que expone las variaciones del producto,
y botón en el constructor que las carga automáticamente.

---

## Arquitectura del sistema

```
Cliente llena el formulario HTML (en WordPress)
        ↓
Formulario envía JSON a WordPress via REST API
POST https://dominio.com/wp-json/mi-tienda/v1/pedido
        ↓
WordPress crea el pedido en WooCommerce (pago COD)
        ↓
Plugin Dropi (Dropify) sincroniza el pedido automáticamente
        ↓
Dropi gestiona el envío — pago contra entrega
```

**Datos que envía el formulario al crear un pedido:**
```json
{
  "nombre": "Juan García",
  "telefono": "0991234567",
  "provincia": "EC-P",
  "ciudad": "QUITO",
  "direccion": "Av. Amazonas N23-45, Barrio El Ejido",
  "product_id": 116,
  "variation_id": 203
}
```

**IMPORTANTE**: `provincia` debe ser el código WooCommerce (`EC-P`, `EC-G`, etc.),
y `ciudad` debe estar en MAYÚSCULAS exactamente como aparece en la base de datos de Dropi.

---

## Snippets PHP instalados en WPCode (WordPress)

### Snippet #74 — "Fix Dropi origen checkout" (ACTIVO)
Crea el endpoint que recibe pedidos del formulario:
```
POST https://dominio.com/wp-json/mi-tienda/v1/pedido
```
Crea el pedido en WooCommerce con método de pago COD y lo envía a Dropi.

### Snippet nuevo — "Endpoint variaciones producto" (INSTALAR)
Crea el endpoint que expone las variaciones del producto para el constructor:
```
GET https://dominio.com/wp-json/mi-tienda/v1/producto/{id}
```
Devuelve nombre, tipo, atributos y todas las variaciones con sus IDs reales.
Incluye headers CORS para que el constructor pueda consultarlo desde la computadora.

**El código de este snippet está en**: `snippet_variaciones_wpcode.php`

---

## Archivos del proyecto

| Archivo | Qué es |
|---|---|
| `constructor_formulario.html` | La herramienta principal. Constructor visual con panel de config + vista previa en tiempo real. Descarga el formulario configurado. |
| `formulario_pedido.html` | Ejemplo de formulario ya generado, configurado para el producto ID 18 de vidaydetalledl.com |
| `snippet_variaciones_wpcode.php` | Código PHP para agregar a WPCode. Expone las variaciones del producto vía REST API. |
| `guia_landing_dropi.md` | Tutorial completo paso a paso para nuevos usuarios del sistema. |
| `instruccion_constructor_para_claude.md` | Documentación técnica detallada del constructor para referencia. |

---

## Lo que hace el constructor actualmente

**Panel izquierdo — configuración:**
- URL del endpoint de WordPress y Product ID
- Botón "Cargar variaciones desde WordPress" → lee los atributos del proveedor automáticamente
- Si hay error de CORS → muestra URL del endpoint para copiar/pegar el JSON manualmente
- Diseño visual: color del botón CTA, fondo de página, fondo del formulario
- Colores / Variantes: agregar/eliminar colores con hex picker, nombre y Variation IDs por pack
- Packs y precios: agregar/eliminar packs con foto, etiqueta, precio, badge de ahorro
- Campos opcionales: Calle Secundaria, Barrio, Número, Cédula, Email, Instrucciones

**Panel derecho:** vista previa en tiempo real via iframe

**Descarga:** genera HTML standalone con las ciudades de Dropi embebidas, listo para pegar en WordPress

---

## Estado actual — qué funciona y qué falta

### ✅ Funcionando
- Dropdown provincia → ciudad con datos reales de Dropi (23 provincias, todas las ciudades)
- Carga automática de variaciones desde WordPress
- Fallback manual si CORS bloquea la conexión directa
- Color picker para botón CTA, fondo de página y fondo del formulario
- Foto por pack con vista previa
- Campos opcionales con toggle
- Descarga del formulario listo para WordPress

### ❌ Pendiente — acordado para implementar

**1. Soporte para múltiples atributos (Color + Talla)**

El constructor actualmente solo mapea un atributo como "color" y otro como "pack".
Cuando el producto tiene Color + Talla (dos atributos visuales, ninguno es pack),
solo carga uno y descarta el otro.

Hay que agregar soporte para que aparezcan ambos selectores en el formulario:
un selector de color (con swatches de color) y un selector de talla (con dropdown o pills).

**2. Nuevo estilo de formulario — estilo "oferta"**

El usuario tiene una referencia visual que quiere implementar como segundo estilo:
- Tarjetas horizontales con foto del producto a la izquierda
- Texto "ADQUIERE 2 / 3 / 4" en negrita
- Precio original tachado + precio de oferta grande en verde/rojo
- Badge personalizable ("Más Vendido", "Super!!", "Increíble!!")
- Resumen debajo: Subtotal, Descuento, Envío gratis, Total
- Borde de color al seleccionar la tarjeta

En el constructor habría un selector de estilo: "Estilo clásico" vs "Estilo oferta".

---

## Cómo retomar el trabajo

1. Copia todos los archivos de este proyecto a tu carpeta de trabajo
2. Abre `constructor_formulario.html` para revisar el estado actual
3. Los dos cambios pendientes están descritos arriba en detalle
4. El dominio real del usuario es `vidaydetalledl.com`
5. El producto activo de prueba tiene ID `116`

**Instrucción para Claude:**
> Tengo un constructor de formularios HTML para dropshipping con Dropi Ecuador. 
> Te adjunto el código del constructor actual y el documento de traspaso del proyecto. 
> Necesito implementar los dos cambios pendientes descritos en el documento: 
> (1) soporte para múltiples atributos Color + Talla, y 
> (2) un nuevo estilo de formulario tipo "oferta" con tarjetas horizontales. 
> Explícame primero cómo lo harás y espera mi confirmación antes de modificar el código.

---

*Proyecto: ecosistema de herramientas de dropshipping automatizado*
*Dominio: vidaydetalledl.com — Mayo 2026*
