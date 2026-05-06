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

$base = 'https://duallegacy-ia-asistentes-n8n.aigmej.easypanel.host/webhook/';

$routes = [
    'generar_copy'          => $base . 'generar-copy-v2',
    'regenerar_seccion'     => $base . 'generar-copy-v2',
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