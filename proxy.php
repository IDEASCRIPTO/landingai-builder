<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    exit(0);
}

$data = file_get_contents('php://input');

if (empty($data)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'LandingAI Builder v2 - proxy ok']);
    exit(0);
}

set_time_limit(150);

/* ── Determinar destino ─────────────────────────────────────────────────── */
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

$is_copy = false;

// Ruta 1: la URL del request contiene "proxy-copy"
if (strpos($request_uri, 'proxy-copy') !== false) {
    $is_copy = true;
}

// Ruta 2: el body JSON tiene accion = "generar_copy" o "regenerar_seccion"
if (!$is_copy) {
    $body = json_decode($data, true);
    if (isset($body['accion']) && in_array($body['accion'], ['generar_copy', 'regenerar_seccion'], true)) {
        $is_copy = true;
    }
}

$n8n_url = $is_copy
    ? 'https://duallegacy-ia-asistentes-n8n.aigmej.easypanel.host/webhook/generar-copy-v2'
    : 'https://duallegacy-ia-asistentes-n8n.aigmej.easypanel.host/webhook/generar-v2';

/* ── Reenviar a n8n ─────────────────────────────────────────────────────── */
$ch = curl_init($n8n_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response  = curl_exec($ch);
$error     = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');

if ($error) {
    echo json_encode(['error' => true, 'message' => $error, 'code' => $httpCode]);
    exit(0);
}

echo $response;
