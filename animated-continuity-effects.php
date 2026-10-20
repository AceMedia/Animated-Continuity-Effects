<?php
/**
 * Plugin Name: Animated Continuity Effects
 * Description: Configurable view transition effects for WordPress, including shared element transitions and exclusions.
 * Version: 0.1.0
 * Author: Shane Rounce
 * Author URI: https://github.com/AceMedia
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: animated-continuity-effects
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ACE_PLUGIN_FILE', __FILE__);
define('ACE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACE_OPTION_NAME', 'ace_settings');

require_once ACE_PLUGIN_DIR . 'includes/class-ace-plugin.php';

register_activation_hook(__FILE__, array('ACE_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('ACE_Plugin', 'deactivate'));

add_action('plugins_loaded', function () {
    ACE_Plugin::instance();
});
