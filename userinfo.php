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
 * OIDC UserInfo endpoint returning current Moodle user profile data.
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

// 4. Bind and fetch fresh current Moodle user data for the 'sub' username.
$user = $DB->get_record('user', ['username' => $decoded->sub, 'deleted' => 0, 'suspended' => 0]);
if (!$user) {
    local_simple_sso_log_event('userinfo_access_failed', 'BLOCKED', $client_id ?? '', '', 'User not found or suspended.');
    http_response_code(404);
    echo json_encode([
        'error'             => 'user_not_found',
        'error_description' => 'Moodle user not found or active.'
    ]);
    exit;
}

// Fetch user cohorts/groups.
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($user->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

local_simple_sso_log_event('userinfo_access', 'SUCCESS', $decoded->aud ?? $client_id ?? '', '', '', '', $user->id);

// 5. Return current user profile data in standard OIDC UserInfo format.
echo json_encode([
    'sub'         => $user->username,
    'email'       => $user->email,
    'given_name'  => $user->firstname,
    'family_name' => $user->lastname,
    'name'        => fullname($user),
    'groups'      => array_values(array_unique($groups))
]);
