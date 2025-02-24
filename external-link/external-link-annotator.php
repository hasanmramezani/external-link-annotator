<?php
/*
 * Plugin Name: External Link Annotator 
 * Description: Adds numbered markers for external links and creates references section with improved design and features.
 * Plugin URI: https://seokar.click/
 * Author: سجاد اکبری
 * Version: 1.4.0
 * Author URI: http://sajjadakbari.ir/
 * Text Domain: seokar.click
 */

// Load text domain for translations
function ela_load_textdomain() {
    load_plugin_textdomain('seokar.click', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'ela_load_textdomain');

// Function to fetch the title of a webpage with caching
function ela_fetch_page_title($url, $fallback) {
    $cache_key = 'ela_title_' . md5($url);
    $cached_title = get_transient($cache_key);

    if ($cached_title !== false) {
        return $cached_title;
    }

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return $fallback;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code == 403) {
        return $fallback;
    }

    $body = wp_remote_retrieve_body($response);
    preg_match('/<title>(.*?)<\/title>/is', $body, $matches);

    if (!empty($matches[1])) {
        $title = trim($matches[1]);
        set_transient($cache_key, $title, 24 * HOUR_IN_SECONDS); // Cache for 24 hours
        return $title;
    }

    return $fallback;
}

// Add markers to external links and create references section
function ela_add_markers_to_content($content) {
    // Check if the feature is enabled for this post
    if (!is_single() || !get_post_meta(get_the_ID(), '_ela_enabled', true)) {
        return $content;
    }

    global $ela_links;
    $ela_links = array();
    $counter = 1;

    // Find and process external links
    $content = preg_replace_callback('/<a(.*?)href=["\'](.*?)["\'](.*?)>(.*?)<\/a>/i',
        function ($matches) use (&$counter, &$ela_links) {
            $url = esc_url($matches[2]);
            $site_url = site_url();

            // Check if the URL points to a video file hosted on the same site
            $video_extensions = array('mp4', 'webm', 'ogg');
            $parsed_url = parse_url($url);
            $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
            $extension = pathinfo($path, PATHINFO_EXTENSION);

            if (strpos($url, $site_url) === false && filter_var($url, FILTER_VALIDATE_URL) && !in_array($extension, $video_extensions)) {
                // Use the link text as fallback
                $fallback_title = strip_tags($matches[4]);
                // Fetch the title of the external page
                $title = ela_fetch_page_title($url, $fallback_title);

                $ela_links[] = array(
                    'number' => $counter,
                    'url' => $url,
                    'title' => esc_attr($title)
                );

                $marker = '<sup class="ela-marker" data-number="' . $counter . '">' . $counter . '</sup>';
                $counter++;
                // Modified to disable the link and add marker
                return '<span class="ela-link" data-number="' . $counter . '" data-tippy-content="' . esc_attr($title) . '">' . $matches[4] . '</span>' . $marker;
            }
            return $matches[0];
        }, $content);

    // Add references section
    if (!empty($ela_links)) {
        $references = '<div id="ela-references"><h3>' . __('منابع و لینک‌های مرتبط', 'seokar.click') . '</h3><ol>';
        foreach ($ela_links as $link) {
            $references .= '<li id="ref-' . $link['number'] . '"><a href="' . esc_url($link['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link['title']) . '</a></li>';
        }
        $references .= '</ol></div>';
        $content .= $references;
    }
    return $content;
}
add_filter('the_content', 'ela_add_markers_to_content', 20);

// Add settings page to the admin menu
function ela_add_settings_page() {
    add_options_page(
        __('تنظیمات External Link Annotator', 'seokar.click'), // Page title
        __('External Link Annotator', 'seokar.click'), // Menu title
        'manage_options', // Capability
        'ela-settings', // Menu slug
        'ela_render_settings_page' // Callback function
    );
}
add_action('admin_menu', 'ela_add_settings_page');

// Render the settings page
function ela_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('تنظیمات External Link Annotator', 'seokar.click'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ela_settings_group'); // Settings group
            do_settings_sections('ela-settings'); // Settings page slug
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings and fields
function ela_register_settings() {
    register_setting('ela_settings_group', 'ela_settings', 'ela_sanitize_settings');

    add_settings_section(
        'ela_main_section', // Section ID
        __('تنظیمات نمایش گزینه', 'seokar.click'), // Section title
        'ela_section_text', // Callback function
        'ela-settings' // Page slug
    );

    add_settings_field(
        'ela_display_location', // Field ID
        __('محل نمایش گزینه', 'seokar.click'), // Field title
        'ela_display_location_field', // Callback function
        'ela-settings', // Page slug
        'ela_main_section' // Section ID
    );
}
add_action('admin_init', 'ela_register_settings');

// Sanitize settings
function ela_sanitize_settings($input) {
    $input['ela_display_location'] = sanitize_text_field($input['ela_display_location']);
    return $input;
}

// Section text
function ela_section_text() {
    echo '<p>' . __('محل نمایش گزینه فعال‌سازی افزونه را انتخاب کنید.', 'seokar.click') . '</p>';
}

// Display location field
function ela_display_location_field() {
    $options = get_option('ela_settings');
    $location = isset($options['ela_display_location']) ? $options['ela_display_location'] : 'sidebar';
    ?>
    <select name="ela_settings[ela_display_location]">
        <option value="sidebar" <?php selected($location, 'sidebar'); ?>><?php _e('سایدبار', 'seokar.click'); ?></option>
        <option value="bottom" <?php selected($location, 'bottom'); ?>><?php _e('قسمت پایین ویرایشگر', 'seokar.click'); ?></option>
    </select>
    <?php
}

// Add checkbox to the selected location
function ela_add_checkbox_to_editor() {
    $options = get_option('ela_settings');
    $location = isset($options['ela_display_location']) ? $options['ela_display_location'] : 'sidebar';

    if ($location === 'sidebar') {
        // Add meta box to the sidebar
        function ela_add_meta_box() {
            $screens = ['post', 'page', 'product']; // Add support for posts, pages, and products
            foreach ($screens as $screen) {
                add_meta_box(
                    'ela_enable_feature', // Meta box ID
                    __('فعال‌سازی External Link Annotator', 'seokar.click'), // Title
                    'ela_meta_box_callback', // Callback function
                    $screen, // Post type
                    'side', // Context
                    'default' // Priority
                );
            }
        }
        add_action('add_meta_boxes', 'ela_add_meta_box');
    } else {
        // Add checkbox to the bottom of the editor
        function ela_add_checkbox_to_submitbox() {
            global $post;
            if (in_array($post->post_type, ['post', 'page', 'product'])) {
                $ela_enabled = get_post_meta($post->ID, '_ela_enabled', true);
                ?>
                <div class="misc-pub-section ela-checkbox">
                    <label for="ela_enable_feature">
                        <input type="checkbox" id="ela_enable_feature" name="ela_enable_feature" value="1" <?php checked($ela_enabled, 1); ?> />
                        <?php _e('فعال‌سازی External Link Annotator', 'seokar.click'); ?>
                    </label>
                </div>
                <?php
            }
        }
        add_action('post_submitbox_misc_actions', 'ela_add_checkbox_to_submitbox');
    }
}
add_action('admin_init', 'ela_add_checkbox_to_editor');

// Save meta box data
function ela_save_meta_box_data($post_id) {
    // Check if nonce is set (if using a nonce)
    if (!isset($_POST['ela_meta_box_nonce'])) {
        return;
    }

    // Verify nonce (if using a nonce)
    if (!wp_verify_nonce($_POST['ela_meta_box_nonce'], 'ela_save_meta_box_data')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save or delete the checkbox value
    if (isset($_POST['ela_enable_feature'])) {
        update_post_meta($post_id, '_ela_enabled', 1);
    } else {
        delete_post_meta($post_id, '_ela_enabled');
    }
}
add_action('save_post', 'ela_save_meta_box_data');

// Enqueue scripts and styles
function ela_enqueue_scripts() {
    wp_enqueue_style('ela-styles', plugin_dir_url(__FILE__) . 'css/ela-styles.css');
    wp_enqueue_script('popper', 'https://unpkg.com/@popperjs/core@2', array(), null, true);
    wp_enqueue_script('tippy', 'https://unpkg.com/tippy.js@6', array('popper'), null, true);
    wp_enqueue_script('ela-scripts', plugin_dir_url(__FILE__) . 'js/ela-scripts.js', array('tippy'), null, true);
}
add_action('wp_enqueue_scripts', 'ela_enqueue_scripts');

// Enqueue admin styles
function ela_enqueue_admin_styles() {
    wp_enqueue_style('ela-admin-styles', plugin_dir_url(__FILE__) . 'css/ela-admin-styles.css');
}
add_action('admin_enqueue_scripts', 'ela_enqueue_admin_styles');
