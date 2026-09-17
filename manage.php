<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Exige permissão de administrador do site
admin_externalpage_setup('local_simple_sso_manage');

global $DB, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/simple_sso/manage.php'));
$PAGE->set_title('Gerenciar Aplicações SSO / OIDC');
$PAGE->set_heading('Gerenciar Aplicações SSO / OIDC');

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
    redirect(new moodle_url('/local/simple_sso/manage.php'), "Aplicação '{$name}' cadastrada com sucesso!", null, \core\output\notification::NOTIFY_SUCCESS);
}

// 2. Processar Exclusão de Aplicação
if (optional_param('action', '', PARAM_ALPHA) === 'deleteclient' && confirm_sesskey()) {
    $client_id = required_param('id', PARAM_INT);
    $DB->delete_records('local_simple_sso_clients', ['id' => $client_id]);
    redirect(new moodle_url('/local/simple_sso/manage.php'), "Aplicação removida com sucesso!", null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

// Endpoints Info
$base_url = $CFG->wwwroot . '/local/simple_sso';
echo '<div class="alert alert-info">
    <h5>Endpoints OIDC / OAuth2 para Integração</h5>
    <ul>
        <li><b>Authorization Endpoint:</b> <code>' . $base_url . '/authorize.php</code></li>
        <li><b>Token Endpoint:</b> <code>' . $base_url . '/token.php</code></li>
        <li><b>Userinfo Endpoint:</b> <code>' . $base_url . '/userinfo.php</code></li>
    </ul>
</div>';

// Tabela de Aplicações Cadastradas
$clients = $DB->get_records('local_simple_sso_clients');

echo '<div class="card p-3 mb-4">';
echo '<h4>Aplicações Cadastradas</h4>';
echo '<table class="table table-striped table-bordered">
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
echo '<form method="post" action="' . $action_url . '" class="card p-3">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="addclient">';
echo '<h4>Cadastrar Nova Aplicação</h4>';
echo '<div class="form-group mb-3">
    <label><b>Nome da Aplicação</b></label>
    <input type="text" name="client_name" class="form-control" required placeholder="Ex: GLPI / Sistema de Requerimentos">
</div>';
echo '<div class="form-group mb-3">
    <label><b>URLs de Redirecionamento Permitidas (Lista Branca)</b></label>
    <textarea name="client_redirect_uri" class="form-control" rows="3" required placeholder="https://requerimentos.empresa.gov.br/callback&#10;http://192.168.56.105/local/simple_sso/test_sso.php"></textarea>
    <small class="text-muted">Uma URL por linha. Apenas estas URLs poderão solicitar login.</small>
</div>';
echo '<div><button type="submit" class="btn btn-primary">Salvar Aplicação</button></div>';
echo '</form>';

echo $OUTPUT->footer();
