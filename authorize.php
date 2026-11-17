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
 * Main SSO / OIDC Authorization endpoint with PKCE (RFC 7636) & Canonical Identity support.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use Firebase\JWT\JWT;

require_login();

global $USER, $DB, $CFG;

// Reject guest accounts from receiving SSO tokens.
if (isguestuser()) {
    local_simple_sso_log_event('login_failed_guest', 'BLOCKED', '', '', 'Guest users are not allowed to authenticate via SSO.');
    throw new \moodle_exception('guestnotallowed', 'local_simple_sso');
}

$client_id             = required_param('client_id', PARAM_RAW);
$redirect_uri          = required_param('redirect_uri', PARAM_RAW);
$response_type         = optional_param('response_type', 'token', PARAM_ALPHA); // 'token' (Implicit) or 'code' (Auth Code / PKCE)
$response_mode         = optional_param('response_mode', 'query', PARAM_ALPHA); // 'query' (default for code/query) or 'fragment'
$code_challenge        = optional_param('code_challenge', null, PARAM_RAW);
$code_challenge_method = optional_param('code_challenge_method', 'S256', PARAM_ALPHANUM); // PARAM_ALPHANUM allows numbers in S256

// 1. Fetch and validate client application.
$client = $DB->get_record('local_simple_sso_clients', ['client_id' => $client_id, 'enabled' => 1]);
if (!$client) {
    local_simple_sso_log_event('login_failed_client', 'BLOCKED', $client_id, $redirect_uri, 'Invalid or disabled client ID.');
    throw new \moodle_exception('invalidclient', 'local_simple_sso');
}

// 2. Validate redirect URI against Whitelist.
if (!local_simple_sso_validate_redirect_uri($redirect_uri, $client->redirect_uri)) {
    local_simple_sso_log_event('login_failed_uri', 'BLOCKED', $client_id, $redirect_uri, 'Redirect URI is not authorized in the whitelist.');
    throw new \moodle_exception('invalidredirecturi', 'local_simple_sso');
}

// 3. Resolução do CPF Canônico da Pessoa Física
$cpf_limpo = null;

if (class_exists('\local_ponto_eletronico\identity_resolver')) {
    $cpf_limpo = \local_ponto_eletronico\identity_resolver::resolve_cpf($USER);
}

if (empty($cpf_limpo)) {
    $clean_u  = preg_replace('/\D/', '', $USER->username ?? '');
    $clean_id = preg_replace('/\D/', '', $USER->idnumber ?? '');
    if (strlen($clean_u) === 11) {
        $cpf_limpo = $clean_u;
    } else if (strlen($clean_id) === 11) {
        $cpf_limpo = $clean_id;
    }
}

// Se não houver CPF resolvido e o login for Google/OAuth2, redireciona para a página de vínculo de primeiro acesso
if (empty($cpf_limpo) && $USER->auth === 'oauth2') {
    $link_url = new moodle_url('/local/simple_sso/link_account.php', [
        'returnurl' => $FULLME
    ]);
    redirect($link_url);
}

// 4. Consulta Vínculos Funcionais no Banco do RH
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
        debugging('Erro ao buscar vínculos funcionais no SSO: ' . $e->getMessage());
    }
}

$user_type = !empty($vinculos) ? 'servidor' : 'contribuinte';

// 5. Valida restrição de Público-Alvo (allowed_audience) da aplicação client
$allowed_aud = $client->allowed_audience ?? 'todos';

if ($allowed_aud === 'servidor' && $user_type !== 'servidor') {
    local_simple_sso_log_event('login_failed_audience', 'BLOCKED', $client_id, $redirect_uri, 'Usuário não servidor tentou acessar aplicação restrita a servidores.');
    throw new \moodle_exception('acessorestritoservidor', 'local_simple_sso', '', 'Acesso Restrito: A aplicação "' . s($client->name) . '" é de acesso exclusivo para servidores públicos ativos.');
}

if ($allowed_aud === 'contribuinte' && $user_type !== 'contribuinte') {
    local_simple_sso_log_event('login_failed_audience', 'BLOCKED', $client_id, $redirect_uri, 'Servidor público tentou acessar aplicação restrita a contribuintes/cidadãos.');
    throw new \moodle_exception('acessorestritocontribuinte', 'local_simple_sso', '', 'Acesso Restrito: A aplicação "' . s($client->name) . '" é de acesso exclusivo para contribuintes / cidadãos.');
}

// 6. Handling Authorization Code Flow (PKCE RFC 7636)
if ($response_type === 'code') {
    $code = local_simple_sso_generate_auth_code($client_id, (int)$USER->id, $redirect_uri, $code_challenge, $code_challenge_method);
    local_simple_sso_log_event('login_success_code', 'SUCCESS', $client_id, $redirect_uri, 'Auth code issued', '', $USER->id);

    if ($response_mode === 'fragment') {
        $final_destination = $redirect_uri . '#code=' . urlencode($code);
    } else {
        $delimiter = (strpos($redirect_uri, '?') !== false) ? '&' : '?';
        $final_destination = $redirect_uri . $delimiter . 'code=' . urlencode($code);
    }

    redirect($final_destination);
}

// 7. Handling Direct Token Flow (Implicit Flow)
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($USER->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

$sub_canonical = !empty($cpf_limpo) ? $cpf_limpo : $USER->username;
$issued_at     = time();
$expiration    = $issued_at + 300;

$payload = [
    "iss"           => $CFG->wwwroot,
    "aud"           => $client->client_id,
    "sub"           => $sub_canonical,
    "moodle_userid" => (int)$USER->id,
    "email"         => $USER->email,
    "firstname"     => $USER->firstname,
    "lastname"      => $USER->lastname,
    "name"          => fullname($USER),
    "user_type"     => $user_type,
    "auth_method"   => $USER->auth,
    "vinculos"      => $vinculos,
    "groups"        => array_values(array_unique($groups)),
    "iat"           => $issued_at,
    "exp"           => $expiration
];

try {
    $jwt = JWT::encode($payload, $client->client_secret, 'HS256');
} catch (\Exception $e) {
    local_simple_sso_log_event('login_error_jwt', 'ERROR', $client_id, $redirect_uri, $e->getMessage());
    throw new \moodle_exception('jwterror', 'local_simple_sso', '', $e->getMessage());
}

local_simple_sso_log_event('login_success', 'SUCCESS', $client_id, $redirect_uri, '', '', $USER->id);

if ($response_mode === 'query') {
    $delimiter = (strpos($redirect_uri, '?') !== false) ? '&' : '?';
    $final_destination = $redirect_uri . $delimiter . 'token=' . urlencode($jwt);
} else {
    $final_destination = $redirect_uri . '#token=' . urlencode($jwt);
}

redirect($final_destination);
