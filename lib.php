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
