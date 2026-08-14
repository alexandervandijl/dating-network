<?php
/**
 * Plugin Name: Dating Network
 * Description: Gratis datingplatform voor singles met wederzijdse matching, interne chat en uitlegbare matchscore.
 * Version: 0.6.0
 * Author: Alexander van Dijl
 * Text Domain: dating-network
 */

if (!defined('ABSPATH')) { exit; }

define('DN_VERSION', '0.6.0');
define('DN_FILE', __FILE__);
define('DN_DIR', plugin_dir_path(__FILE__));
define('DN_URL', plugin_dir_url(__FILE__));

require_once DN_DIR . 'includes/class-dn-install.php';
require_once DN_DIR . 'includes/class-dn-safety.php';
require_once DN_DIR . 'includes/class-dn-match.php';
require_once DN_DIR . 'includes/class-dn-core.php';
require_once DN_DIR . 'includes/class-dn-shortcodes.php';
require_once DN_DIR . 'includes/class-dn-photos.php';
require_once DN_DIR . 'includes/class-dn-reputation.php';
require_once DN_DIR . 'includes/class-dn-risk.php';
require_once DN_DIR . 'includes/class-dn-chat-monitor.php';
require_once DN_DIR . 'includes/class-dn-branding.php';
require_once DN_DIR . 'includes/class-dn-growth.php';
require_once DN_DIR . 'includes/class-dn-video.php';
require_once DN_DIR . 'includes/class-dn-video-safety.php';
require_once DN_DIR . 'includes/class-dn-video-evidence.php';
require_once DN_DIR . 'includes/class-dn-admin.php';
require_once DN_DIR . 'includes/class-dn-updater.php';

register_activation_hook(__FILE__, ['DN_Install', 'activate']);

add_action('plugins_loaded', static function (): void {
    DN_Install::maybe_upgrade();
    DN_Updater::init();
    if (is_admin()) { DN_Admin::init(); }
    DN_Reputation::init();
    DN_Core::init();
    DN_Shortcodes::init();
    DN_Photos::init();
    DN_Chat_Monitor::init();
    DN_Branding::init();
    DN_Growth::init();
    DN_Video::init();
    DN_Video_Safety::init();
    DN_Video_Evidence::init();
});
