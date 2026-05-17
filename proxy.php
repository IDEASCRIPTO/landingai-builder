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

/* ── IMAGEN DIRECTO: DALL-E 3 / Flux / Google Imagen ─────────────────────────────── */
function callImageDirect(string $imgProvider, string $apiKey, string $prompt): array {
    if ($imgProvider === 'flux') {
        $url  = 'https://fal.run/fal-ai/flux/schnell';
        $body = json_encode(['prompt'=>$prompt,'image_size'=>'landscape_4_3','num_inference_steps'=>4,'num_images'=>1,'enable_safety_checker'=>true]);
        $hdrs = ['Content-Type: application/json', 'Authorization: Key ' . $apiKey];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_SSL_VERIFYPEER=>false]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['__error'=>$err];
        $r = json_decode($resp, true);
        $url_img = $r['images'][0]['url'] ?? null;
        return $url_img ? ['url'=>$url_img] : ['__error'=>'Flux no retornó imagen: '.substr($resp,0,200)];

    } elseif ($imgProvider === 'imagen') {
        $url  = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict?key=' . $apiKey;
        $body = json_encode(['instances'=>[['prompt'=>$prompt]],'parameters'=>['sampleCount'=>1]]);
        $hdrs = ['Content-Type: application/json'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_SSL_VERIFYPEER=>false]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['__error'=>$err];
        $r = json_decode($resp, true);
        if (isset($r['error'])) return ['__error'=>$r['error']['message'] ?? json_encode($r['error'])];
        $b64 = $r['predictions'][0]['bytesBase64Encoded'] ?? null;
        $mime = $r['predictions'][0]['mimeType'] ?? 'image/png';
        return $b64 ? ['url'=>'data:'.$mime.';base64,'.$b64] : ['__error'=>'Imagen no retornó datos: '.substr($resp,0,200)];

    } else {
        // DALL-E 3 (default)
        $url  = 'https://api.openai.com/v1/images/generations';
        $body = json_encode(['model'=>'dall-e-3','prompt'=>$prompt,'n'=>1,'size'=>'1024x1024']);
        $hdrs = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_SSL_VERIFYPEER=>false]);
        $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($err) return ['__error'=>$err];
        $r = json_decode($resp, true);
        if (isset($r['error'])) return ['__error'=>$r['error']['message'] ?? json_encode($r['error'])];
        $url_img = $r['data'][0]['url'] ?? null;
        return $url_img ? ['url'=>$url_img] : ['__error'=>'DALL-E no retornó URL: '.substr($resp,0,200)];
    }
}

/* ── AI DIRECTO CON SYSTEM PROMPT: para el agente web que separa system/user ── */
function callAiDirectWithSystem(string $provider, string $apiKey, string $systemPrompt, string $userMessage): array {
    if ($provider === 'openai') {
        $url  = 'https://api.openai.com/v1/chat/completions';
        $body = json_encode(['model'=>'gpt-4o-mini','max_tokens'=>4000,'messages'=>[
            ['role'=>'system','content'=>$systemPrompt],
            ['role'=>'user','content'=>$userMessage],
        ]]);
        $hdrs = ['Content-Type: application/json','Authorization: Bearer '.$apiKey];
    } elseif ($provider === 'gemini') {
        $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey;
        $body = json_encode(['system_instruction'=>['parts'=>[['text'=>$systemPrompt]]],'contents'=>[['parts'=>[['text'=>$userMessage]]]]]);
        $hdrs = ['Content-Type: application/json'];
    } else {
        $url  = 'https://api.anthropic.com/v1/messages';
        $body = json_encode(['model'=>'claude-sonnet-4-6','max_tokens'=>4000,'system'=>$systemPrompt,'messages'=>[['role'=>'user','content'=>$userMessage]]]);
        $hdrs = ['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$hdrs,
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['__curl_error'=>$err];
    return json_decode($resp, true) ?: ['__curl_error'=>'Respuesta no es JSON'];
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
$SUPABASE_URL      = getenv('SUPABASE_URL') ?: 'https://jsrbfuwjkqwzhjdsbicg.supabase.co';
$SUPABASE_ANON_KEY = getenv('SUPABASE_ANON_KEY') ?: 'sb_publishable_aOtEsxOOgBqL05kQ8eMIDQ_WHF7rw54';
$SUPABASE_SVC_KEY  = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
$auth_enabled      = !empty($SUPABASE_URL) && !str_contains($SUPABASE_URL, 'xxxx');
$auth_user_id      = null;
$auth_plan         = 'free';

if ($auth_enabled && !empty($data['_auth_token'])) {
    $token = $data['_auth_token'];

    // Validar token con Supabase
    $ch = curl_init($SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'apikey: ' . $SUPABASE_ANON_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'error'   => 'Sesión inválida. Inicia sesión nuevamente.',
            'code'    => 401,
            '_debug'  => ['http_code' => $httpCode, 'url' => $SUPABASE_URL . '/auth/v1/user', 'resp' => substr($resp ?: '', 0, 200)],
        ]);
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
                'apikey: ' . $SUPABASE_ANON_KEY,
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
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$token,'apikey: '.$SUPABASE_ANON_KEY,'Accept: application/json'],
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
                    'apikey: '.$SUPABASE_ANON_KEY,
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
                    'apikey: '.$SUPABASE_ANON_KEY,
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

/* ── API KEY MANAGEMENT (save / load) ────────────────────────
   Usa service_role para bypass RLS completo.
   save_api_key: upsert de la key del usuario
   load_api_keys: retorna lista de proveedores guardados
─────────────────────────────────────────────────────────────── */
$_svcKey  = $SUPABASE_SVC_KEY;
$_sbApiK  = $_svcKey ?: $SUPABASE_ANON_KEY;
$_sbAuth  = $_svcKey ? 'Bearer ' . $_svcKey : 'Bearer ' . ($token ?? '');

if (($data['accion'] ?? '') === 'save_api_key') {
    if (!$auth_enabled || !$auth_user_id) {
        echo json_encode(['success'=>false,'error'=>'Autenticación requerida.']);
        exit;
    }
    $prov   = $data['provider'] ?? '';
    $keyVal = $data['key_enc']  ?? '';
    if (!in_array($prov, ['anthropic','openai','gemini','fal']) || empty($keyVal)) {
        echo json_encode(['success'=>false,'error'=>'Proveedor o key inválidos.']);
        exit;
    }
    $body = json_encode([['user_id'=>$auth_user_id,'provider'=>$prov,'key_enc'=>$keyVal]]);
    $chS  = curl_init($SUPABASE_URL . '/rest/v1/api_keys?on_conflict=user_id,provider');
    curl_setopt_array($chS, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $_sbAuth,
            'apikey: ' . $_sbApiK,
            'Content-Type: application/json',
            'Prefer: resolution=merge-duplicates,return=minimal',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $sResp = curl_exec($chS);
    $sErr  = curl_error($chS);
    $sCode = curl_getinfo($chS, CURLINFO_HTTP_CODE);
    curl_close($chS);
    if ($sErr || $sCode >= 400) {
        echo json_encode(['success'=>false,'error'=>$sErr ?: 'Error ' . $sCode . ': ' . substr($sResp, 0, 200)]);
    } else {
        echo json_encode(['success'=>true]);
    }
    exit;
}

if (($data['accion'] ?? '') === 'load_api_keys') {
    if (!$auth_enabled || !$auth_user_id) {
        echo json_encode(['success'=>true,'providers'=>[]]);
        exit;
    }
    $chL = curl_init($SUPABASE_URL . '/rest/v1/api_keys?select=provider&user_id=eq.' . $auth_user_id);
    curl_setopt_array($chL, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $_sbAuth,
            'apikey: ' . $_sbApiK,
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $lResp = curl_exec($chL);
    $lErr  = curl_error($chL);
    curl_close($chL);
    $lArr  = json_decode($lResp, true);
    $provs = (is_array($lArr) && !$lErr) ? array_column($lArr, 'provider') : [];
    echo json_encode(['success'=>true,'providers'=>$provs]);
    exit;
}

/* ── API KEY ROUTING ──────────────────────────────────────────
   ADMIN_EMAIL → dueño, usa keys del sistema (env vars de EasyPanel)
   Otros       → deben tener su propia key del proveedor elegido
   Proveedores soportados: anthropic, openai, gemini
─────────────────────────────────────────────────────────────── */
$ADMIN_EMAIL   = getenv('ADMIN_EMAIL') ?: '';
$AI_ACTIONS    = ['generar_copy','regenerar_seccion','generar_ads','generar_html','generar_liquid','generar_web','analizar_url'];
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

$user_email = $auth_user_id ? ($usr['email'] ?? '') : '';
$is_admin   = !empty($ADMIN_EMAIL) && strtolower($user_email) === strtolower($ADMIN_EMAIL);

if (in_array($accion_actual, $AI_ACTIONS)) {

    if ($is_admin || !$auth_enabled) {
        // Admin o modo dev: usa keys del sistema
        $data['_api_key']  = $SYSTEM_KEYS[$provider];
        $data['_provider'] = $provider;
    } elseif ($auth_enabled && $auth_user_id && !empty($token)) {
        // Usuario normal: traer todas las keys del usuario y filtrar por proveedor en PHP
        $serviceRoleKey = $SUPABASE_SVC_KEY;
        $sbAuthHeader   = $serviceRoleKey ? 'Bearer ' . $serviceRoleKey : 'Bearer ' . $token;
        $sbApiKey       = $serviceRoleKey ?: $SUPABASE_ANON_KEY;

        $chK = curl_init($SUPABASE_URL . '/rest/v1/api_keys?select=provider,key_enc&user_id=eq.' . rawurlencode($auth_user_id));
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
        $keyResp    = curl_exec($chK);
        $keyCurlErr = curl_error($chK);
        $keyHttpCode = curl_getinfo($chK, CURLINFO_HTTP_CODE);
        curl_close($chK);

        $keyArr       = json_decode($keyResp, true);
        $user_api_key = '';
        if (is_array($keyArr)) {
            foreach ($keyArr as $row) {
                if (($row['provider'] ?? '') === $provider && !empty($row['key_enc'])) {
                    $user_api_key = $row['key_enc'];
                    break;
                }
            }
        }

        if (empty($user_api_key)) {
            $provName = ['anthropic'=>'Anthropic','openai'=>'OpenAI','gemini'=>'Gemini'][$provider] ?? $provider;
            $sbDebug  = $keyCurlErr ?: ('HTTP ' . $keyHttpCode . ' | rows=' . (is_array($keyArr) ? count($keyArr) : 'null') . ' | ' . substr($keyResp ?: '', 0, 200));
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

/* ── GENERAR_WEB: Claude genera JSON completo para el constructor web ─────────────
   No usa n8n. El prompt se construye aquí y PHP llama a la IA directamente.
──────────────────────────────────────────────────────────────────────────────── */
if ($accion_actual === 'generar_web') {
    $apiKey     = $data['_api_key'] ?? '';
    $aiProvider = $data['_provider'] ?? $provider;
    $descripcion = trim($data['descripcion'] ?? '');
    $tipo = $data['tipo'] ?? 'servicio';

    if (empty($apiKey)) {
        $provName = ['anthropic'=>'Anthropic','openai'=>'OpenAI','gemini'=>'Gemini'][$aiProvider] ?? $aiProvider;
        echo json_encode(['success'=>false,'error'=>'Necesitas configurar tu API key de '.$provName.' en Configuración (⚙️).','code'=>'NO_API_KEY','provider'=>$aiProvider]);
        exit;
    }
    if (empty($descripcion)) {
        echo json_encode(['success'=>false,'error'=>'Falta la descripción del negocio.']);
        exit;
    }

    $tipoLabel = [
        'viajes'       => 'Agencia de Viajes',
        'medico'       => 'Médico / Clínica',
        'abogado'      => 'Abogado / Bufete',
        'inmobiliaria' => 'Inmobiliaria',
        'automotriz'   => 'Automotriz',
        'producto'     => 'Tienda / Productos',
        'servicio'     => 'Servicios / Agencia',
    ][$tipo] ?? 'Negocio';

    $prompt = 'Eres un experto en marketing digital y copywriting para negocios en Ecuador y Latinoamérica. El usuario quiere crear una página web profesional.

Tipo de negocio: ' . $tipoLabel . '
Descripción del usuario: ' . $descripcion . '

Genera el contenido completo para una página web profesional. Responde ÚNICAMENTE con un JSON válido (sin texto adicional, sin bloques de código, sin explicaciones), con esta estructura exacta:

{
  "bizName": "Nombre corto y memorable del negocio",
  "bizTagline": "Propuesta de valor en una línea",
  "bizDesc": "Descripción del negocio en 1-2 oraciones claras",
  "bizBadge": "Emoji + texto corto para el badge del hero (ej: 🏆 Líderes en Ecuador · Desde 2015)",
  "heroTitle": "Título principal del hero, impactante y directo (máx 10 palabras)",
  "heroSub": "Subtítulo persuasivo de 1-2 oraciones",
  "heroCta": "Texto del botón principal (ej: Agendar Cita, Cotizar Ahora, Contactar)",
  "servs": [
    {"icon": "emoji", "title": "Nombre del servicio", "desc": "Descripción breve del beneficio"},
    {"icon": "emoji", "title": "Nombre del servicio", "desc": "Descripción breve del beneficio"},
    {"icon": "emoji", "title": "Nombre del servicio", "desc": "Descripción breve del beneficio"},
    {"icon": "emoji", "title": "Nombre del servicio", "desc": "Descripción breve del beneficio"}
  ],
  "pasos": [
    {"title": "Nombre del paso", "desc": "Descripción breve de qué pasa en este paso"},
    {"title": "Nombre del paso", "desc": "Descripción breve de qué pasa en este paso"},
    {"title": "Nombre del paso", "desc": "Descripción breve de qué pasa en este paso"},
    {"title": "Nombre del paso", "desc": "Descripción breve de qué pasa en este paso"}
  ],
  "testis": [
    {"name": "Nombre real ecuatoriano", "cargo": "Ciudad o profesión", "text": "Testimonio creíble y natural de 1-2 oraciones"},
    {"name": "Nombre real ecuatoriano", "cargo": "Ciudad o profesión", "text": "Testimonio creíble y natural de 1-2 oraciones"},
    {"name": "Nombre real ecuatoriano", "cargo": "Ciudad o profesión", "text": "Testimonio creíble y natural de 1-2 oraciones"}
  ],
  "ctPhone": "+593 9X XXX-XXXX",
  "ctWa": "593XXXXXXXXX",
  "ctEmail": "contacto@negocio.ec",
  "ctAddr": "Ciudad, Ecuador",
  "ctHorario": "Lun-Vie 9:00-18:00",
  "formTitle": "Título llamativo del formulario de contacto",
  "formBtn": "Texto del botón de envío",
  "formSuccess": "Mensaje amigable de confirmación tras enviar el formulario"
}

Reglas: usa nombres, ciudades y referencias reales de Ecuador. Contenido persuasivo y profesional. Exactamente 4 servicios, 4 pasos y 3 testimonios. Solo JSON, sin texto fuera del JSON.';

    $aiResp = callAiDirect($aiProvider, $apiKey, $prompt);
    $parsed = parseAiCopyResponse($aiResp);

    if (isset($parsed['error'])) {
        echo json_encode(['success'=>false,'error'=>$parsed['error']]);
        exit;
    }

    $webData = $parsed['copy'] ?? [];
    if (empty($webData) || isset($webData['raw'])) {
        echo json_encode(['success'=>false,'error'=>'La IA no retornó un JSON válido. Intenta de nuevo.','raw'=>substr($parsed['raw_text']??'',0,300)]);
        exit;
    }

    echo json_encode(['success'=>true,'data'=>$webData]);
    exit;
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
    'analizar_url'          => $base . 'analizar-url',
    'generar_web_v2'        => $base . 'generar-web-agente',
];

$accion = $data['accion'];
if (!isset($routes[$accion])) {
    echo json_encode(['success'=>false,'error'=>'Acción no permitida: '.$accion]);
    exit;
}

set_time_limit(300);
$url = $routes[$accion];

/* ── GENERAR IMAGEN: PHP llama API de imagen directamente (DALL-E / Flux / Google Imagen) ── */
if ($accion_actual === 'generar_imagen') {
    $imgProvider = $data['_imagen_provider'] ?? 'dalle';
    $prompt      = $data['prompt'] ?? '';

    if (empty($prompt)) {
        echo json_encode(['success'=>false,'error'=>'Falta el prompt de la imagen']);
        exit;
    }

    // Obtener la API key del proveedor de imagen
    $imgKeyMap = ['dalle'=>'openai', 'flux'=>'fal', 'imagen'=>'gemini'];
    $imgKeyProvider = $imgKeyMap[$imgProvider] ?? 'openai';

    // Admin usa key del sistema; usuario usa su key de Supabase
    if ($is_admin || !$auth_enabled) {
        $sysKeyMap = ['openai'=>'OPENAI_API_KEY', 'fal'=>'FAL_API_KEY', 'gemini'=>'GEMINI_API_KEY'];
        $imgApiKey = getenv($sysKeyMap[$imgKeyProvider] ?? 'OPENAI_API_KEY') ?: '';
    } else {
        $serviceRoleKey = $SUPABASE_SVC_KEY;
        $sbAuth = $serviceRoleKey ? 'Bearer '.$serviceRoleKey : 'Bearer '.$token;
        $sbKey  = $serviceRoleKey ?: $SUPABASE_ANON_KEY;
        $chImg  = curl_init($SUPABASE_URL.'/rest/v1/api_keys?select=provider,key_enc&user_id=eq.'.rawurlencode($auth_user_id));
        curl_setopt_array($chImg, [CURLOPT_HTTPHEADER=>['Authorization: '.$sbAuth,'apikey: '.$sbKey,'Accept: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>6, CURLOPT_SSL_VERIFYPEER=>false]);
        $kResp = curl_exec($chImg); curl_close($chImg);
        $kArr  = json_decode($kResp, true);
        $imgApiKey = '';
        if (is_array($kArr)) {
            foreach ($kArr as $row) {
                if (($row['provider'] ?? '') === $imgKeyProvider && !empty($row['key_enc'])) {
                    $imgApiKey = $row['key_enc'];
                    break;
                }
            }
        }
    }

    if (empty($imgApiKey)) {
        $provNames = ['openai'=>'OpenAI (para DALL-E)', 'fal'=>'fal.ai (para Flux)', 'gemini'=>'Gemini (para Google Imagen)'];
        echo json_encode(['success'=>false,'error'=>'Necesitas configurar tu API key de '.($provNames[$imgKeyProvider]??$imgKeyProvider).' en Configuración (⚙️).','code'=>'NO_API_KEY']);
        exit;
    }

    $imgResult = callImageDirect($imgProvider, $imgApiKey, $prompt);
    if (isset($imgResult['__error'])) {
        echo json_encode(['success'=>false,'error'=>$imgResult['__error']]);
        exit;
    }
    echo json_encode(['success'=>true,'url'=>$imgResult['url']]);
    exit;
}

/* ── GENERAR_WEB_V2: Agente n8n construye prompt (+ Jina si hay URL), PHP llama AI ──
   n8n devuelve {systemPrompt, userMessage}. PHP llama AI con system separado.
   Mismo patrón COPY_ACTIONS pero respuesta es JSON de página completa.
────────────────────────────────────────────────────────────────────────────────── */
if ($accion_actual === 'generar_web_v2') {
    // El AI Agent de n8n (Claude Opus) llama a la IA directamente.
    // proxy.php solo valida auth, llama n8n y parsea el output del agente.

    $ch1 = curl_init($url);
    curl_setopt_array($ch1, [
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($data),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>120,
        CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $n8nRaw = curl_exec($ch1); $n8nErr = curl_error($ch1); curl_close($ch1);

    if ($n8nErr || empty($n8nRaw)) {
        echo json_encode(['success'=>false,'error'=>$n8nErr ?: 'Sin respuesta del agente']);
        exit;
    }

    $n8nData = json_decode($n8nRaw, true);
    if (is_array($n8nData) && isset($n8nData[0])) $n8nData = $n8nData[0];

    // El AI Agent devuelve {output: "...json string..."}
    $agentOutput = $n8nData['output'] ?? '';

    if (empty($agentOutput)) {
        echo json_encode(['success'=>false,'error'=>'El agente no retornó respuesta','raw'=>substr($n8nRaw,0,400)]);
        exit;
    }

    // Extraer JSON del output (el agente puede incluir markdown o texto extra)
    $pageData = json_decode($agentOutput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $s = strpos($agentOutput, '{'); $e = strrpos($agentOutput, '}');
        if ($s !== false && $e > $s) $pageData = json_decode(substr($agentOutput, $s, $e - $s + 1), true);
    }

    if (!is_array($pageData) || empty($pageData)) {
        echo json_encode(['success'=>false,'error'=>'El agente no retornó JSON válido','raw'=>substr($agentOutput,0,400)]);
        exit;
    }

    echo json_encode(['success'=>true,'data'=>$pageData]);
    exit;
}

/* ── ACCIONES COPY: n8n genera el prompt, PHP llama AI directamente ─────────────
   n8n ya no tiene acceso al HTTP body dinámico por un bug de n8n (double-encoding).
   Solución: n8n devuelve solo el prompt, proxy.php hace la llamada AI con curl.
──────────────────────────────────────────────────────────────────────────────── */
$COPY_ACTIONS = ['generar_copy', 'regenerar_seccion', 'generar_ads', 'analizar_url'];
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
        echo json_encode(['success'=>false,'error'=>$parsed['error'],'_debug'=>substr($parsed['raw_text']??'',0,300)]);
        exit;
    }

    $copy = $parsed['copy'] ?? [];
    if (empty($copy) || isset($copy['raw'])) {
        $raw_preview = substr($parsed['raw_text'] ?? '', 0, 300);
        echo json_encode(['success'=>false,'error'=>'La IA no retornó un JSON válido. Intentá de nuevo.','_debug'=>$raw_preview]);
        exit;
    }
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

// ── Inyectar sticky bar CTA + efectos en HTML generado ──────────────────────
if ($html && $accion_actual === 'generar_html') {
    $bens_img_pos = in_array($data['bens_img_pos'] ?? 'left', ['left','right']) ? $data['bens_img_pos'] : 'left';
    $cta_effect   = preg_replace('/[^a-z]/', '', strtolower($data['cta_effect'] ?? 'none'));
    $sticky_color = $data['sticky_color'] ?? 'auto';
    $cta_href     = htmlspecialchars(trim($data['cta_url'] ?? '') ?: '#cta', ENT_QUOTES);
    $copy_ed      = $data['copy_editado'] ?? [];
    $cta_text     = htmlspecialchars(
        $copy_ed['hero']['cta'] ?? $copy_ed['cta_final']['btn'] ?? 'Comprar ahora',
        ENT_QUOTES
    );

    $valid_effects = ['shake', 'pulse', 'bounce', 'glow'];
    $effect_safe   = in_array($cta_effect, $valid_effects) ? $cta_effect : 'none';

    // Color de fondo: auto usa color_principal del builder (más confiable que detección JS)
    if ($sticky_color === 'auto') {
        $bg_color = preg_replace('/[^#a-fA-F0-9]/', '', $data['color_principal'] ?? '#1D9E75') ?: '#1D9E75';
    } else {
        $bg_color = preg_replace('/[^#a-fA-F0-9]/', '', $sticky_color) ?: '#1D9E75';
    }
    $text_color = preg_replace('/[^#a-fA-F0-9]/', '', $data['sticky_text_color'] ?? '#ffffff') ?: '#ffffff';

    $snippet = "
<!-- df-cta-enhancements -->
<style>
@keyframes df-shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}
@keyframes df-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.07)}}
@keyframes df-bounce{0%,100%{transform:translateY(0)}40%{transform:translateY(-9px)}60%{transform:translateY(-4px)}}
@keyframes df-glow{0%,100%{box-shadow:0 4px 14px rgba(0,0,0,.22)}50%{box-shadow:0 4px 32px rgba(255,255,255,.38),0 0 52px rgba(255,255,255,.15)}}
.df-fx-shake{animation:df-shake 3.5s ease-in-out infinite}
.df-fx-pulse{animation:df-pulse 2s ease-in-out infinite}
.df-fx-bounce{animation:df-bounce 1.6s ease-in-out infinite}
.df-fx-glow{animation:df-glow 2.2s ease-in-out infinite}
#df-sticky-bar{position:fixed;bottom:0;left:0;right:0;z-index:9990;padding:12px 16px 18px;display:none;box-shadow:0 -4px 24px rgba(0,0,0,.35)}
#df-sticky-bar{cursor:pointer}
#df-sticky-bar span{display:block;text-align:center;font-size:1.08rem;font-weight:800;letter-spacing:.3px;transition:opacity .15s;pointer-events:none}
#df-sticky-bar:hover span{opacity:.85}
@media(min-width:769px){#df-sticky-bar{display:none!important}}
@media(max-width:768px){#ctaFloat{display:none!important}}
</style>
<div id=\"df-sticky-bar\" style=\"background-color:{$bg_color};background:{$bg_color}\"><span style=\"color:{$text_color};display:block;padding:0;text-shadow:0 1px 3px rgba(0,0,0,.18)\">{$cta_text}</span></div>
<script>
(function(){
  var effect='{$effect_safe}';
  var bar=document.getElementById('df-sticky-bar');
  if(effect!=='none'){
    var sel='[class*=\"cta\"] a,a[class*=\"cta\"],button[class*=\"cta\"],[class*=\"btn-hero\"],a[class*=\"btn\"]';
    document.querySelectorAll(sel).forEach(function(el){el.classList.add('df-fx-'+effect);});
  }
  var heroEl=document.querySelector('.hero,#hero,[class*=\"hero\"],section:first-of-type');
  var lastEl=document.querySelector('[class*=\"cta-final\"],[id*=\"cta_final\"],section:last-of-type');
  function tick(){
    if(window.innerWidth>768){bar.style.display='none';return;}
    var y=window.scrollY,wh=window.innerHeight;
    var from=heroEl?(heroEl.getBoundingClientRect().bottom+y+60):500;
    var to=lastEl?(lastEl.getBoundingClientRect().top+y-wh*0.6):9999999;
    bar.style.display=(y>from&&y<to)?'block':'none';
  }
  window.addEventListener('scroll',tick,{passive:true});
  window.addEventListener('resize',tick,{passive:true});
  setTimeout(tick,300);
  // Sticky bar tap → same action as all CTA buttons (uses configured link)
  bar.addEventListener('click',function(){
    var href='{$cta_href}';
    if(!href||href==='#')return;
    if(href.startsWith('#')){var el=document.getElementById(href.slice(1));if(el)el.scrollIntoView({behavior:'smooth'});}
    else{window.open(href,'_blank','noopener,noreferrer');}
  });
})();
</script>";

    $html = str_replace('</body>', $snippet . "\n</body>", $html);
    if (strpos($html, '</body>') === false) $html .= $snippet;
    $parsed['html'] = $html;
}
// ────────────────────────────────────────────────────────────────────────────

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

// Para generar_imagen: si n8n retorna url pero sin success, agregarlo
if (!isset($parsed['success']) && isset($parsed['url']) && !empty($parsed['url'])) {
    $parsed['success'] = true;
}

echo json_encode($parsed);