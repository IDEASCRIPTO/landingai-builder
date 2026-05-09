// Actualiza los workflows de n8n para usar _api_key dinámico del payload
// en lugar de las credenciales fijas de n8n.
// Reemplaza el nodo "Llamar Claude API" con un Code node que hace
// la llamada HTTP directamente usando la key recibida en el payload.

const https = require('https');
const N8N_HOST = 'duallegacy-ia-asistentes-n8n.aigmej.easypanel.host';
const API_KEY  = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2YjliYWZhMS1kYmJmLTQ1NjQtOTg3Ni1lNzExYTRlNTRlYTEiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiYTI0MjJiMjAtODVmYi00ZmVmLWI3NDYtMzFmNzYwMWZmOTZmIiwiaWF0IjoxNzc3OTg0ODk4LCJleHAiOjE3ODA1NDU2MDB9.u2zEfY4cItgCMOPWAIkbKkFQWR71bXJS13Epr06kslo';

// IDs de los workflows a actualizar
const WORKFLOWS = [
  { id: 'I0ODMByGg8uh9Mao', label: 'Copy (generar-copy-v2)' },
];

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

// Código del nuevo nodo que reemplaza "Llamar Claude API"
// Hace la llamada HTTP a la API de Anthropic usando _api_key del payload
const CLAUDE_CODE = `
const input = $input.first().json;
const apiKey = input._api_key || '';
const prompt = input.prompt || '';
const model  = 'claude-opus-4-5-20251101';

if (!apiKey) {
  return [{ json: { error: 'API key no configurada', success: false } }];
}

const body = JSON.stringify({
  model,
  max_tokens: 8000,
  messages: [{ role: 'user', content: prompt }]
});

const response = await new Promise((resolve, reject) => {
  const https = require('https');
  const req = https.request({
    hostname: 'api.anthropic.com',
    path: '/v1/messages',
    method: 'POST',
    headers: {
      'x-api-key': apiKey,
      'anthropic-version': '2023-06-01',
      'content-type': 'application/json',
      'content-length': Buffer.byteLength(body)
    }
  }, res => {
    let raw = '';
    res.on('data', d => raw += d);
    res.on('end', () => resolve({ status: res.statusCode, body: raw }));
  });
  req.on('error', reject);
  req.write(body);
  req.end();
});

if (response.status !== 200) {
  const errBody = JSON.parse(response.body || '{}');
  const errMsg  = errBody.error?.message || 'Error llamando a Claude API';
  return [{ json: { error: errMsg, status: response.status, success: false } }];
}

const parsed = JSON.parse(response.body);
const text   = parsed.content?.[0]?.text || '';
return [{ json: { content: [{ text }] } }];
`;

async function updateWorkflow(workflowId, label) {
  console.log(`\\n📋 Procesando: ${label} (${workflowId})`);

  const wf = await apiRequest('GET', `/api/v1/workflows/${workflowId}`);
  if (wf.status !== 200) { console.error('  ❌ Error GET:', wf.status); return; }

  const workflow = wf.body;

  // Buscar el nodo de Claude (puede llamarse "Llamar Claude API" u otro)
  const claudeNodeNames = ['Llamar Claude API', 'Claude', 'Anthropic', 'AI Call', 'Llamar IA'];
  let idx = -1;

  for (const name of claudeNodeNames) {
    idx = workflow.nodes.findIndex(n => n.name.toLowerCase().includes(name.toLowerCase()));
    if (idx !== -1) { console.log(`  ✓ Nodo encontrado: "${workflow.nodes[idx].name}"`); break; }
  }

  if (idx === -1) {
    // Mostrar todos los nodos para diagnóstico
    console.log('  ⚠️  Nodo de Claude no encontrado. Nodos existentes:');
    workflow.nodes.forEach(n => console.log(`     - ${n.name} (${n.type})`));
    return;
  }

  const oldNode = workflow.nodes[idx];

  // Reemplazar con Code node que usa _api_key dinámico
  workflow.nodes[idx] = {
    ...oldNode,
    type: 'n8n-nodes-base.code',
    typeVersion: 2,
    parameters: {
      mode: 'runOnceForEachItem',
      jsCode: CLAUDE_CODE
    },
    credentials: {}  // Sin credenciales fijas
  };

  delete workflow.nodes[idx].credentials;

  console.log(`  → Reemplazado por Code node con llamada HTTP dinámica`);

  const payload = {
    name: workflow.name,
    nodes: workflow.nodes,
    connections: workflow.connections,
    settings: { executionOrder: workflow.settings?.executionOrder || 'v1' },
    staticData: workflow.staticData || null,
    pinData: workflow.pinData || {}
  };

  const result = await apiRequest('PUT', `/api/v1/workflows/${workflowId}`, payload);
  if (result.status === 200) {
    console.log(`  ✅ Workflow actualizado correctamente`);
  } else {
    console.error(`  ❌ Error al guardar:`, result.status, JSON.stringify(result.body).substring(0, 300));
  }
}

async function main() {
  console.log('🔧 Actualizando workflows para usar API keys dinámicas...');
  for (const wf of WORKFLOWS) {
    await updateWorkflow(wf.id, wf.label);
  }
  console.log('\n✅ Proceso completado.');
  console.log('\n📝 Recuerda también actualizar el workflow generar-v2 (HTML).');
  console.log('   Para eso necesitas el ID del workflow HTML — búscalo en n8n y agrégalo al array WORKFLOWS.');
}

main().catch(console.error);
