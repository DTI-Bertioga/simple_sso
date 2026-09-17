<?php
require_once(__DIR__ . '/../../config.php');
use Firebase\JWT\JWT;

header('Content-Type: application/json');

global $DB, $CFG;

$client_id     = optional_param('client_id', '', PARAM_RAW);
$client_secret = optional_param('client_secret', '', PARAM_RAW);

// Permite autenticação via HTTP Basic Auth Header ou POST
if (empty($client_id) && isset($_SERVER['PHP_AUTH_USER'])) {
    $client_id = $_SERVER['PHP_AUTH_USER'];
    $client_secret = $_SERVER['PHP_AUTH_PW'];
}

$client = $DB->get_record('local_simple_sso_clients', [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'enabled' => 1
]);

if (!$client) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_client', 'error_description' => 'Credenciais de cliente inválidas.']);
    exit;
}

// Retorna resposta no padrão OIDC
echo json_encode([
    'token_type'   => 'Bearer',
    'expires_in'   => 300
]);
