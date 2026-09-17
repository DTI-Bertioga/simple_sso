<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use Firebase\JWT\JWT;

// 1. Força a autenticação no Moodle
require_login();

global $USER, $DB, $CFG;

// 2. Recebe os parâmetros do cliente
$client_id    = required_param('client_id', PARAM_RAW);
$redirect_uri = required_param('redirect_uri', PARAM_RAW);

// 3. Busca e valida a Aplicação (Client ID)
$client = $DB->get_record('local_simple_sso_clients', ['client_id' => $client_id, 'enabled' => 1]);
if (!$client) {
    print_error('invalidclient', 'local_simple_sso', '', 'Client ID inválido ou desativado.');
}

// 4. Valida a URL de redirecionamento contra a Lista Branca do Cliente
if (!local_simple_sso_validate_redirect_uri($redirect_uri, $client->redirect_uri)) {
    print_error('invalidredirecturi', 'local_simple_sso', '', 'A URL de redirecionamento informada não está autorizada na Lista Branca.');
}

// 5. Coleta de Grupos / Coortes do Moodle
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($USER->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

// 6. Monta o Payload do JWT e assina com o Secret do Cliente
$issued_at  = time();
$expiration = $issued_at + 300; // Válido por 5 minutos

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
    $jwt = JWT::encode($payload, $client->client_secret, 'HS256');
} catch (Exception $e) {
    print_error('jwterror', 'local_simple_sso', '', $e->getMessage());
}

// 7. Redireciona de volta para o sistema de destino autorizado com o token anexado
$delimiter = (strpos($redirect_uri, '?') !== false) ? '&' : '?';
$final_destination = $redirect_uri . $delimiter . 'token=' . urlencode($jwt);

redirect($final_destination);
