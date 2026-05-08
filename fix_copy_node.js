// Actualiza el nodo "Preparar Prompt Copy" en n8n para respetar cantidad_reviews e instrucciones
const https = require('https');
const N8N_HOST = 'duallegacy-ia-asistentes-n8n.aigmej.easypanel.host';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2YjliYWZhMS1kYmJmLTQ1NjQtOTg3Ni1lNzExYTRlNTRlYTEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiYTI0MjJiMjAtODVmYi00ZmVmLWI3NDYtMzFmNzYwMWZmOTZmIiwiaWF0IjoxNzc3OTg0ODk4LCJleHAiOjE3ODA1NDU2MDB9.u2zEfY4cItgCMOPWAIkbKkFQWR71bXJS13Epr06kslo';
const WORKFLOW_ID = 'I0ODMByGg8uh9Mao';

function apiRequest(method, path, body) {
  return new Promise((resolve, reject) => {
    const data = body ? JSON.stringify(body) : null;
    const req = https.request({
      hostname: N8N_HOST, path, method,
      headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json', ...(data ? { 'Content-Length': Buffer.byteLength(data) } : {}) }
    }, res => {
      let raw = ''; res.on('data', d => raw += d);
      res.on('end', () => { try { resolve({ status: res.statusCode, body: JSON.parse(raw) }); } catch(e) { resolve({ status: res.statusCode, body: raw }); } });
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

const NEW_CODE = `
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

let secciones = body.secciones || ['hero', 'problema', 'beneficios', 'reviews', 'cta_final'];

if (typeof secciones === 'string') {
  try { secciones = JSON.parse(secciones); } catch (e) { secciones = secciones.split(',').map(s => s.trim()); }
}

const secs = Array.isArray(secciones)
  ? secciones.map(s => { if (typeof s === 'object' && s !== null) return s.id || s.value || s.name; return s; }).filter(Boolean)
  : [];

const normalizedSecs = secs.map(sec => sec === 'testimonios' ? 'reviews' : sec);

// Genera el template de reviews con la cantidad exacta solicitada
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
- meta.titular: máximo 40 caracteres, genera curiosidad o apunta al dolor principal
- meta.texto_principal: 100-150 caracteres, conversacional y con prueba social o resultado
- meta.descripcion: máximo 30 caracteres
- meta.cta: 1-4 palabras (Comprar ahora, Ver precio, Quiero esto, etc.)
- tiktok.hook: primeros 2-3 segundos, genera pausa inmediata. Empieza con: "POV:", "Si tienes [problema]...", "Nadie te dice que...", "Esto cambia todo si..."
- tiktok.copy: conversacional, emojis, máximo 150 caracteres
- tiktok.cta: máximo 8 palabras
- tiktok.hashtags: exactamente 5 hashtags sin espacios internos
- Cada variante debe tener un ángulo diferente: dolor, resultado, urgencia, curiosidad, social proof
- Todo en español del país indicado
\`;
  return [{json: {prompt, accion, nombre_producto}}];
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
No uses markdown.
No uses bloque de código.
No agregues explicación.
No agregues texto antes ni después.

Formato obligatorio (usa exactamente esta estructura):
\${jsonExample}

Reglas obligatorias:
- Usa exactamente las claves solicitadas.
- No cambies "reviews" por "testimonios".
- "beneficios" debe ser array con objetos: {"e":"emoji", "t":"titulo", "d":"descripcion"}.
- "reviews" DEBE tener EXACTAMENTE \${cantidad_reviews} objetos. Ni más, ni menos. Cada uno con: {"name":"nombre real", "stars":5, "comment":"testimonio convincente"}.
- "popup_social.messages" DEBE tener EXACTAMENTE 10 strings. Cada uno es un mensaje corto de prueba social con nombre real y ciudad del país del público objetivo. Usa emojis variados. Ejemplos: "🛒 María de Quito acaba de ordenar", "⭐ Carlos: Me encanta, ya lo recibí", "🔥 Ana de Lima acaba de comprar".
- Los precios deben ir vacíos "" salvo que el usuario los haya especificado.
- Cada texto debe estar orientado a venta y conversión.
\`;
}

return [{
  json: {
    prompt, accion, nombre_producto, cantidad_reviews,
    secciones: normalizedSecs,
    debug: {
      body_recibido: body,
      secciones_normalizadas: normalizedSecs,
      formato_generado: jsonExample
    }
  }
}];
`;

async function main() {
  const wf = await apiRequest('GET', `/api/v1/workflows/${WORKFLOW_ID}`);
  if (wf.status !== 200) { console.error('Error GET:', wf.status); process.exit(1); }
  const workflow = wf.body;

  const idx = workflow.nodes.findIndex(n => n.name === 'Preparar Prompt Copy');
  if (idx === -1) { console.error('❌ Nodo "Preparar Prompt Copy" no encontrado'); process.exit(1); }

  workflow.nodes[idx].parameters.jsCode = NEW_CODE;
  console.log('✓ Nodo encontrado, actualizando...');

  const payload = {
    name: workflow.name, nodes: workflow.nodes, connections: workflow.connections,
    settings: { executionOrder: workflow.settings?.executionOrder || 'v1' },
    staticData: workflow.staticData || null, pinData: workflow.pinData || {}
  };

  const result = await apiRequest('PUT', `/api/v1/workflows/${WORKFLOW_ID}`, payload);
  if (result.status === 200) {
    console.log('✅ Nodo "Preparar Prompt Copy" actualizado correctamente');
    console.log('  → cantidad_reviews: leído del payload, hasta 20 reviews');
    console.log('  → instrucciones: inyectadas en el prompt de Claude');
    console.log('  → Template reviews generado dinámicamente con N objetos exactos');
  } else {
    console.error('❌', result.status, JSON.stringify(result.body).substring(0, 500));
  }
}
main().catch(console.error);
