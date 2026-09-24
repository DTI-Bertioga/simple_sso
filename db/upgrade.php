<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Funções de Upgrade do Banco de Dados para local_simple_sso.
 *
 * @param int $oldversion A versão antiga do plugin antes do upgrade.
 * @return bool True em caso de sucesso.
 */
function xmldb_local_simple_sso_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026080303) {
        // Define a tabela local_simple_sso_logs.
        $table = new xmldb_table('local_simple_sso_logs');

        // Adiciona campos.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('client_id', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('endpoint', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('http_method', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('redirect_uri', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('failure_reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('ip', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('user_agent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Chaves.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Índices.
        $table->add_index('client_id_idx', XMLDB_INDEX_NOTUNIQUE, ['client_id']);
        $table->add_index('eventtype_idx', XMLDB_INDEX_NOTUNIQUE, ['eventtype']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // Cria a tabela se não existir.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Ponto de salvamento do upgrade.
        upgrade_plugin_savepoint(true, 2026080303, 'local', 'simple_sso');
    }

    return true;
}
