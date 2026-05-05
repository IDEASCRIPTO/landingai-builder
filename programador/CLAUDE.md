# Programador n8n — LandingAI Builder

Este workspace está dedicado a construir y mantener los workflows de n8n que alimentan el formulario LandingAI Builder. Usás el servidor MCP `n8n-mcp` para crear, validar y deployar workflows directamente desde Claude Code.

## Instancia n8n

```
URL: https://duallegacy-ia-asistentes-n8n.aigmej.easypanel.host
```

Para conectar el MCP server:
1. Copiá `.mcp.json.example` → `.mcp.json`
2. Reemplazá `TU_API_KEY_AQUI` con tu API Key de n8n
3. Para generar la API Key: n8n → Settings → API → Create API Key

> `.mcp.json` está en `.gitignore` — nunca se sube al repo.

## Workflows existentes

### 1. `generar-copy-v2` — Generador de copy con IA
**Webhook**: `POST /webhook/generar-copy-v2`

Payload de entrada:
```json
{
  "accion": "generar_copy",
  "nombre_producto": "string",
  "descripcion": "string",
  "beneficios": "string",
  "publico_objetivo": "string",
  "tono": "amigable | profesional | urgente | lujo | divertido | emocional",
  "instrucciones": "string (opcional)",
  "secciones": ["hero", "problema", "beneficios", "reviews", "cta_final", "faq", "garantia", "popup_social"]
}
```

Respuesta esperada:
```json
{
  "success": true,
  "copy": {
    "hero": "string",
    "problema": "string",
    "beneficios": "string",
    "reviews": "string",
    "cta_final": "string",
    "faq": "string",
    "garantia": "string",
    "popup_social": "string"
  }
}
```

También acepta `accion: "regenerar_seccion"` para regenerar una sección específica.

---

### 2. `generar-v2` — Generador de landing page (HTML y Liquid)
**Webhook**: `POST /webhook/generar-v2`

Payload de entrada:
```json
{
  "accion": "generar_html | generar_liquid",
  "tipo_salida": "html | shopify",
  "nombre_producto": "string",
  "precio": "string",
  "precio_antes": "string",
  "whatsapp": "string",
  "instrucciones": "string",
  "color_principal": "#hex",
  "fuente": "Inter | Poppins | Montserrat | ...",
  "copy_editado": { "hero": "...", "problema": "...", ... },
  "secciones_activas": ["hero", "problema", "beneficios", ...],
  "video_url": "string",
  "video_caption": "string",
  "imagenes_hero": ["url1", "url2"],
  "imagen_problema": "url",
  "imagenes_reviews": ["url1"]
}
```

Respuesta esperada:
```json
{
  "success": true,
  "html": "<!DOCTYPE html>...",
  "tipo_salida": "html | shopify"
}
```

---

## Secciones disponibles

| ID | Nombre | Por defecto |
|---|---|---|
| hero | Hero | ✅ siempre |
| problema | Problema | ✅ |
| beneficios | Beneficios | ✅ |
| reviews | Reviews | ✅ |
| cta_final | CTA Final | ✅ |
| video | Video | ❌ |
| faq | FAQ | ❌ |
| garantia | Garantía | ❌ |
| popup_social | Popup Social | ❌ |

---

## Modelos de IA disponibles en n8n

### Claude (Anthropic)
- Nodo: `@n8n/n8n-nodes-langchain.lmChatAnthropic`
- Modelos recomendados: `claude-sonnet-4-6`, `claude-haiku-4-5-20251001`
- Usar para: generación de copy, HTML estructurado, razonamiento complejo

### OpenAI
- Nodo: `@n8n/n8n-nodes-langchain.lmChatOpenAi`
- Modelos recomendados: `gpt-4o`, `gpt-4o-mini`
- Usar para: generación de imágenes (DALL-E), tareas rápidas

---

## Skills disponibles (n8n-mcp-skills v1.7.0)

Estos skills se activan automáticamente según el contexto:

| Skill | Cuándo se activa |
|---|---|
| `n8n-mcp-tools-expert` | **Siempre primero** — antes de usar cualquier herramienta MCP |
| `n8n-workflow-patterns` | Al diseñar o crear un nuevo workflow |
| `n8n-node-configuration` | Al configurar nodos (parámetros, operaciones) |
| `n8n-expression-syntax` | Al escribir expresiones `{{ }}` |
| `n8n-validation-expert` | Al interpretar errores de validación |
| `n8n-code-javascript` | Al escribir código en nodos Code (JS) |
| `n8n-code-python` | Al escribir código en nodos Code (Python) |

---

## Herramientas MCP principales

```
search_nodes          — buscar nodos por tipo o funcionalidad
get_node              — obtener schema completo de un nodo
validate_node         — validar configuración de un nodo
validate_workflow     — validar workflow completo
n8n_list_workflows    — listar todos los workflows
n8n_get_workflow      — obtener workflow por ID
n8n_create_workflow   — crear workflow nuevo
n8n_update_partial_workflow — editar nodos/conexiones (operación más usada)
n8n_autofix_workflow  — corregir errores automáticamente
n8n_test_workflow     — ejecutar prueba
n8n_executions        — ver historial de ejecuciones
n8n_manage_credentials — gestionar credenciales
search_templates      — buscar templates de la comunidad
```

---

## Convenciones de desarrollo

### Nombres de workflows
- Formato: `kebab-case` + versión → `generar-copy-v2`, `generar-v2`
- Nuevos workflows: incrementar versión → `generar-copy-v3`

### Estructura estándar de un webhook workflow
```
Webhook → Set (normalizar input) → IA Node → Code (formatear output) → Respond to Webhook
```

### Manejo de errores
- Todo workflow debe tener un nodo de error que devuelva `{ "success": false, "error": "mensaje" }`
- Usar `Error Trigger` para capturar fallos

### Output format
- Siempre devolver `{ "success": true/false, ... }`
- El proxy.php ya normaliza arrays → tomar `[0]` si n8n devuelve array

---

## Flujo de trabajo recomendado

1. **Diseñar** → describir el workflow en lenguaje natural
2. **Buscar nodos** → `search_nodes` para encontrar los correctos
3. **Crear** → `n8n_create_workflow` con estructura base
4. **Configurar** → `n8n_update_partial_workflow` nodo por nodo
5. **Validar** → `validate_workflow` + `n8n_autofix_workflow` si hay errores
6. **Probar** → `n8n_test_workflow`
7. **Exportar** → guardar JSON en `workflows/`

---

## Archivos de referencia

- `../index.html` — frontend que consume los webhooks
- `../proxy.php` — proxy que enruta las peticiones a n8n
- `workflows/` — exports JSON de los workflows
- `prompts/` — prompts de los nodos IA
