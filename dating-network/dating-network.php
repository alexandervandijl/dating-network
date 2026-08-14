<?php
/**
 * Plugin Name: Dating Network
 * Description: Veilig datingplatform voor singles met wederzijdse matching, interne chat en uitlegbare matchscore.
 * Version: 0.5.0
 * Author: Alexander van Dijl
 * Text Domain: dating-network
 */

if (!defined('ABSPATH')) { exit; }

define('DN_VERSION', '0.5.0');
define('DN_FILE', __FILE__);
define('DN_DIR', plugin_dir_path(__FILE__));
define('DN_URL', plugin_dir_url(__FILE__));

require_once DN_DIR . 'includes/class-dn-install.php';
require_once DN_DIR . 'includes/class-dn-safety.php';
require_once DN_DIR . 'includes/class-dn-match.php';
require_once DN_DIR . 'includes/class-dn-core.php';
require_once DN_DIR . 'includes/class-dn-shortcodes.php';
require_once DN_DIR . 'includes/class-dn-admin.php';
require_once DN_DIR . 'includes/class-dn-updater.php';

register_activation_hook(__FILE__, ['DN_Install', 'activate']);

add_action('plugins_loaded', static function (): void {
    DN_Install::maybe_upgrade();
    DN_Updater::init();
    DN_Core::init();
    DN_Shortcodes::init();
    if (is_admin()) { DN_Admin::init(); }
});
