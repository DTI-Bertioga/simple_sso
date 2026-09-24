<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Exige permissão de administrador do site
admin_externalpage_setup('local_simple_sso_manage');

global $DB, $OUTPUT, $PAGE, $USER;

$PAGE->set_url(new moodle_url('/local/simple_sso/manage.php'));
$PAGE->set_title(get_string('pluginname', 'local_simple_sso'));
$PAGE->set_heading(get_string('pluginname', 'local_simple_sso'));

// 1. Processar Cadastro de Nova Aplicação
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

    // Registrar log de auditoria
    local_simple_sso_log_event('client_created', 'SUCCESS', $client->client_id, $client->redirect_uri, "Aplicação '{$name}' cadastrada.");

    redirect(new moodle_url('/local/simple_sso/manage.php'), "Aplicação '{$name}' cadastrada com sucesso!", null, \core\output\notification::NOTIFY_SUCCESS);
}

// 2. Processar Exclusão de Aplicação
if (optional_param('action', '', PARAM_ALPHA) === 'deleteclient' && confirm_sesskey()) {
    $client_id_num = required_param('id', PARAM_INT);
    $c = $DB->get_record('local_simple_sso_clients', ['id' => $client_id_num]);
    if ($c) {
        $DB->delete_records('local_simple_sso_clients', ['id' => $client_id_num]);
        local_simple_sso_log_event('client_deleted', 'SUCCESS', $c->client_id, '', "Aplicação '{$c->name}' excluída.");
    }
    redirect(new moodle_url('/local/simple_sso/manage.php'), "Aplicação removida com sucesso!", null, \core\output\notification::NOTIFY_SUCCESS);
}

// 3. Processar Limpeza de Logs
if (optional_param('action', '', PARAM_ALPHA) === 'clearlogs' && confirm_sesskey()) {
    $DB->delete_records('local_simple_sso_logs');
    local_simple_sso_log_event('logs_cleared', 'SUCCESS', '', '', 'Histórico de auditoria limpo pelo administrador.');
    redirect(new moodle_url('/local/simple_sso/manage.php'), "Histórico de logs limpo com sucesso!", null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Endpoints Info
$base_url = $CFG->wwwroot . '/local/simple_sso';
echo '<div class="alert alert-info">
    <h5>Endpoints OIDC / OAuth2 para Integração</h5>
    <ul>
        <li><b>Authorization Endpoint:</b> <code>' . $base_url . '/authorize.php</code></li>
        <li><b>Login Endpoint:</b> <code>' . $base_url . '/login.php</code></li>
        <li><b>Token Endpoint:</b> <code>' . $base_url . '/token.php</code></li>
        <li><b>Userinfo Endpoint:</b> <code>' . $base_url . '/userinfo.php</code></li>
    </ul>
</div>';

// Tabela de Aplicações Cadastradas
$clients = $DB->get_records('local_simple_sso_clients');

echo '<div class="card p-3 mb-4">';
echo '<h4>Aplicações Cadastradas</h4>';
echo '<table class="table table-striped table-bordered align-middle">
<thead>
    <tr>
        <th>Nome</th>
        <th>Client ID</th>
        <th>Client Secret</th>
        <th>Redirect URIs (Lista Branca)</th>
        <th>Ações</th>
    </tr>
</thead>
<tbody>';

if (empty($clients)) {
    echo '<tr><td colspan="5" class="text-muted text-center">Nenhuma aplicação cadastrada ainda.</td></tr>';
} else {
    foreach ($clients as $c) {
        $del_url = new moodle_url('/local/simple_sso/manage.php', [
            'action'  => 'deleteclient',
            'id'      => $c->id,
            'sesskey' => sesskey()
        ]);
        echo "<tr>
            <td><b>" . s($c->name) . "</b></td>
            <td><code>" . s($c->client_id) . "</code></td>
            <td><code>" . s($c->client_secret) . "</code></td>
            <td><small>" . nl2br(s($c->redirect_uri)) . "</small></td>
            <td><a href='{$del_url}' class='btn btn-sm btn-danger' onclick='return confirm(\"Deseja realmente excluir esta aplicação?\");'>Excluir</a></td>
        </tr>";
    }
}
echo '</tbody></table></div>';

// Formulário de Cadastro
$action_url = new moodle_url('/local/simple_sso/manage.php');
echo '<form method="post" action="' . $action_url . '" class="card p-3 mb-4">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="addclient">';
echo '<h4>Cadastrar Nova Aplicação</h4>';
echo '<div class="form-group mb-3">
    <label><b>Nome da Aplicação</b></label>
    <input type="text" name="client_name" class="form-control" required placeholder="Ex: GLPI / Sistema de Requerimentos">
</div>';
echo '<div class="form-group mb-3">
    <label><b>URLs de Redirecionamento Permitidas (Lista Branca)</b></label>
    <textarea name="client_redirect_uri" class="form-control" rows="3" required placeholder="https://requerimentos.empresa.gov.br/callback&#10;http://localhost/local/simple_sso/test_sso.php"></textarea>
    <small class="text-muted">Uma URL por linha. Apenas estas URLs poderão solicitar login.</small>
</div>';
echo '<div><button type="submit" class="btn btn-primary">Salvar Aplicação</button></div>';
echo '</form>';

// Seção de Logs de Auditoria
$clear_logs_url = new moodle_url('/local/simple_sso/manage.php', [
    'action'  => 'clearlogs',
    'sesskey' => sesskey()
]);

echo '<div class="card p-3 mb-4">';
echo '<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Histórico de Auditoria & Logs SSO</h4>
    <a href="' . $clear_logs_url . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Deseja realmente limpar o histórico de logs?\');">Limpar Histórico de Logs</a>
</div>';

$logs = $DB->get_records('local_simple_sso_logs', null, 'timecreated DESC', '*', 0, 100);

echo '<table class="table table-sm table-striped table-hover table-bordered align-middle">
<thead>
    <tr>
        <th>Data/Hora</th>
        <th>Evento</th>
        <th>Status</th>
        <th>Client ID</th>
        <th>Usuário</th>
        <th>IP</th>
        <th>Detalhes / URL Solicitada</th>
    </tr>
</thead>
<tbody>';

if (empty($logs)) {
    echo '<tr><td colspan="7" class="text-muted text-center">Nenhum evento registrado até o momento.</td></tr>';
} else {
    // Busca cache dos nomes dos usuários
    $user_ids = array_filter(array_unique(array_column($logs, 'userid')));
    $users_map = [];
    if (!empty($user_ids)) {
        list($in_sql, $params) = $DB->get_in_or_equal($user_ids);
        $users_records = $DB->get_records_select('user', "id $in_sql", $params, '', 'id, firstname, lastname, username');
        foreach ($users_records as $u) {
            $users_map[$u->id] = fullname($u) . ' (' . $u->username . ')';
        }
    }

    foreach ($logs as $log) {
        $date_str = userdate($log->timecreated, '%d/%m/%Y %H:%M:%S');
        $status_badge = ($log->status === 'SUCCESS')
            ? '<span class="badge bg-success text-white">SUCCESS</span>'
            : '<span class="badge bg-danger text-white">' . s($log->status) . '</span>';

        $user_display = ($log->userid > 0 && isset($users_map[$log->userid]))
            ? s($users_map[$log->userid])
            : '<span class="text-muted">Anônimo / Sistema</span>';

        $details = [];
        if (!empty($log->redirect_uri)) {
            $details[] = '<b>Redirect URI:</b> <code>' . s($log->redirect_uri) . '</code>';
        }
        if (!empty($log->failure_reason)) {
            $details[] = '<span class="text-danger"><b>Motivo:</b> ' . s($log->failure_reason) . '</span>';
        }
        if (!empty($log->endpoint)) {
            $details[] = '<small class="text-muted">Endpoint: ' . s($log->endpoint) . '</small>';
        }

        $details_html = !empty($details) ? implode('<br>', $details) : '-';

        echo "<tr>
            <td><small>{$date_str}</small></td>
            <td><code>" . s($log->eventtype) . "</code></td>
            <td>{$status_badge}</td>
            <td><code>" . s($log->client_id ?: '-') . "</code></td>
            <td><small>{$user_display}</small></td>
            <td><small><code>" . s($log->ip) . "</code></small></td>
            <td><small>{$details_html}</small></td>
        </tr>";
    }
}
echo '</tbody></table></div>';

echo $OUTPUT->footer();
