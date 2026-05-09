// Actualiza el workflow para usar _api_key dinámico del payload
// Cambios:
//  1. "Preparar Prompt Copy" → pasa _api_key al siguiente nodo
//  2. "Llamar Claude API"    → usa x-api-key dinámico del payload
//  3. "Llamar Claude Seccion"→ ídem
//  4. "Agente Director"      → ídem

const https = require('https');
const N8N_HOST   = 'duallegacy-ia-asistentes-n8n.aigmej.easypanel.host';
const API_KEY    = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2YjliYWZhMS1kYmJmLTQ1NjQtOTg3Ni1lNzExYTRlNTRlYTEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiYTI0MjJiMjAtODVmYi00ZmVmLWI3NDYtMzFmNzYwMWZmOTZmIiwiaWF0IjoxNzc3OTg0ODk4LCJleHAiOjE3ODA1NDU2MDB9.u2zEfY4cItgCMOPWAIkbKkFQWR71bXJS13Epr06kslo';
const WORKFLOW_ID = 'I0ODMByGg8uh9Mao';

function apiRequest(method, path, body) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const req = https.request({
      hostname: N8N_HOST, path, method,
      headers: {
        'X-N8N-API-KEY': API_KEY,
        'Content-Type': 'application/json',
        ...(data ? { 'Content-Length': Buffer.byteLength(data) } : {})
      }
    }, res => {
      let raw = '';
      res.on('data', d => raw += d);
      res.on('end', () => {
        try { resolve({ status: res.statusCode, body: JSON.parse(raw) }); }
        catch(e) { resolve({ status: res.statusCode, body: raw }); }
      });
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

// ── Nuevo código para "Preparar Prompt Copy" ────────────────
// Idéntico al actual pero agrega _api_key al output
const NEW_PREPARAR_CODE = `
const input = $input.first().json;
const body = input.body || input;

const accion = body.accion || 'generar_copy';
const nombre_producto = body.nombre_producto || body.producto || '';
const descripcion = body.descripcion || '';
const beneficios = body.beneficios || '';
const publico_objetivo = body.publico_objetivo || '';
const tono = body.tono || 'profesional';
const copy_actual = body.copy_actual || null;
const seccion_regenerar = body.seccion || body.seccion_regenerar || null;
const instrucciones = body.instrucciones || '';
const cantidad_reviews = Math.min(Math.max(parseInt(body.cantidad_reviews) || 3, 1), 20);
const _api_key = body._api_key || '';

let secciones = body.secciones || ['hero', 'problema', 'beneficios', 'reviews', 'cta_final'];

if (typeof secciones === 'string') {
  try { secciones = JSON.parse(secciones); } catch (e) { secciones = secciones.split(',').map(s => s.trim()); }
}

const secs = Array.isArray(secciones)
  ? secciones.map(s => { if (typeof s === 'object' && s !== null) return s.id || s.value || s.name; return s; }).filter(Boolean)
  : [];

const normalizedSecs = secs.map(sec => sec === 'testimonios' ? 'reviews' : sec);

const reviewItem = '{"name": "", "stars": 5, "comment": ""}';
const reviewsArray = Array.from({length: cantidad_reviews}, () => reviewItem).join(', ');

const fmts = {
  hero: '"hero": {"titulo": "", "sub": "", "badge": "", "cta": "", "precio": "", "precio_antes": ""}',
  problema: '"problema": {"titulo": "", "desc": ""}',
  beneficios: '"beneficios": [{"e": "✅", "t": "", "d": ""}, {"e": "🚀", "t": "", "d": ""}, {"e": "💡", "t": "", "d": ""}]',
  reviews: \`"reviews": [\${reviewsArray}]\`,
  video: '"video": {"titulo": "", "sub": ""}',
  cta_final: '"cta_final": {"titulo": "", "sub": "", "btn": "", "escasez": ""}',
  popup_social: '"popup_social": {"messages": ["🛒 Nombre de Ciudad acaba de comprar", "⭐ Nombre: Me encanta el producto, ya lo recibí", "🔥 Nombre de Ciudad lo acaba de ordenar", "💬 Nombre: Excelente calidad, lo recomiendo", "🛍️ Nombre de Ciudad acaba de hacer su pedido", "✅ Nombre: Llegó en perfectas condiciones", "🎉 Nombre de Ciudad acaba de unirse", "⚡ Nombre: No lo pensé dos veces, vale la pena", "💛 Nombre de Ciudad acaba de confirmar su compra", "🏆 Nombre: El mejor producto que he comprado"], "cta": ""}',
  faq: '"faq": [{"q": "", "a": ""}, {"q": "", "a": ""}]',
  garantia: '"garantia": {"titulo": "", "desc": ""}'
};

const activeKeys = ['hero'];
for (const sec of normalizedSecs) {
  if (sec !== 'hero' && fmts[sec]) activeKeys.push(sec);
}

const activeFmts = activeKeys.map(key => fmts[key]);
const jsonExample = '{\\n  ' + activeFmts.join(',\\n  ') + '\\n}';

const pais_nombre = body.pais_nombre || '';
let prompt = '';

if (accion === 'generar_ads') {
  prompt = \`
Eres experto en publicidad digital de alto rendimiento para Meta Ads (Facebook/Instagram) y TikTok Ads.

Producto: \${nombre_producto}
Descripción: \${descripcion}
Beneficios clave: \${beneficios}
Público objetivo: \${publico_objetivo}
Tono: \${tono}
País/Región: \${pais_nombre || 'Latinoamérica'}
\${instrucciones ? 'Instrucciones adicionales:\\n' + instrucciones : ''}

Genera 3 variantes de copy para Meta Ads y 3 para TikTok Ads.
Responde SOLO con JSON válido, sin markdown ni explicación.

Formato exacto:
{
  "meta": [
    {"titular": "", "texto_principal": "", "descripcion": "", "cta": ""},
    {"titular": "", "texto_principal": "", "descripcion": "", "cta": ""},
    {"titular": "", "texto_principal": "", "descripcion": "", "cta": ""}
  ],
  "tiktok": [
    {"hook": "", "copy": "", "cta": "", "hashtags": ["", "", "", "", ""]},
    {"hook": "", "copy": "", "cta": "", "hashtags": ["", "", "", "", ""]},
    {"hook": "", "copy": "", "cta": "", "hashtags": ["", "", "", "", ""]}
  ]
}

Reglas:
- meta.titular: máximo 40 caracteres
- meta.texto_principal: 100-150 caracteres
- meta.descripcion: máximo 30 caracteres
- meta.cta: 1-4 palabras
- tiktok.hook: primeros 2-3 segundos, genera pausa inmediata
- tiktok.copy: conversacional, emojis, máximo 150 caracteres
- tiktok.cta: máximo 8 palabras
- tiktok.hashtags: exactamente 5 hashtags
- Cada variante con un ángulo diferente: dolor, resultado, urgencia, curiosidad, social proof
- Todo en español del país indicado
\`;
  return [{json: {prompt, accion, nombre_producto, _api_key}}];
}

if (accion === 'regenerar_seccion' && seccion_regenerar && copy_actual) {
  const secKey = seccion_regenerar === 'testimonios' ? 'reviews' : seccion_regenerar;
  const fmt = fmts[secKey] || ('"' + secKey + '": {}');
  const regenCount = secKey === 'reviews' ? cantidad_reviews : null;

  prompt = \`
Eres experto en copywriting para landing pages de alta conversión.

Producto: \${nombre_producto}
Tono: \${tono}
\${instrucciones ? 'Instrucciones adicionales:\\n' + instrucciones : ''}

Copy actual:
\${JSON.stringify(copy_actual)}

Regenera SOLO la sección "\${secKey}"\${regenCount ? ' con exactamente ' + regenCount + ' elementos' : ''}.

Responde únicamente con JSON válido, sin markdown, sin explicación y sin bloque de código.

Formato obligatorio:
{
  \${fmt}
}
\`;
} else {
  prompt = \`
Eres experto en copywriting para landing pages de alta conversión.

Producto: \${nombre_producto}
Descripción: \${descripcion}
Beneficios: \${beneficios}
Público objetivo: \${publico_objetivo}
Tono: \${tono}
\${instrucciones ? '\\nInstrucciones adicionales:\\n' + instrucciones : ''}

Responde únicamente con un objeto JSON válido.
No uses markdown. No uses bloque de código. No agregues explicación.

Formato obligatorio:
\${jsonExample}

Reglas:
- "reviews" DEBE tener EXACTAMENTE \${cantidad_reviews} objetos.
- "beneficios" debe ser array con objetos: {"e":"emoji", "t":"titulo", "d":"descripcion"}.
- Los precios deben ir vacíos "" salvo que el usuario los haya especificado.
\`;
}

return [{
  json: {
    prompt, accion, nombre_producto, cantidad_reviews,
    secciones: normalizedSecs,
    _api_key
  }
}];
`;

// ── Parámetros actualizados para nodos HTTP que llaman a Claude ─
function buildHttpNodeParams(jsonBody) {
  return {
    method: 'POST',
    url: 'https://api.anthropic.com/v1/messages',
    authentication: 'none',
    sendHeaders: true,
    headerParameters: {
      parameters: [
        { name: 'x-api-key',          value: '={{ $json._api_key }}' },
        { name: 'anthropic-version',   value: '2023-06-01' },
        { name: 'Content-Type',        value: 'application/json' },
      ]
    },
    sendBody: true,
    specifyBody: 'json',
    jsonBody,
    options: {}
  };
}

async function main() {
  console.log('⬇️  Obteniendo workflow...');
  const wf = await apiRequest('GET', `/api/v1/workflows/${WORKFLOW_ID}`);
  if (wf.status !== 200) { console.error('Error GET:', wf.status); process.exit(1); }
  const workflow = wf.body;

  let changes = 0;

  // 1. Actualizar "Preparar Prompt Copy"
  const idxPrep = workflow.nodes.findIndex(n => n.name === 'Preparar Prompt Copy');
  if (idxPrep !== -1) {
    workflow.nodes[idxPrep].parameters.jsCode = NEW_PREPARAR_CODE;
    console.log('✓ "Preparar Prompt Copy" → ahora pasa _api_key');
    changes++;
  } else {
    console.warn('⚠️  "Preparar Prompt Copy" no encontrado');
  }

  // 2. Actualizar "Llamar Claude API"
  const idxClaude = workflow.nodes.findIndex(n => n.name === 'Llamar Claude API');
  if (idxClaude !== -1) {
    workflow.nodes[idxClaude].parameters = buildHttpNodeParams(
      "={{ JSON.stringify({ model: 'claude-haiku-4-5-20251001', max_tokens: 2000, messages: [{ role: 'user', content: $json.prompt }] }) }}"
    );
    delete workflow.nodes[idxClaude].credentials;
    console.log('✓ "Llamar Claude API" → usa x-api-key dinámico');
    changes++;
  } else {
    console.warn('⚠️  "Llamar Claude API" no encontrado');
  }

  // 3. Actualizar "Llamar Claude Seccion"
  const idxSec = workflow.nodes.findIndex(n => n.name === 'Llamar Claude Seccion');
  if (idxSec !== -1) {
    workflow.nodes[idxSec].parameters = buildHttpNodeParams(
      "={{ JSON.stringify({ model: 'claude-haiku-4-5-20251001', max_tokens: 3000, system: $json.instructions, messages: [{ role: 'user', content: $json.user_prompt }] }) }}"
    );
    delete workflow.nodes[idxSec].credentials;
    console.log('✓ "Llamar Claude Seccion" → usa x-api-key dinámico');
    changes++;
  } else {
    console.warn('⚠️  "Llamar Claude Seccion" no encontrado');
  }

  // 4. Actualizar "Agente Director"
  const idxDir = workflow.nodes.findIndex(n => n.name === 'Agente Director');
  if (idxDir !== -1) {
    workflow.nodes[idxDir].parameters = buildHttpNodeParams(
      '={{ $json._director_request }}'
    );
    delete workflow.nodes[idxDir].credentials;
    console.log('✓ "Agente Director" → usa x-api-key dinámico');
    changes++;
  } else {
    console.warn('⚠️  "Agente Director" no encontrado');
  }

  if (changes === 0) { console.error('❌ No se actualizó ningún nodo'); process.exit(1); }

  // Guardar workflow
  const payload = {
    name: workflow.name,
    nodes: workflow.nodes,
    connections: workflow.connections,
    settings: { executionOrder: workflow.settings?.executionOrder || 'v1' },
    staticData: workflow.staticData || null,
    pinData: workflow.pinData || {}
  };

  console.log(`\n⬆️  Guardando ${changes} cambios...`);
  const result = await apiRequest('PUT', `/api/v1/workflows/${WORKFLOW_ID}`, payload);

  if (result.status === 200) {
    console.log('✅ Workflow actualizado. Todos los nodos ahora usan _api_key del payload.');
  } else {
    console.error('❌ Error al guardar:', result.status, JSON.stringify(result.body).substring(0, 400));
  }
}

main().catch(console.error);
