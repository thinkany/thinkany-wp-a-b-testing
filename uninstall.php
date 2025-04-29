<?php
/**
 * Uninstall ThinkAny WP A/B Testing
 *
 * @package ThinkAny_WP_AB_Testing
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if we should delete all data
$options = get_option('thinkany_wp_ab_testing_settings');
$delete_data = isset($options['delete_data']) && $options['delete_data'];

// Always delete data on complete uninstall
if ($delete_data || true) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'thinkany_ab_testing';
    
    // Drop the table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Delete options
    delete_option('thinkany_wp_ab_testing_settings');
    
    // Delete any transients
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_thinkany_wp_ab_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_thinkany_wp_ab_%'");
    
    // Remove ACF field values from post meta
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE 'ab_testing_%'");
}
