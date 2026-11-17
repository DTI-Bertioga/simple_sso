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
 * OIDC UserInfo endpoint returning canonical user profile & RH active contracts.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

global $DB, $CFG;

// 1. Extract Bearer Token from Authorization Header.
$auth_header = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

if (empty($auth_header)) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
}

if (!preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', '', '', 'Bearer token not provided.');
    http_response_code(401);
    echo json_encode([
        'error'             => 'missing_token',
        'error_description' => 'Bearer token not provided.'
    ]);
    exit;
}

$jwt_token = $matches[1];
$decoded   = null;

// 2. Decode unverified payload to extract 'aud' (client_id) for O(1) client lookup.
$parts = explode('.', $jwt_token);
$client_id = null;

if (count($parts) === 3) {
    try {
        $payload_json = JWT::urlsafeB64Decode($parts[1]);
        $unverified_payload = json_decode($payload_json, true);
        if (is_array($unverified_payload) && !empty($unverified_payload['aud'])) {
            $client_id = $unverified_payload['aud'];
        }
    } catch (\Exception $e) {
        // Invalid payload format.
    }
}

// 3. Verify token signature against calling client's secret.
if (!empty($client_id)) {
    $client = $DB->get_record('local_simple_sso_clients', ['client_id' => $client_id, 'enabled' => 1]);
    if ($client) {
        try {
            $decoded = JWT::decode($jwt_token, new Key($client->client_secret, 'HS256'));
        } catch (\Exception $e) {
            $decoded = null;
        }
    }
} else {
    // Fallback for older tokens without 'aud' claim.
    $clients = $DB->get_records('local_simple_sso_clients', ['enabled' => 1]);
    foreach ($clients as $client) {
        try {
            $decoded = JWT::decode($jwt_token, new Key($client->client_secret, 'HS256'));
            break;
        } catch (\Exception $e) {
            continue;
        }
    }
}

if (!$decoded || empty($decoded->sub)) {
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', $client_id ?? '', '', 'Invalid or expired JWT token.');
    http_response_code(401);
    echo json_encode([
        'error'             => 'invalid_token',
        'error_description' => 'Invalid or expired JWT token.'
    ]);
    exit;
}

// 4. Localiza a conta do usuário no Moodle por id, username ou idnumber (CPF/Matrícula)
$user = null;
if (!empty($decoded->moodle_userid)) {
    $user = $DB->get_record('user', ['id' => (int)$decoded->moodle_userid, 'deleted' => 0, 'suspended' => 0]);
}

if (!$user) {
    $clean_sub = preg_replace('/\D/', '', $decoded->sub);
    $user = $DB->get_record_select(
        'user',
        "(username = :sub OR idnumber = :sub OR REPLACE(REPLACE(REPLACE(username, '.', ''), '-', ''), ' ', '') = :clean_sub OR REPLACE(REPLACE(REPLACE(idnumber, '.', ''), '-', ''), ' ', '') = :clean_sub) AND deleted = 0 AND suspended = 0",
        ['sub' => $decoded->sub, 'clean_sub' => $clean_sub]
    );
}

if (!$user) {
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', $client_id ?? '', '', 'User not found or suspended.');
    http_response_code(404);
    echo json_encode([
        'error'             => 'user_not_found',
        'error_description' => 'Moodle user not found or active.'
    ]);
    exit;
}

// 5. Resolução do CPF Canônico da Pessoa Física
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
    } else {
        $cpf_limpo = $decoded->sub;
    }
}

// 6. Consulta Vínculos Funcionais no RH
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
        debugging('Erro ao buscar vínculos funcionais no UserInfo: ' . $e->getMessage());
    }
}

$user_type = !empty($vinculos) ? 'servidor' : 'contribuinte';

// Cohorts / Grupos do Moodle
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($user->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

local_simple_sso_log_event('userinfo_access', 'SUCCESS', $decoded->aud ?? $client_id ?? '', '', '', '', $user->id);

// 7. Retorna dados do perfil unificado no formato OIDC UserInfo
echo json_encode([
    'sub'           => $cpf_limpo,               // CPF único do cidadão/servidor
    'moodle_userid' => (int)$user->id,
    'username'      => $user->username,
    'email'         => $user->email,
    'given_name'    => $user->firstname,
    'family_name'   => $user->lastname,
    'name'          => fullname($user),
    'user_type'     => $user_type,               // 'servidor' ou 'contribuinte'
    'auth_method'   => $user->auth,
    'vinculos'      => $vinculos,                // Vínculos funcionais ativos no RH
    'groups'        => array_values(array_unique($groups))
]);
