<?php
/**
 * Plugin Name: Agency OS AI
 * Plugin URI:  https://codefreex.com/agency-os-ai
 * Description: AI-powered WordPress project manager with client portal, tickets, tasks, reports, and OpenAI workspace tools.
 * Version:     1.4.0
 * Requires at least: 6.4
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * Author:      Codefreex
 * Author URI:  https://codefreex.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: agency-os-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AOSAI_VERSION', '1.4.0' );
define( 'AOSAI_PLUGIN_FILE', __FILE__ );
define( 'AOSAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AOSAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AOSAI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AOSAI_MIN_WP_VERSION', '6.4' );
define( 'AOSAI_MIN_PHP_VERSION', '8.0' );

function aosai_check_requirements() {
    $errors = array();
    
    if ( version_compare( PHP_VERSION, AOSAI_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            esc_html__( 'Agency OS AI requires PHP %1$s or higher. You are running PHP %2$s.', 'agency-os-ai' ),
            AOSAI_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }
    
    if ( version_compare( get_bloginfo( 'version' ), AOSAI_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            esc_html__( 'Agency OS AI requires WordPress %1$s or higher. You are running WordPress %2$s.', 'agency-os-ai' ),
            AOSAI_MIN_WP_VERSION,
            get_bloginfo( 'version' )
        );
    }
    
    return $errors;
}

function aosai_requirements_notice() {
    $errors = aosai_check_requirements();
    if ( empty( $errors ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__( 'Agency OS AI', 'agency-os-ai' ) . '</strong><br>';
    echo esc_html( implode( '<br>', $errors ) );
    echo '</p></div>';
}

function aosai_init() {
    $errors = aosai_check_requirements();
    if ( ! empty( $errors ) ) {
        add_action( 'admin_notices', 'aosai_requirements_notice' );
        return;
    }
    
    require_once AOSAI_PLUGIN_DIR . 'includes/helpers/aosai-constants.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/helpers/aosai-functions.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/traits/trait-aosai-singleton.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/traits/trait-aosai-rest-validation.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/class-aosai-loader.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/class-aosai-i18n.php';
    require_once AOSAI_PLUGIN_DIR . 'includes/class-aosai-plugin.php';

    AOSAI_Activator::maybe_upgrade();
    
    $plugin = AOSAI_Plugin::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'aosai_init' );

register_activation_hook( __FILE__, array( 'AOSAI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AOSAI_Deactivator', 'deactivate' ) );

require_once AOSAI_PLUGIN_DIR . 'includes/class-aosai-activator.php';
require_once AOSAI_PLUGIN_DIR . 'includes/class-aosai-deactivator.php';
