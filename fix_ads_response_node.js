// Actualiza "Code Limpiar Respuesta" para detectar y retornar ads copy
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

const NEW_LIMPIAR_CODE = `
const data = $input.first().json;
const copy = data.copy || {};
// Auto-detecta ads copy por claves meta/tiktok
if (Array.isArray(copy.meta) || Array.isArray(copy.tiktok)) {
  return [{ json: { success: true, ads: copy } }];
}
return [{ json: { success: true, copy } }];
`;

async function main() {
  const wf = await apiRequest('GET', `/api/v1/workflows/${WORKFLOW_ID}`);
  if (wf.status !== 200) { console.error('Error GET:', wf.status); process.exit(1); }
  const workflow = wf.body;

  const idx = workflow.nodes.findIndex(n => n.name === 'Code Limpiar Respuesta');
  if (idx === -1) { console.error('❌ Nodo "Code Limpiar Respuesta" no encontrado'); process.exit(1); }

  workflow.nodes[idx].parameters.jsCode = NEW_LIMPIAR_CODE;
  console.log('✓ Nodo encontrado, actualizando...');

  const payload = {
    name: workflow.name, nodes: workflow.nodes, connections: workflow.connections,
    settings: { executionOrder: workflow.settings?.executionOrder || 'v1' },
    staticData: workflow.staticData || null, pinData: workflow.pinData || {}
  };

  const result = await apiRequest('PUT', `/api/v1/workflows/${WORKFLOW_ID}`, payload);
  if (result.status === 200) {
    console.log('✅ Nodo "Code Limpiar Respuesta" actualizado');
    console.log('  → Detecta ads automáticamente si copy tiene claves meta/tiktok');
  } else {
    console.error('❌', result.status, JSON.stringify(result.body).substring(0, 500));
  }
}
main().catch(console.error);
