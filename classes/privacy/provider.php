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
 * Privacy Subsystem for local_simple_sso implementing metadata_provider.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_simple_sso\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy provider implementation for local_simple_sso.
 */
class provider implements metadata_provider {

    /**
     * Returns metadata about the user data stored or exported by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored or transmitted by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        // Audit log database table.
        $collection->add_database_table(
            'local_simple_sso_logs',
            [
                'userid'         => 'privacy:metadata:local_simple_sso_logs:userid',
                'client_id'      => 'privacy:metadata:local_simple_sso_logs:client_id',
                'eventtype'      => 'privacy:metadata:local_simple_sso_logs:eventtype',
                'status'         => 'privacy:metadata:local_simple_sso_logs:status',
                'ip'             => 'privacy:metadata:local_simple_sso_logs:ip',
                'user_agent'     => 'privacy:metadata:local_simple_sso_logs:user_agent',
                'redirect_uri'   => 'privacy:metadata:local_simple_sso_logs:redirect_uri',
                'timecreated'    => 'privacy:metadata:local_simple_sso_logs:timecreated',
            ],
            'privacy:metadata:local_simple_sso_logs'
        );

        // Declare personal user data transmitted to external client applications via JWT / UserInfo.
        $collection->add_external_location(
            'client_applications',
            [
                'sub'       => 'privacy:metadata:external:sub',
                'email'     => 'privacy:metadata:external:email',
                'firstname' => 'privacy:metadata:external:firstname',
                'lastname'  => 'privacy:metadata:external:lastname',
                'groups'    => 'privacy:metadata:external:groups',
            ],
            'privacy:metadata:external'
        );

        return $collection;
    }
}
