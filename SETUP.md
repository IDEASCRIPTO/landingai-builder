# LandingAI Builder — Setup Guide

## 1. Supabase (Auth + Base de datos)

### Crear proyecto
1. Ve a [supabase.com](https://supabase.com) → New Project
2. Anota: **Project URL** y **anon public key** (Settings → API)

### Ejecutar schema
1. Supabase Dashboard → SQL Editor → New Query
2. Pega el contenido de `supabase-schema.sql` y ejecuta

### Conectar al frontend
Abre `index.html` y reemplaza (líneas ~120):
```javascript
const SUPABASE_URL  = 'https://TU_PROYECTO.supabase.co';
const SUPABASE_ANON = 'eyJhbGci_TU_ANON_KEY';
```

### Variables de entorno para proxy.php
En tu servidor (Docker / EasyPanel / cPanel), agrega:
```
SUPABASE_URL=https://TU_PROYECTO.supabase.co
SUPABASE_ANON_KEY=eyJhbGci_TU_ANON_KEY
```

---

## 2. Plan Free → límite de uso

El plan Free permite **5 generaciones/mes** (acciones `generar_html` o `generar_liquid`).
El límite se verifica en `proxy.php` y se incrementa con la función RPC `increment_usage`.

Para cambiar el límite, edita `proxy.php` línea ~90:
```php
if ($used >= 5) {  // ← cambia 5 por el número que quieras
```

---

## 3. Stripe (Monetización)

### Flujo de pago
1. Usuario hace clic en "Upgrade a Pro" → abre Stripe Checkout
2. Usuario paga → Stripe dispara webhook
3. Webhook llama a tu backend para actualizar `profiles.plan = 'pro'`

### Crear productos en Stripe
- Pro mensual: $X/mes
- Pro anual: $Y/año (descuento)

### Webhook endpoint (pendiente de implementar)
Crear `stripe-webhook.php` que:
1. Verifique la firma de Stripe (`STRIPE_WEBHOOK_SECRET`)
2. En evento `checkout.session.completed` → buscar usuario por email
3. Actualizar `profiles.plan = 'pro'` usando service_role key

---

## 4. Conectar API Keys propias (usuarios)

Los usuarios guardan sus API Keys en **Settings** (icono ⚙️ en la barra superior).
Estas keys se guardan en la tabla `api_keys` (Supabase).

**Proveedores soportados:** Anthropic, OpenAI, Gemini

Para que n8n use la key del usuario, el frontend envía la key en el payload y n8n la usa
en lugar de la key por defecto del sistema. *(Implementación pendiente en n8n workflows)*

---

## 5. Estructura de archivos

```
landingai-builder/
├── index.html              ← Frontend principal
├── proxy.php               ← Backend PHP (valida auth, llama n8n)
├── supabase-schema.sql     ← Esquema de base de datos
├── SETUP.md                ← Esta guía
├── node_generar_html.js    ← Código del nodo n8n (HTML generator)
├── fix_copy_node.js        ← Script para actualizar nodo copy en n8n
└── fix_ads_response_node.js ← Script para actualizar nodo ads en n8n
```

---

## 6. Checklist de lanzamiento

- [ ] Crear proyecto Supabase
- [ ] Ejecutar `supabase-schema.sql`
- [ ] Rellenar credenciales en `index.html`
- [ ] Configurar variables de entorno en servidor
- [ ] Crear cuenta Stripe + productos
- [ ] Implementar `stripe-webhook.php`
- [ ] Conectar dominio personalizado
- [ ] Configurar HTTPS en servidor
- [ ] Probar flujo completo: registro → generación → límite Free → upgrade
