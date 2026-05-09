<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Método no permitido']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['accion'])) {
    echo json_encode(['success'=>false,'error'=>'Payload inválido o falta accion']);
    exit;
}

/* ── AI DIRECTO: llama a la API del proveedor con PHP curl (evita bug n8n HTTP body) ── */
function callAiDirect(string $provider, string $apiKey, string $prompt): array {
    if ($provider === 'openai') {
        $url  = 'https://api.openai.com/v1/chat/completions';
        $body = json_encode(['model'=>'gpt-4o-mini','max_tokens'=>4000,'messages'=>[['role'=>'user','content'=>$prompt]]]);
        $hdrs = ['Content-Type: application/json','Authorization: Bearer '.$apiKey];
    } elseif ($provider === 'gemini') {
        $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey;
        $body = json_encode(['contents'=>[['parts'=>[['text'=>$prompt]]]]]);
        $hdrs = ['Content-Type: application/json'];
    } else {
        $url  = 'https://api.anthropic.com/v1/messages';
        $body = json_encode(['model'=>'claude-sonnet-4-6','max_tokens'=>4000,'messages'=>[['role'=>'user','content'=>$prompt]]]);
        $hdrs = ['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs,
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['__curl_error'=>$err];
    return json_decode($resp, true) ?: ['__curl_error'=>'Respuesta no es JSON'];
}

/* Misma lógica que el nodo n8n "Parsear Respuesta Claude" */
function parseAiCopyResponse(array $resp): array {
    if (isset($resp['__curl_error'])) return ['error'=>$resp['__curl_error']];
    if (isset($resp['error']) && !isset($resp['content']) && !isset($resp['choices']) && !isset($resp['candidates'])) {
        return ['error'=> $resp['error']['message'] ?? json_encode($resp['error'])];
    }
    $text = '';
    if (!empty($resp['choices'][0]['message'])) {
        $text = $resp['choices'][0]['message']['content'] ?? '';
    } elseif (!empty($resp['candidates'][0]['content']['parts'])) {
        $text = $resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
    } elseif (!empty($resp['content'][0])) {
        $text = $resp['content'][0]['text'] ?? '';
    }
    $copy = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $s = strpos($text, '{'); $e = strrpos($text, '}');
        if ($s !== false && $e > $s) $copy = json_decode(substr($text, $s, $e - $s + 1), true);
        if (!$copy) $copy = ['raw' => $text];
    }
    return ['copy' => $copy, 'raw_text' => $text];
}

/* ── SUPABASE AUTH ────────────────────────────────
   Configura tu proyecto Supabase:
   - SUPABASE_URL: URL de tu proyecto (ej: https://xxxx.supabase.co)
   - Si no está configurado, el proxy funciona sin auth (modo dev)
─────────────────────────────────────────────────── */
$SUPABASE_URL = getenv('SUPABASE_URL') ?: '';
$auth_enabled = !empty($SUPABASE_URL) && !str_contains($SUPABASE_URL, 'xxxx');
$auth_user_id = null;
$auth_plan    = 'free';

if ($auth_enabled && !empty($data['_auth_token'])) {
    $token = $data['_auth_token'];

    // Validar token con Supabase
    $ch = curl_init($SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'apikey: ' . (getenv('SUPABASE_ANON_KEY') ?: '')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['success'=>false,'error'=>'Sesión inválida. Inicia sesión nuevamente.','code'=>401]);
        exit;
    }

    $usr = json_decode($resp, true);
    $auth_user_id = $usr['id'] ?? null;

    // Obtener plan del usuario
    if ($auth_user_id) {
        $ch2 = curl_init($SUPABASE_URL . '/rest/v1/profiles?select=plan&id=eq.' . $auth_user_id);
        curl_setopt_array($ch2, [
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'apikey: ' . (getenv('SUPABASE_ANON_KEY') ?: ''),
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $profResp = curl_exec($ch2);
        curl_close($ch2);
        $profiles = json_decode($profResp, true);
        if (is_array($profiles) && !empty($profiles[0]['plan'])) {
            $auth_plan = $profiles[0]['plan'];
        }

        // Verificar límite plan Free
        if ($auth_plan === 'free') {
            $accion = $data['accion'] ?? '';
            if (in_array($accion, ['generar_html','generar_liquid'])) {
                $month = date('Y-m');
                $ch3 = curl_init($SUPABASE_URL . '/rest/v1/usage?select=count&user_id=eq.' . $auth_user_id . '&month=eq.' . $month);
                curl_setopt_array($ch3, [
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$token,'apikey: '.(getenv('SUPABASE_ANON_KEY')?:''),'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 6,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $usageResp = curl_exec($ch3);
                curl_close($ch3);
                $usageArr = json_decode($usageResp, true);
                $used = (is_array($usageArr) && !empty($usageArr[0])) ? (int)$usageArr[0]['count'] : 0;
                if ($used >= 5) {
                    echo json_encode(['success'=>false,'error'=>'Límite del plan Free alcanzado (5/mes). Haz upgrade a Pro para continuar.','upgrade'=>true]);
                    exit;
                }
            }
        }

        // Incrementar contador de uso
        $accion = $data['accion'] ?? '';
        if (in_array($accion, ['generar_html','generar_liquid']) && $auth_user_id) {
            $month = date('Y-m');
            $upsertData = json_encode(['user_id'=>$auth_user_id,'month'=>$month,'count'=>1,'updated_at'=>date('c')]);
            $ch4 = curl_init($SUPABASE_URL . '/rest/v1/usage');
            curl_setopt_array($ch4, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $upsertData,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer '.$token,
                    'apikey: '.(getenv('SUPABASE_ANON_KEY')?:''),
                    'Content-Type: application/json',
                    'Prefer: resolution=merge-duplicates',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            // Upsert: si existe suma 1, si no crea con count=1
            // Usamos RPC para incrementar atómicamente
            $rpcData = json_encode(['p_user_id'=>$auth_user_id,'p_month'=>$month]);
            curl_close($ch4);
            // Llamada RPC increment_usage
            $ch5 = curl_init($SUPABASE_URL . '/rest/v1/rpc/increment_usage');
            curl_setopt_array($ch5, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $rpcData,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer '.$token,
                    'apikey: '.(getenv('SUPABASE_ANON_KEY')?:''),
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            curl_exec($ch5);
            curl_close($ch5);
        }
    }
} elseif ($auth_enabled && empty($data['_auth_token'])) {
    // Auth habilitado pero no hay token
    echo json_encode(['success'=>false,'error'=>'Autenticación requerida.','code'=>401]);
    exit;
}

// Limpiar campos de auth del payload antes de enviarlo a n8n
unset($data['_auth_token'], $data['_user_id']);

/* ── API KEY ROUTING ──────────────────────────────────────────
   ADMIN_EMAIL → dueño, usa keys del sistema (env vars de EasyPanel)
   Otros       → deben tener su propia key del proveedor elegido
   Proveedores soportados: anthropic, openai, gemini
─────────────────────────────────────────────────────────────── */
$ADMIN_EMAIL   = getenv('ADMIN_EMAIL') ?: '';
$AI_ACTIONS    = ['generar_copy','regenerar_seccion','generar_ads','generar_html','generar_liquid'];
$accion_actual = $data['accion'] ?? '';
$provider      = in_array($data['_provider'] ?? '', ['anthropic','openai','gemini'])
                    ? $data['_provider']
                    : 'anthropic';
unset($data['_provider']);

$SYSTEM_KEYS = [
    'anthropic' => getenv('ANTHROPIC_API_KEY') ?: '',
    'openai'    => getenv('OPENAI_API_KEY')    ?: '',
    'gemini'    => getenv('GEMINI_API_KEY')    ?: '',
];

if (in_array($accion_actual, $AI_ACTIONS)) {
    $user_email = $auth_user_id ? ($usr['email'] ?? '') : '';
    $is_admin   = !empty($ADMIN_EMAIL) && strtolower($user_email) === strtolower($ADMIN_EMAIL);

    if ($is_admin || !$auth_enabled) {
        // Admin o modo dev: usa keys del sistema
        $data['_api_key']  = $SYSTEM_KEYS[$provider];
        $data['_provider'] = $provider;
    } elseif ($auth_enabled && $auth_user_id && !empty($token)) {
        // Usuario normal: buscar su key del proveedor elegido en Supabase
        // Usa service_role si está disponible (bypass RLS); si no, usa el JWT del usuario
        $serviceRoleKey = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
        $sbAuthHeader   = $serviceRoleKey
            ? 'Bearer ' . $serviceRoleKey
            : 'Bearer ' . $token;
        $sbApiKey = $serviceRoleKey ?: (getenv('SUPABASE_ANON_KEY') ?: '');

        $chK = curl_init($SUPABASE_URL . '/rest/v1/api_keys?select=key_enc&user_id=eq.' . $auth_user_id . '&provider=eq.' . $provider . '&limit=1');
        curl_setopt_array($chK, [
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $sbAuthHeader,
                'apikey: ' . $sbApiKey,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $keyResp = curl_exec($chK);
        $keyCurlErr = curl_error($chK);
        curl_close($chK);
        $keyArr       = json_decode($keyResp, true);
        $user_api_key = (is_array($keyArr) && !empty($keyArr[0]['key_enc'])) ? $keyArr[0]['key_enc'] : '';

        if (empty($user_api_key)) {
            $provName = ['anthropic'=>'Anthropic','openai'=>'OpenAI','gemini'=>'Gemini'][$provider] ?? $provider;
            // Debug: incluir respuesta de Supabase para diagnosticar el problema
            $sbDebug = $keyCurlErr ?: (substr($keyResp ?: '', 0, 300));
            echo json_encode([
                'success'  => false,
                'error'    => 'Necesitas configurar tu API key de ' . $provName . ' en Configuración (⚙️).',
                'code'     => 'NO_API_KEY',
                'provider' => $provider,
                '_debug'   => $sbDebug,
            ]);
            exit;
        }
        $data['_api_key']  = $user_api_key;
        $data['_provider'] = $provider;
    }
}

$base = 'https://duallegacy-ia-asistentes-n8n.aigmej.easypanel.host/webhook/';

$routes = [
    'generar_copy'          => $base . 'generar-copy-v2',
    'regenerar_seccion'     => $base . 'generar-copy-v2',
    'generar_ads'           => $base . 'generar-copy-v2',
    'generar_imagen'        => $base . 'generar-imagen-v1',
    'generar_html'          => $base . 'generar-v2',
    'generar_liquid'        => $base . 'generar-v2',
    'generar_prompt_imagen' => $base . 'generar-prompt-imagen',
];

$accion = $data['accion'];
if (!isset($routes[$accion])) {
    echo json_encode(['success'=>false,'error'=>'Acción no permitida: '.$accion]);
    exit;
}

set_time_limit(300);
$url = $routes[$accion];

/* ── ACCIONES COPY: n8n genera el prompt, PHP llama AI directamente ─────────────
   n8n ya no tiene acceso al HTTP body dinámico por un bug de n8n (double-encoding).
   Solución: n8n devuelve solo el prompt, proxy.php hace la llamada AI con curl.
──────────────────────────────────────────────────────────────────────────────── */
$COPY_ACTIONS = ['generar_copy', 'regenerar_seccion', 'generar_ads'];
if (in_array($accion_actual, $COPY_ACTIONS)) {
    $apiKey     = $data['_api_key'] ?? '';
    $aiProvider = $data['_provider'] ?? $provider;

    if (empty($apiKey)) {
        $provName = ['anthropic'=>'Anthropic','openai'=>'OpenAI','gemini'=>'Gemini'][$aiProvider] ?? $aiProvider;
        echo json_encode([
            'success'  => false,
            'error'    => 'Necesitas configurar tu API key de ' . $provName . ' en Configuración (⚙️).',
            'code'     => 'NO_API_KEY',
            'provider' => $aiProvider,
        ]);
        exit;
    }

    // Paso 1: pedir prompt a n8n (no llama AI, solo genera el prompt)
    $ch1 = curl_init($url);
    curl_setopt_array($ch1, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($data),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $n8nRaw  = curl_exec($ch1);
    $n8nErr  = curl_error($ch1);
    curl_close($ch1);

    if ($n8nErr || empty($n8nRaw)) {
        echo json_encode(['success'=>false,'error'=>$n8nErr ?: 'Sin respuesta de n8n (prompt)']);
        exit;
    }
    $n8nData = json_decode($n8nRaw, true);
    if (is_array($n8nData) && isset($n8nData[0])) $n8nData = $n8nData[0];
    $prompt = $n8nData['prompt'] ?? '';

    if (empty($prompt)) {
        echo json_encode(['success'=>false,'error'=>'n8n no retornó un prompt válido','raw'=>substr($n8nRaw,0,200)]);
        exit;
    }

    // Paso 2: llamar AI directamente con PHP curl
    $aiResp = callAiDirect($aiProvider, $apiKey, $prompt);

    // Paso 3: parsear respuesta (misma lógica que nodo n8n "Parsear Respuesta Claude")
    $parsed = parseAiCopyResponse($aiResp);

    if (isset($parsed['error'])) {
        echo json_encode(['success'=>false,'error'=>$parsed['error']]);
        exit;
    }

    $copy = $parsed['copy'] ?? [];
    if (is_array($copy) && (isset($copy['meta']) || isset($copy['tiktok']))) {
        echo json_encode(['success'=>true,'ads'=>$copy]);
    } else {
        echo json_encode(['success'=>true,'copy'=>$copy]);
    }
    exit;
}

$payload = json_encode($data);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 290,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(['success'=>false,'error'=>$err,'source'=>'curl']);
    exit;
}

if (empty($response)) {
    echo json_encode(['success'=>false,'error'=>'Respuesta vacía de n8n','code'=>$httpCode]);
    exit;
}

// Normalizar — n8n a veces devuelve array
$parsed = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success'=>false,'error'=>'Respuesta no es JSON válido','raw'=>substr($response,0,200)]);
    exit;
}

// Si n8n devuelve array tomar primer elemento
if (is_array($parsed) && isset($parsed[0])) {
    $parsed = $parsed[0];
}

// Validar tamaño
$html   = $parsed['html']   ?? '';
$liquid = $parsed['liquid'] ?? '';
$copy   = $parsed['copy']   ?? null;

if ($html && strlen($html) > 716800) {
    echo json_encode(['success'=>false,'error'=>'HTML supera 700 KB']);
    exit;
}
if ($liquid && strlen($liquid) > 819200) {
    echo json_encode(['success'=>false,'error'=>'Liquid supera 800 KB']);
    exit;
}

// Detectar Liquid corrupto
if ($liquid && substr_count($liquid, '{{ product.price | money }}') > 50) {
    echo json_encode(['success'=>false,'error'=>'Liquid corrupto detectado']);
    exit;
}

echo json_encode($parsed);