<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
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
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', '', '', 'Bearer token não fornecido.');
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
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', $client_id ?? '', '', 'Token JWT inválido ou expirado.');
    http_response_code(401);
    echo json_encode(['error' => 'invalid_token', 'error_description' => 'Token JWT inválido ou expirado.']);
    exit;
}

// Retorna as informações no formato OIDC UserInfo e registra log
$user_data = (array)$decoded;

// Se disponível no token, tenta resolver a userid pelo username
$user_id_obj = $DB->get_record('user', ['username' => $user_data['sub'] ?? ''], 'id');
$moodle_userid = $user_id_obj ? (int)$user_id_obj->id : 0;

local_simple_sso_log_event('userinfo_access', 'SUCCESS', $user_data['aud'] ?? $client_id ?? '', '', '', '', $moodle_userid);

echo json_encode([
    'sub'                => $user_data['sub'],
    'email'              => $user_data['email'],
    'given_name'         => $user_data['firstname'],
    'family_name'        => $user_data['lastname'],
    'name'               => trim(($user_data['firstname'] ?? '') . ' ' . ($user_data['lastname'] ?? '')),
    'groups'             => $user_data['groups'] ?? []
]);
