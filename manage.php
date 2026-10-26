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
 * Management UI for local_simple_sso applications and audit logs.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require site administration permissions.
admin_externalpage_setup('local_simple_sso_manage');

global $DB, $OUTPUT, $PAGE, $USER;

$PAGE->set_url(new moodle_url('/local/simple_sso/manage.php'));
$PAGE->set_title(get_string('pluginname', 'local_simple_sso'));
$PAGE->set_heading(get_string('pluginname', 'local_simple_sso'));

// 1. Process New Application Registration.
if (optional_param('action', '', PARAM_ALPHA) === 'addclient' && confirm_sesskey()) {
    $name         = required_param('client_name', PARAM_TEXT);
    $redirect_uri = required_param('client_redirect_uri', PARAM_RAW);

    $client = new stdClass();
    $client->name          = $name;
    $client->client_id     = 'client_' . bin2hex(random_bytes(8));
    $client->client_secret = bin2hex(random_bytes(24));
    $client->redirect_uri  = trim($redirect_uri);
    $client->enabled       = 1;
    $client->timecreated   = time();

    $DB->insert_record('local_simple_sso_clients', $client);

    // Log audit event.
    local_simple_sso_log_event('client_created', 'SUCCESS', $client->client_id, $client->redirect_uri, "Application '{$name}' created.");

    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('client_added_success', 'local_simple_sso', $name), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 2. Process Application Deletion.
if (optional_param('action', '', PARAM_ALPHA) === 'deleteclient' && confirm_sesskey()) {
    $client_id_num = required_param('id', PARAM_INT);
    $c = $DB->get_record('local_simple_sso_clients', ['id' => $client_id_num]);
    if ($c) {
        $DB->delete_records('local_simple_sso_clients', ['id' => $client_id_num]);
        local_simple_sso_log_event('client_deleted', 'SUCCESS', $c->client_id, '', "Application '{$c->name}' deleted.");
    }
    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('client_deleted_success', 'local_simple_sso'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 3. Process Audit Logs Clear Action.
if (optional_param('action', '', PARAM_ALPHA) === 'clearlogs' && confirm_sesskey()) {
    $DB->delete_records('local_simple_sso_logs');
    local_simple_sso_log_event('logs_cleared', 'SUCCESS', '', '', 'Audit logs cleared by administrator.');
    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('logs_cleared_success', 'local_simple_sso'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Prepare Mustache Template Data.
$base_url = $CFG->wwwroot . '/local/simple_sso';

// Registered Clients.
$clients_records = $DB->get_records('local_simple_sso_clients');
$clients_data = [];

foreach ($clients_records as $c) {
    $del_url = new moodle_url('/local/simple_sso/manage.php', [
        'action'  => 'deleteclient',
        'id'      => $c->id,
        'sesskey' => sesskey()
    ]);
    $clients_data[] = [
        'name'         => s($c->name),
        'clientid'     => s($c->client_id),
        'clientsecret' => s($c->client_secret),
        'redirecturi'  => nl2br(s($c->redirect_uri)),
        'deleteurl'    => $del_url->out(false),
    ];
}

// Audit Logs.
$logs_records = $DB->get_records('local_simple_sso_logs', null, 'timecreated DESC', '*', 0, 100);
$logs_data = [];

if (!empty($logs_records)) {
    $user_ids = array_filter(array_unique(array_column($logs_records, 'userid')));
    $users_map = [];
    if (!empty($user_ids)) {
        list($in_sql, $params) = $DB->get_in_or_equal($user_ids);
        $users_records = $DB->get_records_select('user', "id $in_sql", $params, '', 'id, firstname, lastname, username');
        foreach ($users_records as $u) {
            $users_map[$u->id] = fullname($u) . ' (' . $u->username . ')';
        }
    }

    foreach ($logs_records as $log) {
        $date_str = userdate($log->timecreated, '%d/%m/%Y %H:%M:%S');
        $status_badge = ($log->status === 'SUCCESS')
            ? '<span class="badge bg-success text-white">SUCCESS</span>'
            : '<span class="badge bg-danger text-white">' . s($log->status) . '</span>';

        $user_display = ($log->userid > 0 && isset($users_map[$log->userid]))
            ? s($users_map[$log->userid])
            : get_string('anonymous_or_system', 'local_simple_sso');

        $details = [];
        if (!empty($log->redirect_uri)) {
            $details[] = '<b>Redirect URI:</b> <code>' . s($log->redirect_uri) . '</code>';
        }
        if (!empty($log->failure_reason)) {
            $details[] = '<span class="text-danger"><b>Reason:</b> ' . s($log->failure_reason) . '</span>';
        }
        if (!empty($log->endpoint)) {
            $details[] = '<small class="text-muted">Endpoint: ' . s($log->endpoint) . '</small>';
        }

        $logs_data[] = [
            'datetime'    => $date_str,
            'eventtype'   => s($log->eventtype),
            'statusbadge' => $status_badge,
            'clientid'    => s($log->client_id ?: '-'),
            'userdisplay' => $user_display,
            'ip'          => s($log->ip),
            'details'     => !empty($details) ? implode('<br>', $details) : '-',
        ];
    }
}

$template_context = [
    'baseurl'      => $base_url,
    'actionurl'    => (new moodle_url('/local/simple_sso/manage.php'))->out(false),
    'clearlogsurl' => (new moodle_url('/local/simple_sso/manage.php', ['action' => 'clearlogs', 'sesskey' => sesskey()]))->out(false),
    'sesskey'      => sesskey(),
    'hasclients'   => !empty($clients_data),
    'clients'      => $clients_data,
    'haslogs'      => !empty($logs_data),
    'logs'         => $logs_data,
];

echo $OUTPUT->render_from_template('local_simple_sso/manage', $template_context);

echo $OUTPUT->footer();
