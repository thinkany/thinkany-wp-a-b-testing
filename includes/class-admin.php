<?php
/**
 * Admin settings for thinkany WP A/B Testing
 */

class ThinkAny_WP_AB_Testing_Admin {
    private static $instance = null;
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link on the plugins page
        add_filter('plugin_action_links_thinkany-wp-a-b-testing/thinkany-wp-a-b-testing.php', array($this, 'add_settings_link'));
        
        // Add A/B testing columns to post/page list
        add_filter('manage_pages_columns', array($this, 'add_ab_testing_column'));
        add_filter('manage_posts_columns', array($this, 'add_ab_testing_column'));
        
        // Populate A/B testing column
        add_action('manage_pages_custom_column', array($this, 'populate_ab_testing_column'), 10, 2);
        add_action('manage_posts_custom_column', array($this, 'populate_ab_testing_column'), 10, 2);
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('thinkany WP A/B Testing Settings', 'thinkany-wp-a-b-testing'),
            __('thinkany A/B Testing', 'thinkany-wp-a-b-testing'),
            'manage_options',
            'thinkany-wp-a-b-testing',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting(
            'thinkany_wp_ab_testing_settings_group',
            'thinkany_wp_ab_testing_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'thinkany_wp_ab_testing_main_section',
            null,
            array($this, 'render_section_info'),
            'thinkany-wp-a-b-testing'
        );
        
        add_settings_field(
            'enabled',
            __('Enable A/B Testing', 'thinkany-wp-a-b-testing'),
            array($this, 'render_enabled_field'),
            'thinkany-wp-a-b-testing',
            'thinkany_wp_ab_testing_main_section'
        );
        
        add_settings_field(
            'split_ratio',
            __('Split Ratio (A/B)', 'thinkany-wp-a-b-testing'),
            array($this, 'render_split_ratio_field'),
            'thinkany-wp-a-b-testing',
            'thinkany_wp_ab_testing_main_section'
        );
        
        // Add session persistence option
        add_settings_field(
            'session_persistence',
            __('Session Persistence', 'thinkany-wp-a-b-testing'),
            array($this, 'render_session_persistence_field'),
            'thinkany-wp-a-b-testing',
            'thinkany_wp_ab_testing_main_section'
        );
        
        // Add cookie duration option
        add_settings_field(
            'cookie_duration',
            __('Cookie Duration (days)', 'thinkany-wp-a-b-testing'),
            array($this, 'render_cookie_duration_field'),
            'thinkany-wp-a-b-testing',
            'thinkany_wp_ab_testing_main_section'
        );
        
        // Add data deletion option
        add_settings_field(
            'delete_data',
            __('Data Management', 'thinkany-wp-a-b-testing'),
            array($this, 'render_delete_data_field'),
            'thinkany-wp-a-b-testing',
            'thinkany_wp_ab_testing_main_section'
        );
    }
    
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Sanitize Enabled
        $sanitized_input['enabled'] = isset($input['enabled']) ? (bool)$input['enabled'] : false;
        
        // Sanitize Split Ratio
        $split_ratio = isset($input['split_ratio']) ? intval($input['split_ratio']) : 50;
        $sanitized_input['split_ratio'] = max(10, min(90, $split_ratio)); // Ensure between 10 and 90
        
        // Sanitize Session Persistence
        $sanitized_input['session_persistence'] = isset($input['session_persistence']) ? (bool)$input['session_persistence'] : false;
        
        // Sanitize Cookie Duration (in days)
        $cookie_duration = isset($input['cookie_duration']) ? intval($input['cookie_duration']) : 7;
        $sanitized_input['cookie_duration'] = max(1, min(365, $cookie_duration)); // Ensure between 1 and 365 days
        
        // Sanitize Delete Data option
        $sanitized_input['delete_data'] = isset($input['delete_data']) ? (bool)$input['delete_data'] : false;
        
        return $sanitized_input;
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=thinkany-wp-a-b-testing">' . __('Settings', 'thinkany-wp-a-b-testing') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function render_section_info() {
        echo '<p>' . __('Configure the A/B testing settings below.', 'thinkany-wp-a-b-testing') . '</p>';
    }
    
    public function render_enabled_field() {
        $options = get_option('thinkany_wp_ab_testing_settings');
        $enabled = isset($options['enabled']) ? $options['enabled'] : true;
        
        echo '<input type="checkbox" id="enabled" name="thinkany_wp_ab_testing_settings[enabled]" ' . checked($enabled, true, false) . ' />';
        echo '<label for="enabled">' . __('Enable A/B testing functionality', 'thinkany-wp-a-b-testing') . '</label>';
    }
    
    public function render_split_ratio_field() {
        $options = get_option('thinkany_wp_ab_testing_settings');
        $split_ratio = isset($options['split_ratio']) ? $options['split_ratio'] : 50;
        
        // Round to nearest 10
        $split_ratio = round($split_ratio / 10) * 10;
        
        echo '<div style="display: flex; align-items: center;">';
        echo '<input type="range" id="split_ratio" name="thinkany_wp_ab_testing_settings[split_ratio]" min="10" max="90" step="10" value="' . esc_attr($split_ratio) . '" style="width: 200px;" />';
        echo '<span id="split_ratio_display" style="margin-left: 10px;">' . esc_html($split_ratio) . '% / ' . (100 - $split_ratio) . '%</span>';
        echo '</div>';
        echo '<p class="description">' . __('Adjust the split ratio between variant A and variant B.', 'thinkany-wp-a-b-testing') . '</p>';
        
        echo '<script>
            jQuery(document).ready(function($) {
                $("#split_ratio").on("input", function() {
                    var value = $(this).val();
                    $("#split_ratio_display").text(value + "% / " + (100 - value) + "%");
                });
            });
        </script>';
    }
    
    public function render_session_persistence_field() {
        $options = get_option('thinkany_wp_ab_testing_settings');
        $session_persistence = isset($options['session_persistence']) ? $options['session_persistence'] : false;
        
        echo '<input type="checkbox" id="session_persistence" name="thinkany_wp_ab_testing_settings[session_persistence]" ' . checked($session_persistence, true, false) . ' />';
        echo '<label for="session_persistence">' . __('Enable session persistence for A/B testing', 'thinkany-wp-a-b-testing') . '</label>';
    }
    
    public function render_cookie_duration_field() {
        $options = get_option('thinkany_wp_ab_testing_settings');
        $cookie_duration = isset($options['cookie_duration']) ? $options['cookie_duration'] : 7;
        
        echo '<input type="number" id="cookie_duration" name="thinkany_wp_ab_testing_settings[cookie_duration]" value="' . esc_attr($cookie_duration) . '" min="1" max="365" />';
        echo '<p class="description">' . __('Set the duration of the A/B testing cookie in days.', 'thinkany-wp-a-b-testing') . '</p>';
    }
    
    public function render_delete_data_field() {
        $options = get_option('thinkany_wp_ab_testing_settings');
        $delete_data = isset($options['delete_data']) ? $options['delete_data'] : false;
        
        echo '<input type="checkbox" id="delete_data" name="thinkany_wp_ab_testing_settings[delete_data]" ' . checked($delete_data, true, false) . ' />';
        echo '<label for="delete_data">' . __('Delete all A/B testing data when plugin is uninstalled', 'thinkany-wp-a-b-testing') . '</label>';
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Flush all cached stats to the database before displaying
        $ab_testing = ThinkAny_WP_AB_Testing::get_instance();
        $ab_testing->flush_stats_to_database();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('thinkany_wp_ab_testing_settings_group');
                do_settings_sections('thinkany-wp-a-b-testing');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php _e('A/B Testing Statistics', 'thinkany-wp-a-b-testing'); ?></h2>
            
            <?php $this->render_ab_testing_stats(); ?>
            
        </div>
        <?php
    }
    
    public function render_ab_testing_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        // Get all posts with A/B testing enabled
        $ab_posts = $this->get_ab_testing_posts();
        
        if (empty($ab_posts)) {
            echo '<p>' . __('No pages or posts currently have A/B testing enabled.', 'thinkany-wp-a-b-testing') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Page/Post', 'thinkany-wp-a-b-testing') . '</th>';
        echo '<th>' . __('Variant B', 'thinkany-wp-a-b-testing') . '</th>';
        echo '<th>' . __('Views (A)', 'thinkany-wp-a-b-testing') . '</th>';
        echo '<th>' . __('Views (B)', 'thinkany-wp-a-b-testing') . '</th>';
        echo '<th>' . __('Last Served', 'thinkany-wp-a-b-testing') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($ab_posts as $post_id) {
            $post = get_post($post_id);
            $variant_b_id = get_field('ab_testing_variant_b', $post_id);
            $variant_b = get_post($variant_b_id);
            
            // Get stats from database
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE post_id = %d",
                $post_id
            ));
            
            $views_a = $stats ? intval($stats->views_a) : 0;
            $views_b = $stats ? intval($stats->views_b) : 0;
            $last_served = $stats ? $stats->last_served : __('Never', 'thinkany-wp-a-b-testing');
            
            echo '<tr>';
            echo '<td><a href="' . get_edit_post_link($post_id) . '">' . esc_html($post->post_title) . '</a></td>';
            echo '<td>' . ($variant_b ? '<a href="' . get_edit_post_link($variant_b_id) . '">' . esc_html($variant_b->post_title) . '</a>' : __('None', 'thinkany-wp-a-b-testing')) . '</td>';
            echo '<td>' . esc_html($views_a) . '</td>';
            echo '<td>' . esc_html($views_b) . '</td>';
            echo '<td>' . esc_html($last_served) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    private function get_ab_testing_posts() {
        $args = array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'ab_testing_enabled',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        $post_ids = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        return $post_ids;
    }
    
    public function add_ab_testing_column($columns) {
        $columns['ab_testing'] = __('A/B Testing', 'thinkany-wp-a-b-testing');
        return $columns;
    }
    
    public function populate_ab_testing_column($column, $post_id) {
        if ($column !== 'ab_testing') {
            return;
        }
        
        $ab_enabled = get_field('ab_testing_enabled', $post_id);
        
        if ($ab_enabled) {
            $variant_b_id = get_field('ab_testing_variant_b', $post_id);
            $variant_b = get_post($variant_b_id);
            
            echo '<span style="color: green;"><span class="dashicons dashicons-yes"></span> ' . __('Enabled', 'thinkany-wp-a-b-testing') . '</span>';
            
            if ($variant_b) {
                echo '<br><small>' . __('Variant B:', 'thinkany-wp-a-b-testing') . ' <a href="' . get_edit_post_link($variant_b_id) . '">' . esc_html($variant_b->post_title) . '</a></small>';
            } else {
                echo '<br><small>' . __('Variant B not set', 'thinkany-wp-a-b-testing') . '</small>';
            }
        } else {
            echo '<span style="color: #999;"><span class="dashicons dashicons-no-alt"></span> ' . __('Disabled', 'thinkany-wp-a-b-testing') . '</span>';
        }
    }
}
