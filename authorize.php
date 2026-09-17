<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use Firebase\JWT\JWT;

require_login();

global $USER, $DB, $CFG;

$client_id    = required_param('client_id', PARAM_RAW);
$redirect_uri = required_param('redirect_uri', PARAM_RAW);
$response_type= optional_param('response_type', 'token', PARAM_ALPHA); // 'token' (Implicit) ou 'code'

// 1. Busca e valida a Aplicação (Client ID)
$client = $DB->get_record('local_simple_sso_clients', ['client_id' => $client_id, 'enabled' => 1]);
if (!$client) {
    print_error('invalidclient', 'local_simple_sso', '', 'Client ID inválido ou desativado.');
}

// 2. Validação da Lista Branca de Domínios (Allowed Redirect URIs)
if (!local_simple_sso_validate_redirect_uri($redirect_uri, $client->redirect_uri)) {
    print_error('invalidredirecturi', 'local_simple_sso', '', 'A URL de redirecionamento informada não está autorizada na Lista Branca.');
}

// 3. Coleta de Grupos / Coortes
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($USER->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

// 4. Monta o Payload e assina usando a CHAVE SECRETA ESPECÍFICA deste Cliente
$issued_at  = time();
$expiration = $issued_at + 300; // Válido por 5 min

$payload = [
    "iss"       => $CFG->wwwroot,
    "aud"       => $client->client_id,
    "sub"       => $USER->username,
    "email"     => $USER->email,
    "firstname" => $USER->firstname,
    "lastname"  => $USER->lastname,
    "groups"    => array_values(array_unique($groups)),
    "iat"       => $issued_at,
    "exp"       => $expiration
];

try {
    // Assina usando o Client Secret do próprio cliente
    $jwt = JWT::encode($payload, $client->client_secret, 'HS256');
} catch (Exception $e) {
    print_error('jwterror', 'local_simple_sso', '', $e->getMessage());
}

// 5. Redireciona de volta para a aplicação autorizada
$delimiter = (strpos($redirect_uri, '?') !== false) ? '&' : '?';
$final_destination = $redirect_uri . $delimiter . 'token=' . urlencode($jwt);

redirect($final_destination);
