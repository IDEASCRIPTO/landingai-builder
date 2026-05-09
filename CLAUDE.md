# LandingAI Builder — Reglas críticas del sistema

## ⚠️ REGLA GENERAL: Proponer antes de implementar

Antes de cualquier cambio — código, configuración, n8n, Supabase, EasyPanel — siempre:
1. Proponer qué se va a cambiar
2. Indicar pros y contras
3. Esperar confirmación explícita del usuario antes de proceder

Esto aplica a todo, sin excepción, incluso si el cambio parece pequeño.

---

## ⚠️ Antes de modificar cualquier ítem marcado con 🔒, notificar al usuario y pedir confirmación explícita.

---

## 🔒 1. Flujo COPY_ACTIONS en proxy.php

**NO modificar** la lógica de `$COPY_ACTIONS` en `proxy.php` sin notificar.

**Regla:** Las acciones `generar_copy`, `regenerar_seccion` y `generar_ads` SIEMPRE son interceptadas por proxy.php. El flujo es:
1. proxy.php envía el payload a n8n → n8n devuelve **solo el prompt** (sin llamar a la IA)
2. proxy.php llama a la API de IA directamente con PHP curl

**Por qué:** El nodo HTTP Request de n8n v4.2 tiene un bug de double-encoding en el body cuando usa expresiones dinámicas (`={{ $json._body }}`). Cualquier intento de que n8n haga la llamada a la IA directamente falla con errores como "model: Field required" o "messages: Input should be a valid array".

**No hacer:**
- Reconectar el nodo `Preparar Prompt Copy` al nodo de llamada a la IA en n8n
- Eliminar el bloque `$COPY_ACTIONS` en proxy.php
- Mover la llamada a la IA de PHP de vuelta a n8n

---

## 🔒 2. Workflow n8n: generar-copy-v2 (ID: I0ODMByGg8uh9Mao)

**NO modificar** la conexión `Preparar Prompt Copy → Respond to Webhook` sin notificar.

**Regla:** Este workflow debe devolver **solo el prompt** al webhook. El nodo `Respond to Webhook` está conectado directamente después de `Preparar Prompt Copy`, saltándose todos los nodos de llamada a la IA.

**Por qué:** Ver punto 1. proxy.php es quien llama a la IA.

---

## 🔒 3. Variables de entorno en EasyPanel (PHP container)

Estas variables **deben estar configuradas** siempre. No eliminarlas ni renombrarlas:

| Variable | Descripción |
|---|---|
| `SUPABASE_URL` | URL del proyecto Supabase (`https://jsrbfuwjkqwzhjdsbicg.supabase.co`) |
| `SUPABASE_ANON_KEY` | Anon/public key de Supabase |
| `SUPABASE_SERVICE_ROLE_KEY` | Service role key (bypasa RLS para leer `api_keys`) |
| `ADMIN_EMAIL` | Email exacto del admin (usa keys del sistema, no de Supabase) |
| `ANTHROPIC_API_KEY` | API key de Anthropic del sistema (para el admin) |
| `OPENAI_API_KEY` | API key de OpenAI del sistema (para el admin) — opcional |
| `GEMINI_API_KEY` | API key de Gemini del sistema (para el admin) — opcional |

**Tras cualquier cambio en EasyPanel, reiniciar el container para que las variables tomen efecto.**

---

## 🔒 4. Tabla `api_keys` en Supabase

**NO eliminar** el constraint único ni las columnas existentes.

**Estado actual:**
- Columnas: `id`, `user_id`, `provider`, `key_enc`, `created_at`
- Constraint: `UNIQUE (user_id, provider)` — necesario para que el upsert funcione correctamente
- RLS: proxy.php usa `SUPABASE_SERVICE_ROLE_KEY` para bypasear RLS al leer keys

---

## 🔒 5. Lógica Admin vs Usuario en proxy.php

**Regla:** El admin es identificado por `ADMIN_EMAIL` env var. Si el email del usuario autenticado coincide:
- Usa `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `GEMINI_API_KEY` del sistema (env vars)

Si NO es admin:
- proxy.php lee la key del usuario desde la tabla `api_keys` en Supabase
- Si no tiene key guardada → error `NO_API_KEY` con mensaje de configuración

---

## 📋 Arquitectura general

```
Frontend (index.html)
  → proxy.php (PHP/EasyPanel)
      ├── Valida JWT con Supabase /auth/v1/user
      ├── Lee plan del usuario desde tabla `profiles`
      ├── COPY_ACTIONS (generar_copy, regenerar_seccion, generar_ads):
      │     ├── Llama n8n webhook → recibe SOLO el prompt
      │     └── Llama API de IA directamente (PHP curl)
      └── Otras acciones (generar_html, generar_liquid):
            └── Llama n8n webhook → n8n maneja todo
```

---

## 🐛 Bugs conocidos pendientes

- `generar_liquid`: El nodo `Llamar Claude Seccion HTTP` en n8n todavía tiene el bug de double-encoding. Prioridad baja — pendiente de fix usando el mismo patrón que COPY_ACTIONS.
