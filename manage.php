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
    $name             = required_param('client_name', PARAM_TEXT);
    $redirect_uri     = required_param('client_redirect_uri', PARAM_RAW);
    $allowed_audience = optional_param('allowed_audience', 'todos', PARAM_ALPHA);

    $client = new stdClass();
    $client->name             = $name;
    $client->client_id        = 'client_' . bin2hex(random_bytes(8));
    $client->client_secret    = bin2hex(random_bytes(24));
    $client->redirect_uri     = trim($redirect_uri);
    $client->allowed_audience = in_array($allowed_audience, ['servidor', 'contribuinte', 'todos'], true) ? $allowed_audience : 'todos';
    $client->enabled          = 1;
    $client->timecreated      = time();

    $DB->insert_record('local_simple_sso_clients', $client);

    // Log audit event.
    local_simple_sso_log_event('client_created', 'SUCCESS', $client->client_id, $client->redirect_uri, "Application '{$name}' created (Audience: {$client->allowed_audience}).");

    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('client_added_success', 'local_simple_sso', $name), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 2. Process Application Audience Update.
if (optional_param('action', '', PARAM_ALPHA) === 'updateaudience' && confirm_sesskey()) {
    $client_id_num = required_param('id', PARAM_INT);
    $new_aud       = optional_param('allowed_audience', 'todos', PARAM_ALPHA);

    $c = $DB->get_record('local_simple_sso_clients', ['id' => $client_id_num]);
    if ($c) {
        $aud_clean = in_array($new_aud, ['servidor', 'contribuinte', 'todos'], true) ? $new_aud : 'todos';
        $DB->set_field('local_simple_sso_clients', 'allowed_audience', $aud_clean, ['id' => $client_id_num]);
        local_simple_sso_log_event('client_updated', 'SUCCESS', $c->client_id, '', "Application '{$c->name}' audience updated to '{$aud_clean}'.");
    }
    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('client_updated_success', 'local_simple_sso'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 3. Process Application Deletion.
if (optional_param('action', '', PARAM_ALPHA) === 'deleteclient' && confirm_sesskey()) {
    $client_id_num = required_param('id', PARAM_INT);
    $c = $DB->get_record('local_simple_sso_clients', ['id' => $client_id_num]);
    if ($c) {
        $DB->delete_records('local_simple_sso_clients', ['id' => $client_id_num]);
        local_simple_sso_log_event('client_deleted', 'SUCCESS', $c->client_id, '', "Application '{$c->name}' deleted.");
    }
    redirect(new moodle_url('/local/simple_sso/manage.php'), get_string('client_deleted_success', 'local_simple_sso'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 4. Process Audit Logs Clear Action.
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

    $aud = $c->allowed_audience ?? 'todos';
    $aud_badge = '';
    if ($aud === 'servidor') {
        $aud_badge = '<span class="badge bg-primary text-white p-2 font-weight-bold" style="background-color: #1351b4 !important;"><i class="fa fa-user-tie mr-1"></i> Apenas Servidores</span>';
    } else if ($aud === 'contribuinte') {
        $aud_badge = '<span class="badge bg-info text-white p-2 font-weight-bold" style="background-color: #17a2b8 !important;"><i class="fa fa-users mr-1"></i> Apenas Contribuintes</span>';
    } else {
        $aud_badge = '<span class="badge bg-secondary text-white p-2 font-weight-bold" style="background-color: #6c757d !important;"><i class="fa fa-globe mr-1"></i> Todos os Usuários</span>';
    }

    $clients_data[] = [
        'id'               => $c->id,
        'name'             => s($c->name),
        'clientid'         => s($c->client_id),
        'clientsecret'     => s($c->client_secret),
        'redirecturi'      => nl2br(s($c->redirect_uri)),
        'audience'         => $aud,
        'audiencebadge'    => $aud_badge,
        'is_todos'         => ($aud === 'todos'),
        'is_servidor'      => ($aud === 'servidor'),
        'is_contribuinte'  => ($aud === 'contribuinte'),
        'deleteurl'        => $del_url->out(false),
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
