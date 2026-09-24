<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Autenticação Simples SSO / OIDC';
$string['jwt_settings'] = 'Configurações Globais do Token JWT';
$string['invalidredirecturl'] = 'URL de redirecionamento inválida ou ausente.';
$string['invalidclient'] = 'Cliente não encontrado ou inativo.';
$string['invalidredirecturi'] = 'A URL de redirecionamento não está autorizada na lista branca.';
$string['jwterror'] = 'Erro ao gerar ou assinar o token JWT.';
$string['privacy:metadata'] = 'O plugin Autenticação Simples SSO armazena logs de auditoria sobre tentativas de autenticação e ações administrativas.';
$string['privacy:metadata:local_simple_sso_logs'] = 'Registros de auditoria das requisições de autenticação SSO e alterações administrativas.';
$string['privacy:metadata:local_simple_sso_logs:userid'] = 'O ID do usuário que tentou a autenticação ou realizou ações administrativas.';
$string['privacy:metadata:local_simple_sso_logs:client_id'] = 'O identificador da aplicação cliente que solicitou o SSO.';
$string['privacy:metadata:local_simple_sso_logs:eventtype'] = 'O tipo do evento registrado.';
$string['privacy:metadata:local_simple_sso_logs:status'] = 'O status do pedido de autenticação (ex: SUCCESS ou BLOCKED).';
$string['privacy:metadata:local_simple_sso_logs:ip'] = 'O endereço IP do usuário ou sistema que fez a requisição.';
$string['privacy:metadata:local_simple_sso_logs:user_agent'] = 'A string de User-Agent do navegador ou cliente HTTP.';
$string['privacy:metadata:local_simple_sso_logs:redirect_uri'] = 'A URL de redirecionamento solicitada pela aplicação cliente.';
$string['privacy:metadata:local_simple_sso_logs:timecreated'] = 'A data e hora de criação do registro de auditoria.';
