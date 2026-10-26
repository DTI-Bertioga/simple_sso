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
 * OIDC Token endpoint for client credentials authentication and token issuance.
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

$client_id     = optional_param('client_id', '', PARAM_RAW);
$client_secret = optional_param('client_secret', '', PARAM_RAW);

// Support HTTP Basic Authentication header (client_id:client_secret).
if (empty($client_id) && isset($_SERVER['PHP_AUTH_USER'])) {
    $client_id     = $_SERVER['PHP_AUTH_USER'];
    $client_secret = $_SERVER['PHP_AUTH_PW'] ?? '';
}

if (empty($client_id) || empty($client_secret)) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', $client_id, '', 'Missing client credentials.');
    http_response_code(400);
    echo json_encode([
        'error'             => 'invalid_request',
        'error_description' => 'Missing client_id or client_secret.'
    ]);
    exit;
}

// Fetch active client application from database.
$client = $DB->get_record('local_simple_sso_clients', [
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'enabled'       => 1
]);

if (!$client) {
    local_simple_sso_log_event('token_request_failed', 'BLOCKED', $client_id, '', 'Invalid client credentials.');
    http_response_code(401);
    echo json_encode([
        'error'             => 'invalid_client',
        'error_description' => 'Invalid client credentials or disabled client.'
    ]);
    exit;
}

// Issue signed JWT access token for client.
$issued_at  = time();
$expiration = $issued_at + 300; // Valid for 5 minutes.

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

// Return standard OIDC / OAuth2 token response payload.
echo json_encode([
    'access_token' => $jwt,
    'token_type'   => 'Bearer',
    'expires_in'   => 300
]);
