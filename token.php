<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * OIDC Token endpoint for authorization code (PKCE RFC 7636) and client credentials authentication.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use Firebase\JWT\JWT;

header('Content-Type: application/json');

global $DB, $CFG;

// Security check: Never accept client_secret in GET query parameters.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['client_secret'])) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', '', '', 'Client secret rejected in GET request parameters.');
    http_response_code(400);
    echo json_encode([
        'error'             => 'invalid_request',
        'error_description' => 'Client secret must not be transmitted via GET query parameters.'
    ]);
    exit;
}

$grant_type    = optional_param('grant_type', 'client_credentials', PARAM_RAW);
$client_id     = optional_param('client_id', '', PARAM_RAW);
$client_secret = optional_param('client_secret', '', PARAM_RAW);
$code          = optional_param('code', '', PARAM_RAW);
$code_verifier = optional_param('code_verifier', '', PARAM_RAW);

// Support HTTP Basic Authentication header (client_id:client_secret).
if (empty($client_id) && isset($_SERVER['PHP_AUTH_USER'])) {
    $client_id     = $_SERVER['PHP_AUTH_USER'];
    $client_secret = $_SERVER['PHP_AUTH_PW'] ?? '';
}

if (empty($client_id)) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', '', '', 'Missing client_id.');
    http_response_code(400);
    echo json_encode([
        'error'             => 'invalid_request',
        'error_description' => 'Missing client_id.'
    ]);
    exit;
}

// Fetch active client application from database.
$client = $DB->get_record('local_simple_sso_clients', [
    'client_id' => $client_id,
    'enabled'   => 1
]);

if (!$client) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', $client_id, '', 'Invalid client credentials or disabled client.');
    http_response_code(401);
    echo json_encode([
        'error'             => 'invalid_client',
        'error_description' => 'Invalid client credentials or disabled client.'
    ]);
    exit;
}

// Handle Authorization Code Grant (PKCE RFC 7636)
if ($grant_type === 'authorization_code') {
    if (empty($code)) {
        http_response_code(400);
        echo json_encode([
            'error'             => 'invalid_request',
            'error_description' => 'Missing authorization code parameter.'
        ]);
        exit;
    }

    // If client_secret is provided, verify it match.
    if (!empty($client_secret) && !hash_equals($client->client_secret, $client_secret)) {
        http_response_code(401);
        echo json_encode([
            'error'             => 'invalid_client',
            'error_description' => 'Client secret verification failed.'
        ]);
        exit;
    }

    $error_reason = null;
    $code_record = local_simple_sso_consume_auth_code($code, $client_id, $code_verifier, $error_reason);
    if (!$code_record) {
        $desc = 'Invalid, expired, or failed PKCE verification code.';
        if (!empty($error_reason)) {
            $desc .= ' Reason: ' . $error_reason;
        }
        local_simple_sso_log_event('token_request_failed', 'BLOCKED', $client_id, '', $desc);
        http_response_code(400);
        echo json_encode([
            'error'             => 'invalid_grant',
            'error_description' => $desc
        ]);
        exit;
    }

    // Load Moodle user.
    $user = $DB->get_record('user', ['id' => $code_record->userid, 'deleted' => 0]);
    if (!$user) {
        http_response_code(400);
        echo json_encode([
            'error'             => 'invalid_grant',
            'error_description' => 'User associated with code not found.'
        ]);
        exit;
    }

    // Resolve Canonical CPF
    $cpf_limpo = null;
    if (class_exists('\local_ponto_eletronico\identity_resolver')) {
        $cpf_limpo = \local_ponto_eletronico\identity_resolver::resolve_cpf($user);
    }
    if (empty($cpf_limpo)) {
        $clean_u  = preg_replace('/\D/', '', $user->username ?? '');
        $clean_id = preg_replace('/\D/', '', $user->idnumber ?? '');
        if (strlen($clean_u) === 11) {
            $cpf_limpo = $clean_u;
        } else if (strlen($clean_id) === 11) {
            $cpf_limpo = $clean_id;
        }
    }

    $vinculos = [];
    if (!empty($cpf_limpo) && class_exists('\local_ponto_eletronico\external_connection')) {
        try {
            $extdb = \local_ponto_eletronico\external_connection::get();
            $sql_vinculos = "SELECT Matricula, Contrato, Cod_Lotacao, Cargo, Lotacao, Descr_Secretaria
                             FROM funcionarios
                             WHERE (CPF = ? OR REPLACE(REPLACE(REPLACE(CPF, '.', ''), '-', ''), ' ', '') = ?)
                               AND (Encerrado IS NULL OR UPPER(TRIM(Encerrado)) NOT LIKE 'SIM%')
                             ORDER BY Contrato ASC";
            $records_vh = $extdb->get_records_sql($sql_vinculos, [$cpf_limpo, $cpf_limpo]);
            foreach ($records_vh as $vh) {
                $vinculos[] = [
                    'matricula'        => (int)($vh->matricula ?? $vh->Matricula),
                    'contrato'         => (int)($vh->contrato ?? $vh->Contrato),
                    'cod_lotacao'      => trim($vh->cod_lotacao ?? $vh->Cod_Lotacao ?? ''),
                    'cargo'            => trim($vh->cargo ?? $vh->Cargo ?? ''),
                    'lotacao'          => trim($vh->lotacao ?? $vh->Lotacao ?? ''),
                    'descr_secretaria' => trim($vh->descr_secretaria ?? $vh->Descr_Secretaria ?? '')
                ];
            }
        } catch (\Throwable $e) {
            debugging('Erro ao buscar vínculos funcionais no SSO token: ' . $e->getMessage());
        }
    }

    $user_type = !empty($vinculos) ? 'servidor' : 'contribuinte';

    require_once($CFG->dirroot . '/cohort/lib.php');
    $groups = [];
    $user_cohorts = cohort_get_user_cohorts($user->id);
    if (!empty($user_cohorts)) {
        foreach ($user_cohorts as $cohort) {
            $groups[] = $cohort->idnumber ?: $cohort->name;
        }
    }

    $sub_canonical = !empty($cpf_limpo) ? $cpf_limpo : $user->username;
    $issued_at     = time();
    $expiration    = $issued_at + 300;

    $payload = [
        "iss"           => $CFG->wwwroot,
        "aud"           => $client->client_id,
        "sub"           => $sub_canonical,
        "moodle_userid" => (int)$user->id,
        "email"         => $user->email,
        "firstname"     => $user->firstname,
        "lastname"      => $user->lastname,
        "name"          => fullname($user),
        "user_type"     => $user_type,
        "auth_method"   => $user->auth,
        "vinculos"      => $vinculos,
        "groups"        => array_values(array_unique($groups)),
        "iat"           => $issued_at,
        "exp"           => $expiration
    ];

    try {
        $jwt = JWT::encode($payload, $client->client_secret, 'HS256');
        local_simple_sso_log_event('token_issued_pkce', 'SUCCESS', $client->client_id, '', '', '', $user->id);
    } catch (\Exception $e) {
        local_simple_sso_log_event('token_request_failed', 'ERROR', $client->client_id, '', $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error'             => 'server_error',
            'error_description' => 'Error issuing access token.'
        ]);
        exit;
    }

    echo json_encode([
        'access_token' => $jwt,
        'token_type'   => 'Bearer',
        'expires_in'   => 300,
        'id_token'     => $jwt
    ]);
    exit;
}

// Handle Client Credentials Grant
if (empty($client_secret) || !hash_equals($client->client_secret, $client_secret)) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', $client_id, '', 'Invalid client secret.');
    http_response_code(401);
    echo json_encode([
        'error'             => 'invalid_client',
        'error_description' => 'Invalid client secret.'
    ]);
    exit;
}

// Issue signed JWT access token for client credentials.
$issued_at  = time();
$expiration = $issued_at + 300;

$payload = [
    "iss" => $CFG->wwwroot,
    "aud" => $client->client_id,
    "sub" => $client->client_id,
    "iat" => $issued_at,
    "exp" => $expiration
];

try {
    $jwt = JWT::encode($payload, $client->client_secret, 'HS256');
    local_simple_sso_log_event('token_issued', 'SUCCESS', $client->client_id);
} catch (\Exception $e) {
    local_simple_sso_log_event('token_request_failed', 'ERROR', $client->client_id, '', $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error'             => 'server_error',
        'error_description' => 'Error issuing access token.'
    ]);
    exit;
}

echo json_encode([
    'access_token' => $jwt,
    'token_type'   => 'Bearer',
    'expires_in'   => 300
]);
