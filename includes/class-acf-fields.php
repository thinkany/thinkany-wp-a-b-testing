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
        
        // Basic field group that works for all posts/pages
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
                    'message' => '',
                    'new_lines' => 'wpautop',
                    'esc_html' => 0,
                ),
                array(
                    'key' => 'field_ab_testing_is_variant_b',
                    'label' => 'Variant B Notice',
                    'name' => 'ab_testing_is_variant_b',
                    'type' => 'message',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0, // Always show this field
                    'wrapper' => array(
                        'width' => '',
                        'class' => 'thinkany-variant-b-notice',
                        'id' => '',
                    ),
                    'message' => $this->get_variant_b_message(),
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
        
        // Add JavaScript to handle showing/hiding fields based on whether it's a B variant
        add_action('acf/input/admin_footer', array($this, 'add_variant_b_script'));
    }
    
    /**
     * Get variant B message
     */
    private function get_variant_b_message() {
        global $post;
        
        if (!$post) {
            return '';
        }
        
        $is_variant_b = $this->is_variant_b();
        
        if (!$is_variant_b) {
            return '<div class="hidden"></div>';
        }
        
        $parent_post = $this->get_parent_post();
        
        if (!$parent_post) {
            return '<div class="hidden"></div>';
        }
        
        $message = '<div class="thinkany-variant-b-box" style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 10px;">';
        $message .= '<h3 style="margin-top: 0; color: #2271b1;">' . __('Variant B Page', 'thinkany-wp-a-b-testing') . '</h3>';
        $message .= '<p>' . __('This page is being used as <strong>Variant B</strong> in an A/B test.', 'thinkany-wp-a-b-testing') . '</p>';
        
        $message .= '<p><strong>' . __('Primary Page:', 'thinkany-wp-a-b-testing') . '</strong> ';
        $message .= '<a href="' . get_edit_post_link($parent_post->ID) . '">' . esc_html($parent_post->post_title) . '</a></p>';
        
        // Get A/B testing stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $parent_post->ID
        ));
        
        if ($stats) {
            $message .= '<p><strong>' . __('Current Statistics:', 'thinkany-wp-a-b-testing') . '</strong><br>';
            $message .= __('Views (A):', 'thinkany-wp-a-b-testing') . ' ' . intval($stats->views_a) . '<br>';
            $message .= __('Views (B):', 'thinkany-wp-a-b-testing') . ' ' . intval($stats->views_b) . '</p>';
        }
        
        $message .= '<p class="description">' . __('A/B testing settings can only be managed from the primary page.', 'thinkany-wp-a-b-testing') . '</p>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Add JavaScript to handle variant B pages
     */
    public function add_variant_b_script() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $is_variant_b = $this->is_variant_b();
        
        if ($is_variant_b) {
            // For B variant pages
            $this->add_variant_b_notice_script();
        } else {
            // For A variant pages
            $this->add_variant_a_stats_script();
            
            // Hide the variant B notice
            ?>
            <script type="text/javascript">
            (function($) {
                $(document).ready(function() {
                    $('.thinkany-variant-b-notice').hide();
                });
            })(jQuery);
            </script>
            <?php
        }
    }
    
    /**
     * Add script for B variant notice
     */
    private function add_variant_b_notice_script() {
        global $post;
        
        // Get the parent post
        $parent_post = $this->get_parent_post();
        
        if (!$parent_post) {
            return;
        }
        
        // Get A/B testing stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $parent_post->ID
        ));
        
        $views_a = $stats ? intval($stats->views_a) : 0;
        $views_b = $stats ? intval($stats->views_b) : 0;
        
        // Calculate percentages
        $total_views = $views_a + $views_b;
        $percent_a = $total_views > 0 ? round(($views_a / $total_views) * 100) : 50;
        $percent_b = $total_views > 0 ? round(($views_b / $total_views) * 100) : 50;
        
        $parent_title = esc_js($parent_post->post_title);
        $parent_edit_link = esc_js(get_edit_post_link($parent_post->ID));
        
        // Prepare translated strings without HTML tags
        $variant_b_page = esc_js(__('Variant B Page', 'thinkany-wp-a-b-testing'));
        $this_page_is = esc_js(__('This page is being used as', 'thinkany-wp-a-b-testing'));
        $variant_b = esc_js(__('Variant B', 'thinkany-wp-a-b-testing'));
        $in_ab_test = esc_js(__('in an A/B test.', 'thinkany-wp-a-b-testing'));
        $primary_page = esc_js(__('Primary Page:', 'thinkany-wp-a-b-testing'));
        $current_stats = esc_js(__('Current Statistics:', 'thinkany-wp-a-b-testing'));
        $views_a_text = esc_js(__('Variant A:', 'thinkany-wp-a-b-testing'));
        $views_b_text = esc_js(__('Variant B:', 'thinkany-wp-a-b-testing'));
        $settings_note = esc_js(__('A/B testing settings can only be managed from the primary page.', 'thinkany-wp-a-b-testing'));
        
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Hide all standard A/B testing fields
                $('.acf-field:not(.thinkany-variant-b-notice)').hide();
                
                // Find the variant B notice field
                var $noticeField = $('.thinkany-variant-b-notice');
                
                if ($noticeField.length) {
                    // Create our custom notice HTML with the same structure as A variant
                    var noticeHtml = '<div class="acf-input">' +
                        '<div class="thinkany-stats-box" style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 10px;">' +
                        '<h3 style="margin-top: 0; color: #2271b1;">' + '<?php echo $variant_b_page; ?>' + '</h3>' +
                        '<p>' + '<?php echo $this_page_is; ?>' + ' <strong>' + '<?php echo $variant_b; ?>' + '</strong> ' + '<?php echo $in_ab_test; ?>' + '</p>' +
                        '<p><strong>' + '<?php echo $primary_page; ?>' + '</strong> ' +
                        '<a href="' + '<?php echo $parent_edit_link; ?>' + '">' + '<?php echo $parent_title; ?>' + '</a></p>';
                    
                    <?php if ($stats): ?>
                    noticeHtml += '<p><strong>' + '<?php echo $current_stats; ?>' + '</strong><br>' +
                        '<?php echo $views_a_text; ?>' + ' <strong><?php echo $views_a; ?></strong><br>' +
                        '<?php echo $views_b_text; ?>' + ' <strong><?php echo $views_b; ?></strong></p>';
                        
                    <?php if ($total_views > 0): ?>
                    noticeHtml += '<div style="margin-bottom: 10px;">' +
                        '<div style="background-color: #eee; height: 20px; width: 100%; border-radius: 4px; overflow: hidden;">' +
                        '<div style="background-color: #2271b1; height: 100%; width: <?php echo $percent_a; ?>%; float: left;"></div>' +
                        '<div style="background-color: #d63638; height: 100%; width: <?php echo $percent_b; ?>%; float: left;"></div>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 4px;">' +
                        '<span>A: <?php echo $percent_a; ?>%</span>' +
                        '<span>B: <?php echo $percent_b; ?>%</span>' +
                        '</div>' +
                        '</div>';
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    noticeHtml += '<p class="description">' + '<?php echo $settings_note; ?>' + '</p>' +
                        '</div>' +
                        '</div>';
                    
                    // Replace the content of the notice field, preserving the label
                    $noticeField.find('.acf-label').siblings().remove();
                    $noticeField.append(noticeHtml);
                } else {
                    // Fallback: If the field isn't found, insert at the top of ACF fields
                    var fallbackHtml = '<div class="acf-field">' +
                        '<div class="acf-label"><label>' + '<?php echo $variant_b_page; ?>' + '</label></div>' +
                        '<div class="acf-input">' +
                        '<div class="thinkany-stats-box" style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 10px;">' +
                        // ... same content as above ...
                        '</div>' +
                        '</div>' +
                        '</div>';
                    
                    $('.acf-fields').prepend(fallbackHtml);
                }
            });
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Add script for A variant statistics
     */
    private function add_variant_a_stats_script() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Only proceed if A/B testing is enabled for this post
        $ab_enabled = get_field('ab_testing_enabled', $post->ID);
        if (!$ab_enabled) {
            return;
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
        
        $variant_a_count = $stats ? intval($stats->views_a) : 0;
        $variant_b_count = $stats ? intval($stats->views_b) : 0;
        $last_served = $stats ? esc_js($stats->last_served) : esc_js(__('Never', 'thinkany-wp-a-b-testing'));
        $last_variant = $stats ? esc_js($stats->variant_served) : '';
        
        // Get variant B post
        $variant_b_id = get_field('ab_testing_variant_b', $post->ID);
        $variant_b_post = $variant_b_id ? get_post($variant_b_id) : null;
        $variant_b_title = $variant_b_post ? esc_js($variant_b_post->post_title) : '';
        $variant_b_edit_link = $variant_b_post ? esc_js(get_edit_post_link($variant_b_id)) : '';
        
        // Get plugin settings
        $options = get_option('thinkany_wp_ab_testing_settings');
        $split_ratio = isset($options['split_ratio']) ? intval($options['split_ratio']) : 50;
        $session_persistence = isset($options['session_persistence']) ? (bool)$options['session_persistence'] : false;
        
        // Calculate percentages
        $total_views = $variant_a_count + $variant_b_count;
        $percent_a = $total_views > 0 ? round(($variant_a_count / $total_views) * 100) : 50;
        $percent_b = $total_views > 0 ? round(($variant_b_count / $total_views) * 100) : 50;
        
        // Prepare translated strings
        $stats_title = esc_js(__('A/B Testing Statistics', 'thinkany-wp-a-b-testing'));
        $variant_b_page = esc_js(__('Variant B Page:', 'thinkany-wp-a-b-testing'));
        $views_label = esc_js(__('Views:', 'thinkany-wp-a-b-testing'));
        $variant_a_label = esc_js(__('Variant A:', 'thinkany-wp-a-b-testing'));
        $variant_b_label = esc_js(__('Variant B:', 'thinkany-wp-a-b-testing'));
        $last_served_label = esc_js(__('Last Served:', 'thinkany-wp-a-b-testing'));
        $variant_label = esc_js(__('Variant', 'thinkany-wp-a-b-testing'));
        $config_label = esc_js(__('Configuration:', 'thinkany-wp-a-b-testing'));
        $split_ratio_label = esc_js(__('Split Ratio:', 'thinkany-wp-a-b-testing'));
        $session_persistence_label = esc_js(__('Session Persistence:', 'thinkany-wp-a-b-testing'));
        $enabled_text = esc_js(__('Enabled', 'thinkany-wp-a-b-testing'));
        $disabled_text = esc_js(__('Disabled', 'thinkany-wp-a-b-testing'));
        
        ?>
        <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                // Find the stats field
                var $statsField = $('.acf-field[data-name="ab_testing_stats"]');
                
                if ($statsField.length) {
                    // Create our custom stats HTML
                    var statsHtml = '<div class="acf-input">' +
                        '<div class="thinkany-stats-box" style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 10px;">' +
                        '<h3 style="margin-top: 0; color: #2271b1;">' + '<?php echo $stats_title; ?>' + '</h3>';
                    
                    <?php if ($variant_b_post): ?>
                    statsHtml += '<p><strong>' + '<?php echo $variant_b_page; ?>' + '</strong> ' +
                        '<a href="' + '<?php echo $variant_b_edit_link; ?>' + '">' + '<?php echo $variant_b_title; ?>' + '</a></p>';
                    <?php endif; ?>
                    
                    statsHtml += '<p><strong>' + '<?php echo $views_label; ?>' + '</strong><br>' +
                        '<?php echo $variant_a_label; ?>' + ' <strong><?php echo $variant_a_count; ?></strong><br>' +
                        '<?php echo $variant_b_label; ?>' + ' <strong><?php echo $variant_b_count; ?></strong></p>';
                    
                    <?php if ($total_views > 0): ?>
                    statsHtml += '<div style="margin-bottom: 10px;">' +
                        '<div style="background-color: #eee; height: 20px; width: 100%; border-radius: 4px; overflow: hidden;">' +
                        '<div style="background-color: #2271b1; height: 100%; width: <?php echo $percent_a; ?>%; float: left;"></div>' +
                        '<div style="background-color: #d63638; height: 100%; width: <?php echo $percent_b; ?>%; float: left;"></div>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 4px;">' +
                        '<span>A: <?php echo $percent_a; ?>%</span>' +
                        '<span>B: <?php echo $percent_b; ?>%</span>' +
                        '</div>' +
                        '</div>';
                    <?php endif; ?>
                    
                    statsHtml += '<p><strong>' + '<?php echo $last_served_label; ?>' + '</strong> ' +
                        '<?php echo $last_served; ?>' + (<?php echo !empty($last_variant) ? 'true' : 'false'; ?> ? ' (' + '<?php echo $variant_label; ?>' + ' <?php echo $last_variant; ?>)' : '') + '</p>';
                    
                    statsHtml += '<p><strong>' + '<?php echo $config_label; ?>' + '</strong><br>' +
                        '<?php echo $split_ratio_label; ?>' + ' <?php echo $split_ratio; ?>% / <?php echo (100 - $split_ratio); ?>%<br>' +
                        '<?php echo $session_persistence_label; ?>' + ' ' + (<?php echo $session_persistence ? 'true' : 'false'; ?> ? '<?php echo $enabled_text; ?>' : '<?php echo $disabled_text; ?>') + '</p>';
                    
                    statsHtml += '</div>' +
                        '</div>';
                    
                    // Replace the content of the stats field
                    $statsField.find('.acf-label').siblings().remove();
                    $statsField.append(statsHtml);
                }
            });
        })(jQuery);
        </script>
        <?php
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
        
        // Get variant B post
        $variant_b_id = get_field('ab_testing_variant_b', $post->ID);
        $variant_b_post = $variant_b_id ? get_post($variant_b_id) : null;
        
        // Create a styled stats box
        $message = '<div class="thinkany-stats-box" style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin-bottom: 10px;">';
        $message .= '<h3 style="margin-top: 0; color: #2271b1;">' . __('A/B Testing Statistics', 'thinkany-wp-a-b-testing') . '</h3>';
        
        // Add variant B info if available
        if ($variant_b_post) {
            $message .= '<p><strong>' . __('Variant B Page:', 'thinkany-wp-a-b-testing') . '</strong> ';
            $message .= '<a href="' . get_edit_post_link($variant_b_id) . '">' . esc_html($variant_b_post->post_title) . '</a></p>';
        }
        
        // Add view statistics
        $message .= '<p><strong>' . __('Views:', 'thinkany-wp-a-b-testing') . '</strong><br>';
        $message .= __('Variant A:', 'thinkany-wp-a-b-testing') . ' <strong>' . $variant_a_count . '</strong><br>';
        $message .= __('Variant B:', 'thinkany-wp-a-b-testing') . ' <strong>' . $variant_b_count . '</strong></p>';
        
        // Calculate percentage if there are views
        $total_views = $variant_a_count + $variant_b_count;
        if ($total_views > 0) {
            $percent_a = round(($variant_a_count / $total_views) * 100);
            $percent_b = round(($variant_b_count / $total_views) * 100);
            
            $message .= '<div style="margin-bottom: 10px;">';
            $message .= '<div style="background-color: #eee; height: 20px; width: 100%; border-radius: 4px; overflow: hidden;">';
            $message .= '<div style="background-color: #2271b1; height: 100%; width: ' . $percent_a . '%; float: left;"></div>';
            $message .= '<div style="background-color: #d63638; height: 100%; width: ' . $percent_b . '%; float: left;"></div>';
            $message .= '</div>';
            $message .= '<div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 4px;">';
            $message .= '<span>A: ' . $percent_a . '%</span>';
            $message .= '<span>B: ' . $percent_b . '%</span>';
            $message .= '</div>';
            $message .= '</div>';
        }
        
        // Add last served info
        $message .= '<p><strong>' . __('Last Served:', 'thinkany-wp-a-b-testing') . '</strong> ';
        $message .= $last_served . ' (' . __('Variant', 'thinkany-wp-a-b-testing') . ' ' . $last_variant . ')</p>';
        
        // Get plugin settings
        $options = get_option('thinkany_wp_ab_testing_settings');
        $split_ratio = isset($options['split_ratio']) ? intval($options['split_ratio']) : 50;
        $session_persistence = isset($options['session_persistence']) ? (bool)$options['session_persistence'] : false;
        
        // Add configuration info
        $message .= '<p><strong>' . __('Configuration:', 'thinkany-wp-a-b-testing') . '</strong><br>';
        $message .= __('Split Ratio:', 'thinkany-wp-a-b-testing') . ' ' . $split_ratio . '% / ' . (100 - $split_ratio) . '%<br>';
        $message .= __('Session Persistence:', 'thinkany-wp-a-b-testing') . ' ' . ($session_persistence ? __('Enabled', 'thinkany-wp-a-b-testing') : __('Disabled', 'thinkany-wp-a-b-testing')) . '</p>';
        
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Check if current post is a B variant in an A/B test
     */
    private function is_variant_b() {
        global $post, $wpdb;
        
        if (!$post) {
            return false;
        }
        
        // Query to find if this post is used as a variant B in any A/B test
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'ab_testing_variant_b' 
            AND meta_value = %d",
            $post->ID
        );
        
        $parent_id = $wpdb->get_var($query);
        
        return !empty($parent_id);
    }
    
    /**
     * Get the parent post (variant A) for a B variant
     */
    private function get_parent_post() {
        global $post, $wpdb;
        
        if (!$post) {
            return null;
        }
        
        // Query to find the parent post that uses this post as variant B
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'ab_testing_variant_b' 
            AND meta_value = %d",
            $post->ID
        );
        
        $parent_id = $wpdb->get_var($query);
        
        if (empty($parent_id)) {
            return null;
        }
        
        return get_post($parent_id);
    }
    
    /**
     * Get all post IDs that are used as B variants
     */
    private function get_all_b_variant_ids() {
        global $wpdb;
        
        $query = "SELECT meta_value FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'ab_testing_variant_b'";
        
        $b_variant_ids = $wpdb->get_col($query);
        
        return array_map('intval', $b_variant_ids);
    }
}
