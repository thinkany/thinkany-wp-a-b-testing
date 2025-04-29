<?php
/**
 * Plugin Name: thinkany WP A/B Testing
 * Plugin URI: https://thinkany.co/plugins/thinkany-wp-a-b-testing/
 * Description: Simple A/B testing for WordPress pages and posts with ACF integration.
 * Version: 1.0.0
 * Author: thinkany LLC
 * Author URI: https://thinkany.co
 * Text Domain: thinkany-wp-a-b-testing
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('THINKANY_WP_AB_TESTING_VERSION', '1.0.0');
define('THINKANY_WP_AB_TESTING_PATH', plugin_dir_path(__FILE__));
define('THINKANY_WP_AB_TESTING_URL', plugin_dir_url(__FILE__));

// Include required files
require_once THINKANY_WP_AB_TESTING_PATH . 'includes/class-admin.php';
require_once THINKANY_WP_AB_TESTING_PATH . 'includes/class-ab-testing.php';
require_once THINKANY_WP_AB_TESTING_PATH . 'includes/class-acf-fields.php';

/**
 * Load plugin text domain for translations
 */
function thinkany_wp_ab_testing_load_textdomain() {
    load_plugin_textdomain(
        'thinkany-wp-a-b-testing',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'thinkany_wp_ab_testing_load_textdomain');

/**
 * Enqueue admin scripts and styles
 */
function thinkany_wp_ab_testing_admin_enqueue_scripts() {
    wp_enqueue_style(
        'thinkany-wp-a-b-testing-admin',
        THINKANY_WP_AB_TESTING_URL . 'assets/css/admin.css',
        array(),
        THINKANY_WP_AB_TESTING_VERSION
    );
    
    wp_enqueue_script(
        'thinkany-wp-a-b-testing-admin',
        THINKANY_WP_AB_TESTING_URL . 'assets/js/admin.js',
        array('jquery'),
        THINKANY_WP_AB_TESTING_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'thinkany_wp_ab_testing_admin_enqueue_scripts');

/**
 * Initialize the plugin
 */
function thinkany_wp_ab_testing_init() {
    // Initialize admin settings
    ThinkAny_WP_AB_Testing_Admin::get_instance();
    
    // Initialize ACF fields
    ThinkAny_WP_AB_Testing_ACF_Fields::get_instance();
    
    // Initialize A/B testing functionality
    ThinkAny_WP_AB_Testing::get_instance();
}
add_action('plugins_loaded', 'thinkany_wp_ab_testing_init');

/**
 * Check if ACF is active and display admin notice if not
 */
function thinkany_wp_ab_testing_check_dependencies() {
    // Check if ACF is active
    if (!class_exists('ACF') && current_user_can('activate_plugins')) {
        add_action('admin_notices', 'thinkany_wp_ab_testing_acf_notice');
    }
}
add_action('admin_init', 'thinkany_wp_ab_testing_check_dependencies');

/**
 * Display admin notice about ACF requirement
 */
function thinkany_wp_ab_testing_acf_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php printf(
            /* translators: %s: URL to the plugin installation page */
            esc_html__('The thinkany WP A/B Testing plugin requires Advanced Custom Fields (ACF) to be installed and activated. Please <a href="%s">install and activate ACF</a> first.', 'thinkany-wp-a-b-testing'),
            esc_url(admin_url('plugin-install.php?tab=search&s=advanced-custom-fields'))
        ); ?></p>
    </div>
    <?php
}

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, 'thinkany_wp_ab_testing_activate');
function thinkany_wp_ab_testing_activate() {
    // Check if ACF is active first
    if (!class_exists('ACF')) {
        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Add admin notice about ACF requirement
        add_option('thinkany_wp_ab_testing_acf_notice', true);
        
        // Display error message
        wp_die(
            sprintf(
                /* translators: %s: URL to the plugin installation page */
                esc_html__('The thinkany WP A/B Testing plugin requires Advanced Custom Fields (ACF) to be installed and activated. Please <a href="%s">install and activate ACF</a> first.', 'thinkany-wp-a-b-testing'),
                esc_url(admin_url('plugin-install.php?tab=search&s=advanced-custom-fields'))
            ),
            esc_html__('Plugin Activation Error', 'thinkany-wp-a-b-testing'),
            array('back_link' => true)
        );
        
        return;
    }
    
    // Create database table for tracking A/B test views
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_ab_testing';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        variant_served varchar(1) NOT NULL,
        views_a bigint(20) NOT NULL DEFAULT 0,
        views_b bigint(20) NOT NULL DEFAULT 0,
        last_served datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add default options
    if (!get_option('thinkany_wp_ab_testing_settings')) {
        add_option('thinkany_wp_ab_testing_settings', array(
            'enabled' => true,
            'split_ratio' => 50 // 50/50 split by default
        ));
    }
}

/**
 * Register deactivation hook
 */
register_deactivation_hook(__FILE__, 'thinkany_wp_ab_testing_deactivate');
function thinkany_wp_ab_testing_deactivate() {
    // Check if we should delete all data
    $options = get_option('thinkany_wp_ab_testing_settings');
    $delete_data = isset($options['delete_data']) && $options['delete_data'];
    
    if ($delete_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        // Drop the table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Delete options
        delete_option('thinkany_wp_ab_testing_settings');
        
        // Remove ACF field group
        if (function_exists('acf_remove_local_field_group')) {
            acf_remove_local_field_group('group_thinkany_ab_testing');
        }
    }
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook('thinkany_wp_ab_testing_daily_stats');
}

/**
 * Register uninstall hook
 */
register_uninstall_hook(__FILE__, 'thinkany_wp_ab_testing_uninstall');
function thinkany_wp_ab_testing_uninstall() {
    // Remove all plugin options
    delete_option('thinkany_wp_ab_testing_settings');
    delete_option('thinkany_wp_ab_testing_acf_notice');
    
    // Drop the A/B testing tracking table
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_ab_testing';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Add admin notice if ACF is not active
 */
function thinkany_wp_ab_testing_admin_notice() {
    if (get_option('thinkany_wp_ab_testing_acf_notice')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php esc_html_e('ThinkAny WP A/B Testing requires Advanced Custom Fields (ACF) to be installed and activated.', 'thinkany-wp-a-b-testing'); ?></p>
        </div>
        <?php
        // Delete the option so the notice is only shown once
        delete_option('thinkany_wp_ab_testing_acf_notice');
    }
}
add_action('admin_notices', 'thinkany_wp_ab_testing_admin_notice');

/**
 * Register shutdown hook to flush stats
 */
function thinkany_wp_ab_testing_shutdown() {
    // We don't need to flush on every page load
    // The stats will be flushed:
    // 1. When the view count reaches 100
    // 2. When viewing the admin settings page
    // 3. When the plugin is deactivated
    
    // Uncomment the following if you want to flush on shutdown
    /*
    // Only flush stats on shutdown if we're not in admin
    if (!is_admin()) {
        $ab_testing = ThinkAny_WP_AB_Testing::get_instance();
        $ab_testing->flush_stats_to_database();
    }
    */
}
add_action('shutdown', 'thinkany_wp_ab_testing_shutdown');
