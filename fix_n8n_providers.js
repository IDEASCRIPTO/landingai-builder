// Corrige los nodos de Claude en n8n:
// - Reemplaza Code nodes rotos con el patrón: Code(prepara datos) → HTTP Request(llama API)
// - Soporta Anthropic, OpenAI y Gemini según _provider del payload

const https = require('https');
const N8N_HOST    = 'duallegacy-ia-asistentes-n8n.aigmej.easypanel.host';
const API_KEY     = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2YjliYWZhMS1kYmJmLTQ1NjQtOTg3Ni1lNzExYTRlNTRlYTEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiYTI0MjJiMjAtODVmYi00ZmVmLWI3NDYtMzFmNzYwMWZmOTZmIiwiaWF0IjoxNzc3OTg0ODk4LCJleHAiOjE3ODA1NDU2MDB9.u2zEfY4cItgCMOPWAIkbKkFQWR71bXJS13Epr06kslo';
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

// ─────────────────────────────────────────────────────────────
// Code node: prepara URL, headers y body según el proveedor
// No hace HTTP — solo arma los datos para el siguiente nodo
// ─────────────────────────────────────────────────────────────
const PREP_COPY_CODE = `
const inp      = $input.first().json;
const provider = inp._provider || 'anthropic';
const apiKey   = inp._api_key  || '';
const prompt   = inp.prompt    || '';

if (!apiKey) {
  return [{ json: { error: 'API key no configurada', success: false } }];
}

let url, xkey, auth, bodyStr;

if (provider === 'openai') {
  url     = 'https://api.openai.com/v1/chat/completions';
  xkey    = '';
  auth    = 'Bearer ' + apiKey;
  bodyStr = JSON.stringify({ model: 'gpt-4o-mini', max_tokens: 4000,
    messages: [{ role: 'user', content: prompt }] });

} else if (provider === 'gemini') {
  url     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' + apiKey;
  xkey    = '';
  auth    = '';
  bodyStr = JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] });

} else {
  url     = 'https://api.anthropic.com/v1/messages';
  xkey    = apiKey;
  auth    = '';
  bodyStr = JSON.stringify({ model: 'claude-haiku-4-5-20251001', max_tokens: 4000,
    messages: [{ role: 'user', content: prompt }] });
}

return [{ json: { ...inp, _url: url, _xkey: xkey, _auth: auth, _body: bodyStr } }];
`;

const PREP_SECCION_CODE = `
const inp      = $input.first().json;
const provider = inp._provider || 'anthropic';
const apiKey   = inp._api_key  || '';
const prompt   = inp.user_prompt   || '';
const system   = inp.instructions  || '';

if (!apiKey) {
  return [{ json: { error: 'API key no configurada', success: false } }];
}

let url, xkey, auth, bodyStr;

if (provider === 'openai') {
  url     = 'https://api.openai.com/v1/chat/completions';
  xkey    = '';
  auth    = 'Bearer ' + apiKey;
  bodyStr = JSON.stringify({ model: 'gpt-4o-mini', max_tokens: 4000,
    messages: [{ role: 'system', content: system }, { role: 'user', content: prompt }] });

} else if (provider === 'gemini') {
  url     = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' + apiKey;
  xkey    = '';
  auth    = '';
  bodyStr = JSON.stringify({ contents: [{ parts: [{ text: 'System: ' + system + '\\n' + prompt }] }] });

} else {
  url     = 'https://api.anthropic.com/v1/messages';
  xkey    = apiKey;
  auth    = '';
  bodyStr = JSON.stringify({ model: 'claude-haiku-4-5-20251001', max_tokens: 4000,
    system: system, messages: [{ role: 'user', content: prompt }] });
}

return [{ json: { ...inp, _url: url, _xkey: xkey, _auth: auth, _body: bodyStr } }];
`;

// ─────────────────────────────────────────────────────────────
// HTTP Request node que consume los datos preparados
// ─────────────────────────────────────────────────────────────
function makeHttpNode(name, position) {
  return {
    name,
    type: 'n8n-nodes-base.httpRequest',
    typeVersion: 4.2,
    position,
    parameters: {
      method: 'POST',
      url: '={{ $json._url }}',
      authentication: 'none',
      sendHeaders: true,
      headerParameters: {
        parameters: [
          { name: 'x-api-key',        value: '={{ $json._xkey }}' },
          { name: 'Authorization',     value: '={{ $json._auth }}' },
          { name: 'anthropic-version', value: '2023-06-01' },
          { name: 'Content-Type',      value: 'application/json' },
        ]
      },
      sendBody: true,
      specifyBody: 'string',
      body: '={{ $json._body }}',
      options: {}
    }
  };
}

// ─────────────────────────────────────────────────────────────
// Parsear respuesta normalizada (Anthropic / OpenAI / Gemini)
// ─────────────────────────────────────────────────────────────
const PARSEAR_CODE = `
const data = $input.first().json;

// Normalizar respuesta de los 3 proveedores
let text = '';
if (data.choices) {
  // OpenAI
  text = data.choices[0]?.message?.content || '';
} else if (data.candidates) {
  // Gemini
  text = data.candidates[0]?.content?.parts[0]?.text || '';
} else {
  // Anthropic
  text = data.content?.[0]?.text || '';
}

let copy = null;
try { copy = JSON.parse(text); } catch(e) {
  const m = text.match(/\\{[\\s\\S]*\\}/);
  if (m) { try { copy = JSON.parse(m[0]); } catch(e2) { copy = { raw: text }; } }
  else { copy = { raw: text }; }
}
return [{ json: { copy, raw_text: text } }];
`;

async function main() {
  console.log('⬇️  Obteniendo workflow...');
  const wfResp = await apiRequest('GET', `/api/v1/workflows/${WORKFLOW_ID}`);
  if (wfResp.status !== 200) { console.error('Error:', wfResp.status); process.exit(1); }
  const wf = wfResp.body;

  // ── 1. Reparar "Llamar Claude API" ──────────────────────────
  const idxCopy = wf.nodes.findIndex(n => n.name === 'Llamar Claude API');
  if (idxCopy !== -1) {
    const pos = wf.nodes[idxCopy].position || [0, 0];
    // Convertir en Code node de preparación
    wf.nodes[idxCopy] = {
      name: 'Llamar Claude API',
      id:   wf.nodes[idxCopy].id,
      type: 'n8n-nodes-base.code',
      typeVersion: 2,
      position: pos,
      parameters: { mode: 'runOnceForEachItem', jsCode: PREP_COPY_CODE }
    };
    // Agregar HTTP Request node a continuación
    const httpId = 'llamar_claude_http';
    wf.nodes.push(makeHttpNode('Llamar Claude API HTTP', [pos[0] + 220, pos[1]]));
    // Conectar: Llamar Claude API → Llamar Claude API HTTP
    if (!wf.connections['Llamar Claude API']) wf.connections['Llamar Claude API'] = {};
    wf.connections['Llamar Claude API'].main = [[{ node: 'Llamar Claude API HTTP', type: 'main', index: 0 }]];
    // Redirigir lo que antes conectaba desde HTTP al parseo
    // El "Parsear Respuesta Claude" ahora recibe de "Llamar Claude API HTTP"
    if (!wf.connections['Llamar Claude API HTTP']) wf.connections['Llamar Claude API HTTP'] = {};
    // Copiar conexiones que salían del antiguo "Llamar Claude API"
    // (las que van a Parsear Respuesta Claude) ya existen en wf.connections
    // Solo necesitamos que el HTTP node conecte al parseo
    const oldConns = JSON.parse(JSON.stringify(wf.connections['Llamar Claude API']?.main || []));
    // oldConns ahora apunta al HTTP node, así que copiamos las conexiones
    // del antiguo nodo al nuevo HTTP node
    // Buscar qué nodo seguía originalmente a "Llamar Claude API"
    // Como cambiamos el Code a Prep, sus conns ahora apuntan al HTTP node
    // Y el HTTP node debe conectar a Parsear Respuesta Claude
    const parsearIdx = wf.nodes.findIndex(n => n.name === 'Parsear Respuesta Claude');
    if (parsearIdx !== -1) {
      wf.connections['Llamar Claude API HTTP'].main = [[{ node: 'Parsear Respuesta Claude', type: 'main', index: 0 }]];
    }
    console.log('✓ "Llamar Claude API" → Code(Prep) + HTTP Request');
  }

  // ── 2. Reparar "Llamar Claude Seccion" ──────────────────────
  const idxSec = wf.nodes.findIndex(n => n.name === 'Llamar Claude Seccion');
  if (idxSec !== -1) {
    const pos = wf.nodes[idxSec].position || [0, 0];
    // Guardar lo que conectaba después de este nodo
    const afterConns = wf.connections['Llamar Claude Seccion']?.main || [];
    wf.nodes[idxSec] = {
      name: 'Llamar Claude Seccion',
      id:   wf.nodes[idxSec].id,
      type: 'n8n-nodes-base.code',
      typeVersion: 2,
      position: pos,
      parameters: { mode: 'runOnceForEachItem', jsCode: PREP_SECCION_CODE }
    };
    wf.nodes.push(makeHttpNode('Llamar Claude Seccion HTTP', [pos[0] + 220, pos[1]]));
    wf.connections['Llamar Claude Seccion'].main = [[{ node: 'Llamar Claude Seccion HTTP', type: 'main', index: 0 }]];
    wf.connections['Llamar Claude Seccion HTTP'] = { main: afterConns };
    console.log('✓ "Llamar Claude Seccion" → Code(Prep) + HTTP Request');
  }

  // ── 3. Reparar "Parsear Respuesta Claude" (normaliza 3 proveedores) ──
  const idxParse = wf.nodes.findIndex(n => n.name === 'Parsear Respuesta Claude');
  if (idxParse !== -1) {
    wf.nodes[idxParse].parameters.jsCode = PARSEAR_CODE;
    console.log('✓ "Parsear Respuesta Claude" → normaliza Anthropic + OpenAI + Gemini');
  }

  // ── 4. Reparar "Agente Director" ────────────────────────────
  const idxDir = wf.nodes.findIndex(n => n.name === 'Agente Director');
  if (idxDir !== -1) {
    wf.nodes[idxDir] = {
      ...wf.nodes[idxDir],
      type: 'n8n-nodes-base.httpRequest',
      typeVersion: 4.2,
      parameters: {
        method: 'POST',
        url: 'https://api.anthropic.com/v1/messages',
        authentication: 'none',
        sendHeaders: true,
        headerParameters: { parameters: [
          { name: 'x-api-key',        value: "={{ $json._api_key || $('Webhook para copy').item.json.body._api_key }}" },
          { name: 'anthropic-version', value: '2023-06-01' },
          { name: 'Content-Type',      value: 'application/json' },
        ]},
        sendBody: true,
        specifyBody: 'string',
        body: '={{ $json._director_request }}',
        options: {}
      }
    };
    delete wf.nodes[idxDir].credentials;
    console.log('✓ "Agente Director" → HTTP Request con x-api-key dinámico');
  }

  // ── Guardar ──────────────────────────────────────────────────
  const payload = {
    name: wf.name, nodes: wf.nodes, connections: wf.connections,
    settings: { executionOrder: wf.settings?.executionOrder || 'v1' },
    staticData: wf.staticData || null, pinData: wf.pinData || {}
  };

  console.log('\n⬆️  Guardando workflow...');
  const result = await apiRequest('PUT', `/api/v1/workflows/${WORKFLOW_ID}`, payload);
  if (result.status === 200) {
    console.log('✅ Workflow corregido. Prueba generar copy ahora.');
  } else {
    console.error('❌ Error:', result.status, JSON.stringify(result.body).substring(0, 500));
  }
}

main().catch(console.error);
