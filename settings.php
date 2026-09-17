<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $CFG;

    // 1. Cria a categoria PAI com o nome do plugin dentro de "Plugins locais"
    $ADMIN->add('localplugins', new admin_category(
        'local_simple_sso_category', 
        get_string('pluginname', 'local_simple_sso')
    ));

    // 2. Adiciona a página externa "Gerenciar Aplicações" como FILHA da categoria do plugin
    $ADMIN->add('local_simple_sso_category', new admin_externalpage(
        'local_simple_sso_manage',
        'Gerenciar Aplicações SSO / OIDC',
        new moodle_url('/local/simple_sso/manage.php')
    ));

    // 3. Adiciona a página de "Configurações Adicionais" como FILHA da categoria do plugin
    //$settings = new admin_settingpage('local_simple_sso_settings', 'Configurações Adicionais');

    $manage_url = new moodle_url('/local/simple_sso/manage.php');
    $info_html = '<div class="alert alert-primary">
        <h5>Gerenciamento de Aplicações SSO / OIDC</h5>
        <p>Para cadastrar, visualizar ou remover aplicações clientes (Client ID / Client Secret), acesse o painel dedicado:</p>
        <a href="' . $manage_url . '" class="btn btn-primary">Abrir Painel de Gestão de Aplicações</a>
    </div>';

    //$settings->add(new admin_setting_heading('local_simple_sso/manage_link', '', $info_html));

    //$ADMIN->add('local_simple_sso_category', $settings);
}
