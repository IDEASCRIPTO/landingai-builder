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

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $raw,
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