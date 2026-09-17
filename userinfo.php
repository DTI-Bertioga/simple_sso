<?php
require_once(__DIR__ . '/../../config.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

global $DB;

// 1. Extrai o Bearer Token do cabeçalho Authorization (compatível com Apache, Nginx, IIS e CLI)
$auth_header = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

if (empty($auth_header)) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
}

if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'missing_token', 'error_description' => 'Bearer token não fornecido.']);
    exit;
}

$jwt_token = $matches[1];
$decoded   = null;

// 2. Extrai o 'aud' (client_id) do payload do token sem verificar a assinatura para realizar a busca O(1)
$parts = explode('.', $jwt_token);
$client_id = null;

if (count($parts) === 3) {
    try {
        $payload_json = JWT::urlsafeB64Decode($parts[1]);
        $unverified_payload = json_decode($payload_json, true);
        if (is_array($unverified_payload) && !empty($unverified_payload['aud'])) {
            $client_id = $unverified_payload['aud'];
        }
    } catch (Exception $e) {
        // Formato de payload inválido
    }
}

// 3. Validação O(1) do Token usando o secret do cliente específico
if (!empty($client_id)) {
    $client = $DB->get_record('local_simple_sso_clients', ['client_id' => $client_id, 'enabled' => 1]);
    if ($client) {
        try {
            $decoded = JWT::decode($jwt_token, new Key($client->client_secret, 'HS256'));
        } catch (Exception $e) {
            $decoded = null;
        }
    }
} else {
    // Fallback de retrocompatibilidade caso o token não possua a claim 'aud'
    $clients = $DB->get_records('local_simple_sso_clients', ['enabled' => 1]);
    foreach ($clients as $client) {
        try {
            $decoded = JWT::decode($jwt_token, new Key($client->client_secret, 'HS256'));
            break;
        } catch (Exception $e) {
            continue;
        }
    }
}

if (!$decoded) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_token', 'error_description' => 'Token JWT inválido ou expirado.']);
    exit;
}

// 4. Retorna as informações no formato OIDC UserInfo
$user_data = (array)$decoded;
echo json_encode([
    'sub'                => $user_data['sub'],
    'email'              => $user_data['email'],
    'given_name'         => $user_data['firstname'],
    'family_name'        => $user_data['lastname'],
    'name'               => trim(($user_data['firstname'] ?? '') . ' ' . ($user_data['lastname'] ?? '')),
    'groups'             => $user_data['groups'] ?? []
]);
