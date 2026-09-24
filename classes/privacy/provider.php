<?php
namespace local_simple_sso\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy Subsystem for local_simple_sso implementing metadata_provider.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {

    /**
     * Returns metadata about the user data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
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

        return $collection;
    }
}
