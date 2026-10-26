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
 * Brazilian Portuguese language strings for local_simple_sso.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Autenticação Simples SSO / OIDC';
$string['jwt_settings'] = 'Configurações Globais do Token JWT';
$string['invalidredirecturl'] = 'URL de redirecionamento inválida ou ausente.';
$string['invalidclient'] = 'Aplicação cliente não encontrada ou inativa.';
$string['invalidredirecturi'] = 'A URL de redirecionamento não está autorizada na lista branca.';
$string['jwterror'] = 'Erro ao gerar ou assinar o token JWT.';
$string['guestnotallowed'] = 'Usuários convidados (guest) não têm permissão para autenticar via SSO.';

// Strings da Interface de Gestão.
$string['client_added_success'] = 'Aplicação "{$a}" cadastrada com sucesso.';
$string['client_deleted_success'] = 'Aplicação removida com sucesso.';
$string['logs_cleared_success'] = 'Histórico de logs de auditoria limpo com sucesso.';
$string['endpoints_title'] = 'Endpoints OIDC / OAuth2 para Integração';
$string['endpoint_authorization'] = 'Endpoint de Autorização';
$string['endpoint_login'] = 'Endpoint de Login';
$string['endpoint_token'] = 'Endpoint de Token';
$string['endpoint_userinfo'] = 'Endpoint Userinfo';
$string['registered_applications'] = 'Aplicações Cadastradas';
$string['col_name'] = 'Nome da Aplicação';
$string['col_client_id'] = 'Client ID';
$string['col_client_secret'] = 'Client Secret';
$string['col_redirect_uris'] = 'URLs de Redirecionamento Permitidas (Lista Branca)';
$string['col_actions'] = 'Ações';
$string['delete'] = 'Excluir';
$string['confirm_delete_client'] = 'Deseja realmente excluir esta aplicação?';
$string['no_clients'] = 'Nenhuma aplicação cadastrada ainda.';
$string['add_new_application'] = 'Cadastrar Nova Aplicação';
$string['app_name'] = 'Nome da Aplicação';
$string['app_name_placeholder'] = 'Ex: GLPI / Sistema de Requerimentos';
$string['allowed_redirect_uris'] = 'URLs de Redirecionamento Permitidas (Lista Branca)';
$string['redirect_uris_placeholder'] = "https://requerimentos.empresa.gov.br/callback\nhttp://localhost/sso/callback";
$string['redirect_uris_help'] = 'Uma URL por linha. Apenas estas URLs exatas poderão solicitar login.';
$string['save_application'] = 'Salvar Aplicação';
$string['audit_logs_title'] = 'Histórico de Auditoria & Logs SSO';
$string['clear_logs'] = 'Limpar Histórico de Logs';
$string['confirm_clear_logs'] = 'Deseja realmente limpar o histórico de logs?';
$string['col_datetime'] = 'Data/Hora';
$string['col_event'] = 'Evento';
$string['col_status'] = 'Status';
$string['col_user'] = 'Usuário';
$string['col_ip'] = 'Endereço IP';
$string['col_details'] = 'Detalhes / URL Solicitada';
$string['no_logs'] = 'Nenhum evento de auditoria registrado até o momento.';
$string['anonymous_or_system'] = 'Anônimo / Sistema';

// Strings de Privacidade (Privacy API).
$string['privacy:metadata'] = 'O plugin Autenticação Simples SSO armazena logs de auditoria sobre tentativas de autenticação e transmite dados de perfil para aplicações clientes autorizadas.';
$string['privacy:metadata:local_simple_sso_logs'] = 'Registros de auditoria das requisições de autenticação SSO e alterações administrativas.';
$string['privacy:metadata:local_simple_sso_logs:userid'] = 'O ID do usuário que tentou a autenticação ou realizou ações administrativas.';
$string['privacy:metadata:local_simple_sso_logs:client_id'] = 'O identificador da aplicação cliente que solicitou o SSO.';
$string['privacy:metadata:local_simple_sso_logs:eventtype'] = 'O tipo do evento registrado.';
$string['privacy:metadata:local_simple_sso_logs:status'] = 'O status do pedido de autenticação (ex: SUCCESS ou BLOCKED).';
$string['privacy:metadata:local_simple_sso_logs:ip'] = 'O endereço IP do usuário ou sistema que fez a requisição.';
$string['privacy:metadata:local_simple_sso_logs:user_agent'] = 'A string de User-Agent do navegador ou cliente HTTP.';
$string['privacy:metadata:local_simple_sso_logs:redirect_uri'] = 'A URL de redirecionamento solicitada pela aplicação cliente.';
$string['privacy:metadata:local_simple_sso_logs:timecreated'] = 'A data e hora de criação do registro de auditoria.';
$string['privacy:metadata:external'] = 'Informações de perfil do usuário transmitidas para aplicações clientes externas após autenticação SSO bem-sucedida.';
$string['privacy:metadata:external:sub'] = 'O nome de usuário Moodle do usuário autenticado.';
$string['privacy:metadata:external:email'] = 'O endereço de e-mail do usuário autenticado.';
$string['privacy:metadata:external:firstname'] = 'O primeiro nome do usuário autenticado.';
$string['privacy:metadata:external:lastname'] = 'O sobrenome do usuário autenticado.';
$string['privacy:metadata:external:groups'] = 'As coortes ou grupos Moodle atribuídos ao usuário.';
