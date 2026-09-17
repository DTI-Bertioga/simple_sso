<?php
namespace local_simple_sso\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for local_simple_sso implementing null_provider.
 *
 * @package     local_simple_sso
 * @copyright   2026
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's language file to explain why no user data is stored.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
