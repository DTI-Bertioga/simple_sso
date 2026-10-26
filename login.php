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
 * SSO Login endpoint alias.
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

$client_id     = required_param('client_id', PARAM_RAW);
$redirect_uri  = required_param('redirect_uri', PARAM_RAW);
$response_mode = optional_param('response_mode', 'fragment', PARAM_ALPHA);

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

// 3. Collect Moodle cohorts/groups.
require_once($CFG->dirroot . '/cohort/lib.php');
$groups = [];
$user_cohorts = cohort_get_user_cohorts($USER->id);
if (!empty($user_cohorts)) {
    foreach ($user_cohorts as $cohort) {
        $groups[] = $cohort->idnumber ?: $cohort->name;
    }
}

// 4. Construct JWT Payload and sign with client's secret.
$issued_at  = time();
$expiration = $issued_at + 300; // Valid for 5 minutes.

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
} catch (\Exception $e) {
    local_simple_sso_log_event('login_error_jwt', 'ERROR', $client_id, $redirect_uri, $e->getMessage());
    throw new \moodle_exception('jwterror', 'local_simple_sso', '', $e->getMessage());
}

// Log successful authentication.
local_simple_sso_log_event('login_success', 'SUCCESS', $client_id, $redirect_uri);

// 5. Redirect back using URI fragment (#token=) per OIDC Implicit flow specification, or query string if requested.
if ($response_mode === 'query') {
    $delimiter = (strpos($redirect_uri, '?') !== false) ? '&' : '?';
    $final_destination = $redirect_uri . $delimiter . 'token=' . urlencode($jwt);
} else {
    $final_destination = $redirect_uri . '#token=' . urlencode($jwt);
}

redirect($final_destination);
