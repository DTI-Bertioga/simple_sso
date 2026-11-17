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
 * Database upgrade steps for local_simple_sso.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Database upgrade function for local_simple_sso.
 *
 * @param int $oldversion The old version of the plugin before upgrade.
 * @return bool True on success.
 */
function xmldb_local_simple_sso_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026080303) {
        $table = new xmldb_table('local_simple_sso_logs');

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

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('client_id_idx', XMLDB_INDEX_NOTUNIQUE, ['client_id']);
        $table->add_index('eventtype_idx', XMLDB_INDEX_NOTUNIQUE, ['eventtype']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026080303, 'local', 'simple_sso');
    }

    if ($oldversion < 2026110501) {
        $table = new xmldb_table('local_simple_sso_clients');
        $field = new xmldb_field('allowed_audience', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'todos', 'redirect_uri');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026110501, 'local', 'simple_sso');
    }

    if ($oldversion < 2026110504) {
        $table = new xmldb_table('local_simple_sso_codes');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('client_id', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('redirect_uri', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('code_challenge', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('code_challenge_method', XMLDB_TYPE_CHAR, '10', null, null, null, 'S256');
        $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('code_uniq', XMLDB_KEY_UNIQUE, ['code']);

        $table->add_index('client_id_idx', XMLDB_INDEX_NOTUNIQUE, ['client_id']);
        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('expires_at_idx', XMLDB_INDEX_NOTUNIQUE, ['expires_at']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026110504, 'local', 'simple_sso');
    }

    if ($oldversion < 2026110505) {
        $table = new xmldb_table('local_simple_sso_codes');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
            $table->add_field('client_id', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('redirect_uri', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('code_challenge', XMLDB_TYPE_CHAR, '128', null, null, null, null);
            $table->add_field('code_challenge_method', XMLDB_TYPE_CHAR, '10', null, null, null, 'S256');
            $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('code_uniq', XMLDB_KEY_UNIQUE, ['code']);

            $table->add_index('client_id_idx', XMLDB_INDEX_NOTUNIQUE, ['client_id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('expires_at_idx', XMLDB_INDEX_NOTUNIQUE, ['expires_at']);

            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110505, 'local', 'simple_sso');
    }

    return true;
}
