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
 * English language strings for local_simple_sso.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Simple SSO / OIDC Authentication';
$string['jwt_settings'] = 'Global JWT Token Settings';
$string['invalidredirecturl'] = 'Invalid or missing redirect URL.';
$string['invalidclient'] = 'Client application not found or disabled.';
$string['invalidredirecturi'] = 'Redirect URI is not authorized in the whitelist.';
$string['jwterror'] = 'Error generating or signing JWT token.';
$string['guestnotallowed'] = 'Guest users are not allowed to authenticate via SSO.';
$string['acessorestritoservidor'] = 'Restricted Access: The application "{$a}" is exclusive to active public servants.';
$string['acessorestritocontribuinte'] = 'Restricted Access: The application "{$a}" is exclusive to citizens.';

// Management UI Strings.
$string['client_added_success'] = 'Application "{$a}" registered successfully.';
$string['client_updated_success'] = 'Application target audience updated successfully.';
$string['client_deleted_success'] = 'Application removed successfully.';
$string['logs_cleared_success'] = 'Audit log history cleared successfully.';
$string['endpoints_title'] = 'OIDC / OAuth2 Integration Endpoints';
$string['endpoint_authorization'] = 'Authorization Endpoint';
$string['endpoint_login'] = 'Login Endpoint';
$string['endpoint_token'] = 'Token Endpoint';
$string['endpoint_userinfo'] = 'Userinfo Endpoint';
$string['registered_applications'] = 'Registered Applications';
$string['col_name'] = 'Application Name';
$string['col_client_id'] = 'Client ID';
$string['col_client_secret'] = 'Client Secret';
$string['col_redirect_uris'] = 'Allowed Redirect URIs (Whitelist)';
$string['col_audience'] = 'Allowed Target Audience';
$string['audience_label'] = 'Allowed Target Audience';
$string['audience_todos'] = 'All (Public Servants + Citizens)';
$string['audience_servidor'] = 'Public Servants Only (Active HR Contracts)';
$string['audience_contribuinte'] = 'Citizens Only';
$string['audience_help'] = 'Select who is allowed to authenticate to this application via SSO.';
$string['col_actions'] = 'Actions';
$string['delete'] = 'Delete';
$string['confirm_delete_client'] = 'Are you sure you want to delete this application?';
$string['no_clients'] = 'No applications registered yet.';
$string['add_new_application'] = 'Register New Application';
$string['app_name'] = 'Application Name';
$string['app_name_placeholder'] = 'Ex: GLPI / Requisitions System';
$string['allowed_redirect_uris'] = 'Allowed Redirect URIs (Whitelist)';
$string['redirect_uris_placeholder'] = "https://requisitions.company.gov.br/callback\nhttp://localhost/sso/callback";
$string['redirect_uris_help'] = 'One URL per line. Only these exact URLs may request login.';
$string['save_application'] = 'Save Application';
$string['audit_logs_title'] = 'Audit & SSO Log History';
$string['clear_logs'] = 'Clear Log History';
$string['confirm_clear_logs'] = 'Are you sure you want to clear the log history?';
$string['col_datetime'] = 'Date/Time';
$string['col_event'] = 'Event';
$string['col_status'] = 'Status';
$string['col_user'] = 'User';
$string['col_ip'] = 'IP Address';
$string['col_details'] = 'Details / Requested URL';
$string['no_logs'] = 'No audit events recorded yet.';
$string['anonymous_or_system'] = 'Anonymous / System';

// Privacy API Strings.
$string['privacy:metadata'] = 'The Simple SSO Authentication plugin stores audit logs about authentication attempts and transmits profile data to authorized client applications.';
$string['privacy:metadata:local_simple_sso_logs'] = 'Audit records of SSO authentication requests and administrative changes.';
$string['privacy:metadata:local_simple_sso_logs:userid'] = 'The ID of the user who attempted authentication or performed administrative actions.';
$string['privacy:metadata:local_simple_sso_logs:client_id'] = 'The identifier of the client application that requested SSO.';
$string['privacy:metadata:local_simple_sso_logs:eventtype'] = 'The type of event recorded.';
$string['privacy:metadata:local_simple_sso_logs:status'] = 'The status of the authentication request (e.g. SUCCESS or BLOCKED).';
$string['privacy:metadata:local_simple_sso_logs:ip'] = 'The IP address of the user or system making the request.';
$string['privacy:metadata:local_simple_sso_logs:user_agent'] = 'The User-Agent string of the browser or HTTP client.';
$string['privacy:metadata:local_simple_sso_logs:redirect_uri'] = 'The redirect URI requested by the client application.';
$string['privacy:metadata:local_simple_sso_logs:timecreated'] = 'The date and time the audit log was created.';
$string['privacy:metadata:external'] = 'User profile information transmitted to external client applications following successful SSO authentication.';
$string['privacy:metadata:external:sub'] = 'The Moodle username of the authenticated user.';
$string['privacy:metadata:external:email'] = 'The email address of the authenticated user.';
$string['privacy:metadata:external:firstname'] = 'The first name of the authenticated user.';
$string['privacy:metadata:external:lastname'] = 'The last name of the authenticated user.';
$string['privacy:metadata:external:groups'] = 'The Moodle cohorts or groups assigned to the user.';
