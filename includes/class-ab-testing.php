<?php
/**
 * A/B Testing functionality for thinkany
 */

class ThinkAny_WP_AB_Testing {
    private static $instance = null;
    
    private function __construct() {
        // Hook into template_redirect to potentially redirect to variant B
        add_action('template_redirect', array($this, 'maybe_serve_variant_b'));
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if we should serve variant B and redirect if needed
     */
    public function maybe_serve_variant_b() {
        // Only run on singular pages (posts, pages)
        if (!is_singular()) {
            return;
        }
        
        // Get current post
        global $post;
        
        // Check if A/B testing is enabled for this post
        $ab_enabled = get_field('ab_testing_enabled', $post->ID);
        
        if (!$ab_enabled) {
            return;
        }
        
        // Get plugin settings
        $options = get_option('thinkany_wp_ab_testing_settings');
        $plugin_enabled = isset($options['enabled']) ? (bool)$options['enabled'] : true;
        
        if (!$plugin_enabled) {
            return;
        }
        
        // Get variant B post ID
        $variant_b_id = get_field('ab_testing_variant_b', $post->ID);
        
        if (!$variant_b_id) {
            return;
        }
        
        // Determine which variant to serve
        $variant = $this->determine_variant($post->ID);
        
        // Update stats
        $this->update_stats($post->ID, $variant);
        
        // If variant B, redirect to it
        if ($variant === 'B') {
            $variant_b_url = get_permalink($variant_b_id);
            
            // Add a query parameter to prevent infinite redirects
            $variant_b_url = add_query_arg('ab_variant', 'B', $variant_b_url);
            
            wp_redirect($variant_b_url);
            exit;
        }
    }
    
    /**
     * Determine which variant to serve
     */
    private function determine_variant($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        // Get the plugin settings
        $options = get_option('thinkany_wp_ab_testing_settings');
        $split_ratio = isset($options['split_ratio']) ? intval($options['split_ratio']) : 50;
        $session_persistence = isset($options['session_persistence']) ? (bool)$options['session_persistence'] : false;
        $cookie_duration = isset($options['cookie_duration']) ? intval($options['cookie_duration']) : 7;
        
        // Round to nearest 10
        $split_ratio = round($split_ratio / 10) * 10;
        
        // Check if session persistence is enabled
        if ($session_persistence) {
            // Cookie name unique to this post
            $cookie_name = 'thinkany_ab_variant_' . $post_id;
            
            // Check if the cookie exists
            if (isset($_COOKIE[$cookie_name])) {
                // Return the variant stored in the cookie
                return $_COOKIE[$cookie_name];
            }
            
            // No cookie found, determine which variant to serve
            $variant = $this->get_variant_by_ratio($split_ratio);
            
            // Set the cookie to persist the variant for this session
            // Use httponly for security, but not secure to work on both http and https
            setcookie(
                $cookie_name,
                $variant,
                time() + (DAY_IN_SECONDS * $cookie_duration),
                COOKIEPATH,
                COOKIE_DOMAIN,
                false,
                true
            );
            
            return $variant;
        }
        
        // If session persistence is disabled, use the split ratio directly
        return $this->get_variant_by_ratio($split_ratio);
    }
    
    /**
     * Get a variant based on the split ratio
     */
    private function get_variant_by_ratio($split_ratio) {
        // Use split ratio to determine which variant to serve
        $random = mt_rand(1, 100);
        if ($random <= $split_ratio) {
            return 'A';
        } else {
            return 'B';
        }
    }
    
    /**
     * Update the stats for the served variant
     */
    private function update_stats($post_id, $variant) {
        // Get the current transient data
        $transient_key = 'thinkany_ab_testing_views_' . $post_id;
        $cached_views = get_transient($transient_key);
        
        if (!$cached_views) {
            $cached_views = array(
                'views_a' => 0,
                'views_b' => 0,
                'last_variant' => $variant,
                'count' => 0
            );
        }
        
        // Update the cached data
        if ($variant === 'A') {
            $cached_views['views_a']++;
        } else {
            $cached_views['views_b']++;
        }
        
        $cached_views['last_variant'] = $variant;
        $cached_views['count']++;
        
        // Save the updated cache
        set_transient($transient_key, $cached_views, DAY_IN_SECONDS);
        
        // If we've hit 100 views or more, write to the database
        if ($cached_views['count'] >= 100) {
            $this->flush_single_post_stats($post_id);
        }
    }
    
    /**
     * Flush cached stats to the database
     */
    public function flush_stats_to_database($post_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        
        // If post_id is provided, only flush that specific post's stats
        if ($post_id) {
            $this->flush_single_post_stats($post_id);
            return;
        }
        
        // Otherwise, get all transients for A/B testing
        // Note: WordPress stores transients in the options table with a '_transient_' prefix
        $transient_prefix = '_transient_thinkany_ab_testing_views_';
        
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value FROM $wpdb->options 
            WHERE option_name LIKE '{$transient_prefix}%'"
        );
        
        foreach ($transients as $transient) {
            // Extract post ID from transient name
            $transient_name = str_replace($transient_prefix, '', $transient->option_name);
            $post_id = intval($transient_name);
            
            if ($post_id > 0) {
                $this->flush_single_post_stats($post_id);
            }
        }
    }
    
    /**
     * Flush stats for a single post to the database
     */
    private function flush_single_post_stats($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'thinkany_ab_testing';
        $transient_key = 'thinkany_ab_testing_views_' . $post_id;
        
        // Get the cached data
        $cached_views = get_transient($transient_key);
        
        if (!$cached_views || empty($cached_views['count'])) {
            return; // No data to flush
        }
        
        // Get current database record
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post_id
        ));
        
        $now = current_time('mysql');
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table_name,
                array(
                    'variant_served' => $cached_views['last_variant'],
                    'views_a' => $existing->views_a + $cached_views['views_a'],
                    'views_b' => $existing->views_b + $cached_views['views_b'],
                    'last_served' => $now
                ),
                array('post_id' => $post_id),
                array('%s', '%d', '%d', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'variant_served' => $cached_views['last_variant'],
                    'views_a' => $cached_views['views_a'],
                    'views_b' => $cached_views['views_b'],
                    'last_served' => $now
                ),
                array('%d', '%s', '%d', '%d', '%s')
            );
        }
        
        // Clear the transient
        delete_transient($transient_key);
    }
    
    /**
     * Add dashboard widget for A/B testing stats
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'thinkany_ab_testing_dashboard_widget',
            __('A/B Testing Statistics', 'thinkany-wp-a-b-testing'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render the dashboard widget
     */
    public function render_dashboard_widget() {
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
        echo '<th>' . __('Views (A)', 'thinkany-wp-a-b-testing') . '</th>';
        echo '<th>' . __('Views (B)', 'thinkany-wp-a-b-testing') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($ab_posts as $post_id) {
            $post = get_post($post_id);
            
            // Get stats from database
            $stats_a = $wpdb->get_var($wpdb->prepare(
                "SELECT views_a FROM $table_name WHERE post_id = %d",
                $post_id
            ));
            
            $stats_b = $wpdb->get_var($wpdb->prepare(
                "SELECT views_b FROM $table_name WHERE post_id = %d",
                $post_id
            ));
            
            $views_a = $stats_a ? intval($stats_a) : 0;
            $views_b = $stats_b ? intval($stats_b) : 0;
            
            echo '<tr>';
            echo '<td><a href="' . get_edit_post_link($post_id) . '">' . esc_html($post->post_title) . '</a></td>';
            echo '<td>' . esc_html($views_a) . '</td>';
            echo '<td>' . esc_html($views_b) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<p><a href="' . admin_url('options-general.php?page=thinkany-wp-a-b-testing') . '">' . __('View detailed statistics', 'thinkany-wp-a-b-testing') . '</a></p>';
    }
    
    /**
     * Get all posts with A/B testing enabled
     */
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
}
