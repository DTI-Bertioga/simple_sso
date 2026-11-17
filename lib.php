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
 * Helper functions for local_simple_sso.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Validates whether the provided redirect_uri is authorized against the whitelist of allowed URIs.
 *
 * @param string $redirect_uri Requested redirect URL.
 * @param string $allowed_uris_text Line-separated text of allowed redirect URIs.
 * @return bool True if valid and authorized, false otherwise.
 */
function local_simple_sso_validate_redirect_uri(string $redirect_uri, string $allowed_uris_text): bool {
    if (empty($redirect_uri) || !filter_var($redirect_uri, FILTER_VALIDATE_URL)) {
        return false;
    }

    $target = parse_url($redirect_uri);
    if (!$target || empty($target['scheme']) || empty($target['host'])) {
        return false;
    }

    $allowed_uris = array_map('trim', explode("\n", str_replace("\r", "", $allowed_uris_text)));

    foreach ($allowed_uris as $allowed) {
        if (empty($allowed)) {
            continue;
        }

        // Exact match comparison first.
        if ($redirect_uri === $allowed) {
            return true;
        }

        $allowed_parsed = parse_url($allowed);
        if (!$allowed_parsed || empty($allowed_parsed['scheme']) || empty($allowed_parsed['host'])) {
            continue;
        }

        // 1. Validate Scheme (http/https).
        if (strtolower($target['scheme']) !== strtolower($allowed_parsed['scheme'])) {
            continue;
        }

        // 2. Validate Host.
        if (strtolower($target['host']) !== strtolower($allowed_parsed['host'])) {
            continue;
        }

        // 3. Validate Port if specified in allowed.
        if (isset($allowed_parsed['port']) && ($target['port'] ?? null) !== $allowed_parsed['port']) {
            continue;
        }

        // 4. Validate Path.
        $allowed_path = $allowed_parsed['path'] ?? '/';
        $target_path  = $target['path'] ?? '/';

        if ($allowed_path === '' || $allowed_path === null) {
            $allowed_path = '/';
        }
        if ($target_path === '' || $target_path === null) {
            $target_path = '/';
        }

        if ($allowed_path === $target_path) {
            return true;
        }

        // Check path prefix if allowed path ends with a slash.
        if (strpos($target_path, $allowed_path) === 0) {
            if (substr($allowed_path, -1) === '/' || substr($target_path, strlen($allowed_path), 1) === '/') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Logs an audit or authentication event into the local_simple_sso_logs database table.
 *
 * @param string $eventtype Event type identifier.
 * @param string $status Event status ('SUCCESS', 'BLOCKED', 'ERROR').
 * @param string $client_id Associated client application ID.
 * @param string $redirect_uri Requested redirect URI.
 * @param string $failure_reason Description of failure or note.
 * @param string $endpoint Script or endpoint executed.
 * @param int|null $userid Moodle user ID.
 * @return bool True on success, false on failure.
 */
function local_simple_sso_log_event(
    string $eventtype,
    string $status = 'SUCCESS',
    string $client_id = '',
    string $redirect_uri = '',
    string $failure_reason = '',
    string $endpoint = '',
    ?int $userid = null
): bool {
    global $DB, $USER;

    try {
        if ($userid === null) {
            $userid = (!empty($USER) && isset($USER->id)) ? (int)$USER->id : 0;
        }

        if (empty($endpoint)) {
            $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'unknown');
        }

        $log = new \stdClass();
        $log->client_id      = substr($client_id, 0, 100);
        $log->userid         = $userid;
        $log->eventtype      = substr($eventtype, 0, 50);
        $log->status         = substr($status, 0, 20);
        $log->endpoint       = substr($endpoint, 0, 100);
        $log->http_method    = substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 10);
        $log->redirect_uri   = $redirect_uri;
        $log->failure_reason = $failure_reason;
        $log->ip             = getremoteaddr();
        $log->user_agent     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log->timecreated    = time();

        $DB->insert_record('local_simple_sso_logs', $log);
        return true;
    } catch (\Exception $e) {
        // Log failure should not break application flow.
        return false;
    }
}

/**
 * Base64URL encodes data according to RFC 7515 / RFC 7636.
 *
 * @param string $data Raw binary data.
 * @return string Base64url encoded string.
 */
function local_simple_sso_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Ensures that the local_simple_sso_codes database table exists.
 */
function local_simple_sso_ensure_codes_table_exists(): void {
    global $DB;
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_simple_sso_codes')) {
        $table = new \xmldb_table('local_simple_sso_codes');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('client_id', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('redirect_uri', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('code_challenge', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('code_challenge_method', XMLDB_TYPE_CHAR, '10', null, null, null, 'S256');
        $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('code_uniq', XMLDB_KEY_UNIQUE, ['code']);

        $table->add_index('client_id_idx', XMLDB_INDEX_NOTUNIQUE, ['client_id']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('expires_at_idx', XMLDB_INDEX_NOTUNIQUE, ['expires_at']);

        $dbman->create_table($table);
    }
}

/**
 * Generates and stores a temporary authorization code for PKCE / OAuth2 authorization code flow.
 *
 * @param string $client_id Client ID requesting authorization.
 * @param int $userid Authenticated Moodle user ID.
 * @param string $redirect_uri Authorized redirect URI.
 * @param string|null $code_challenge PKCE Code Challenge string.
 * @param string $code_challenge_method Challenge method ('S256' or 'plain').
 * @return string Generated 64-character authorization code.
 */
function local_simple_sso_generate_auth_code(
    string $client_id,
    int $userid,
    string $redirect_uri,
    ?string $code_challenge = null,
    string $code_challenge_method = 'S256'
): string {
    global $DB;

    local_simple_sso_ensure_codes_table_exists();

    // Purge expired authorization codes.
    $DB->delete_records_select('local_simple_sso_codes', 'expires_at < ?', [time()]);

    $code = bin2hex(random_bytes(32));

    $record = new \stdClass();
    $record->code                  = $code;
    $record->client_id             = $client_id;
    $record->userid                = $userid;
    $record->redirect_uri          = $redirect_uri;
    $record->code_challenge        = trim((string)$code_challenge);
    $record->code_challenge_method = strtoupper(trim($code_challenge_method));
    $record->expires_at            = time() + 300; // 5 minutes lifetime.
    $record->timecreated           = time();

    $DB->insert_record('local_simple_sso_codes', $record);

    return $code;
}

/**
 * Consumes and validates a single-use authorization code and PKCE code_verifier.
 *
 * @param string $code Authorization code.
 * @param string $client_id Client ID exchanging the code.
 * @param string|null $code_verifier PKCE Code Verifier string provided by client.
 * @param string|null &$error_out Variable reference to store failure reason if invalid.
 * @return \stdClass|null Returns code record object if valid, null otherwise.
 */
function local_simple_sso_consume_auth_code(
    string $code,
    string $client_id,
    ?string $code_verifier = null,
    ?string &$error_out = null
): ?\stdClass {
    global $DB;

    local_simple_sso_ensure_codes_table_exists();

    $record = $DB->get_record('local_simple_sso_codes', ['code' => trim($code)]);
    if (!$record) {
        $error_out = 'Authorization code not found in database or already consumed.';
        return null;
    }

    // Delete record immediately for single-use guarantee.
    $DB->delete_records('local_simple_sso_codes', ['id' => $record->id]);

    // Check expiration.
    if ((int)$record->expires_at < time()) {
        $error_out = 'Authorization code has expired (exceeded 5 minutes).';
        return null;
    }

    // Check client_id match.
    if (trim($record->client_id) !== trim($client_id)) {
        $error_out = 'Client ID mismatch. Saved: ' . $record->client_id . ' Provided: ' . $client_id;
        return null;
    }

    // Validate PKCE code_verifier if code_challenge was saved during authorization.
    if (!empty($record->code_challenge)) {
        $verifier = trim((string)$code_verifier);
        if (empty($verifier)) {
            $error_out = 'Missing code_verifier parameter in token request.';
            return null;
        }

        $saved_challenge = trim($record->code_challenge);
        $method          = strtoupper(trim($record->code_challenge_method ?? 'S256'));

        if ($method === 'S256' || $method === 'S') {
            $computed_challenge = local_simple_sso_base64url_encode(hash('sha256', $verifier, true));
            if (!hash_equals($saved_challenge, $computed_challenge)) {
                $error_out = 'PKCE Verification Failed! Saved Challenge: [' . $saved_challenge . '] vs Computed: [' . $computed_challenge . '] for Verifier: [' . $verifier . ']';
                return null;
            }
        } else if ($method === 'PLAIN') {
            if (!hash_equals($saved_challenge, $verifier)) {
                $error_out = 'PKCE Plain Verification Failed!';
                return null;
            }
        } else {
            $error_out = 'Unsupported code_challenge_method: ' . $method;
            return null;
        }
    }

    return $record;
}
