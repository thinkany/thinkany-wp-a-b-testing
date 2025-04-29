<?php
/**
 * ACF Fields for thinkany WP A/B Testing
 */

class ThinkAny_WP_AB_Testing_ACF_Fields {
    private static $instance = null;
    
    private function __construct() {
        // Register ACF fields
        add_action('acf/init', array($this, 'register_acf_fields'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register ACF fields for A/B testing
     */
    public function register_acf_fields() {
        // Check if ACF is active
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        acf_add_local_field_group(array(
            'key' => 'group_thinkany_ab_testing',
            'title' => 'A/B Testing',
            'fields' => array(
                array(
                    'key' => 'field_ab_testing_enabled',
                    'label' => 'Enable A/B Testing',
                    'name' => 'ab_testing_enabled',
                    'type' => 'true_false',
                    'instructions' => 'Enable A/B testing for this page/post',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'message' => '',
                    'default_value' => 0,
                    'ui' => 1,
                    'ui_on_text' => 'Enabled',
                    'ui_off_text' => 'Disabled',
                ),
                array(
                    'key' => 'field_ab_testing_variant_b',
                    'label' => 'Variant B',
                    'name' => 'ab_testing_variant_b',
                    'type' => 'post_object',
                    'instructions' => 'Select the alternate version (B) of this page/post',
                    'required' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_ab_testing_enabled',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'post_type' => array(
                        0 => 'page',
                        1 => 'post',
                    ),
                    'taxonomy' => '',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'return_format' => 'id',
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_ab_testing_stats',
                    'label' => 'A/B Testing Statistics',
                    'name' => 'ab_testing_stats',
                    'type' => 'message',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_ab_testing_enabled',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'message' => $this->get_ab_testing_stats_message(),
                    'new_lines' => 'wpautop',
                    'esc_html' => 0,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
        ));
    }
    
    /**
     * Generate the statistics message for the ACF field
     */
    private function get_ab_testing_stats_message() {
        global $post;
        
        if (!$post) {
            return __('Statistics will be available after saving.', 'thinkany-wp-a-b-testing');
        }
        
        // Flush stats for this post before displaying
        $ab_testing = ThinkAny_WP_AB_Testing::get_instance();
        $ab_testing->flush_stats_to_database($post->ID);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        // Get stats from database
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post->ID
        ));
        
        if (!$stats) {
            return __('No statistics available yet.', 'thinkany-wp-a-b-testing');
        }
        
        $variant_a_count = isset($stats->views_a) ? intval($stats->views_a) : 0;
        $variant_b_count = isset($stats->views_b) ? intval($stats->views_b) : 0;
        $last_served = $stats->last_served;
        $last_variant = $stats->variant_served;
        
        $message = '<strong>' . __('Views (A):', 'thinkany-wp-a-b-testing') . '</strong> ' . $variant_a_count . '<br>';
        $message .= '<strong>' . __('Views (B):', 'thinkany-wp-a-b-testing') . '</strong> ' . $variant_b_count . '<br>';
        $message .= '<strong>' . __('Last Served:', 'thinkany-wp-a-b-testing') . '</strong> ' . $last_served . ' (Variant ' . $last_variant . ')';
        
        return $message;
    }
}
