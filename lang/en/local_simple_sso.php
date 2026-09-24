<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Simple SSO / OIDC Authentication';
$string['jwt_settings'] = 'Global JWT Token Settings';
$string['invalidredirecturl'] = 'Invalid or missing redirect URL.';
$string['invalidclient'] = 'Client not found or disabled.';
$string['invalidredirecturi'] = 'The redirect URL is not authorized in the whitelist.';
$string['jwterror'] = 'Error generating or signing the JWT token.';
$string['privacy:metadata'] = 'The Simple SSO plugin stores audit logs of authentication attempts and administrative actions.';
$string['privacy:metadata:local_simple_sso_logs'] = 'Audit log records for SSO authentication requests and administrative changes.';
$string['privacy:metadata:local_simple_sso_logs:userid'] = 'The ID of the user attempting authentication or performing administrative actions.';
$string['privacy:metadata:local_simple_sso_logs:client_id'] = 'The identifier of the client application requesting SSO.';
$string['privacy:metadata:local_simple_sso_logs:eventtype'] = 'The type of event logged.';
$string['privacy:metadata:local_simple_sso_logs:status'] = 'The status of the authentication request (e.g. SUCCESS or BLOCKED).';
$string['privacy:metadata:local_simple_sso_logs:ip'] = 'The IP address of the user or system making the request.';
$string['privacy:metadata:local_simple_sso_logs:user_agent'] = 'The user agent string of the browser or HTTP client.';
$string['privacy:metadata:local_simple_sso_logs:redirect_uri'] = 'The redirect URL requested by the client application.';
$string['privacy:metadata:local_simple_sso_logs:timecreated'] = 'The timestamp when the audit log event occurred.';
