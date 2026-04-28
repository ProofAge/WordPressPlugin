<?php

/**
 * Plugin Name: ProofAge Age Verification
 * Plugin URI: https://proofage.xyz/
 * Description: Adds ProofAge-powered age verification to WordPress and WooCommerce storefronts.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: ProofAge
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: proofage-age-verification
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PROOFAGE_WP_PLUGIN_FILE', __FILE__);
define('PROOFAGE_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PROOFAGE_WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROOFAGE_WP_PLUGIN_VERSION', '0.1.0');

require_once PROOFAGE_WP_PLUGIN_DIR . 'src/Autoloader.php';

\ProofAge\WordPress\Autoloader::register();

register_activation_hook(__FILE__, ['ProofAge\\WordPress\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['ProofAge\\WordPress\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new \ProofAge\WordPress\Plugin())->boot();
});
