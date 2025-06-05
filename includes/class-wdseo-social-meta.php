<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Social_Meta {

    public static function init() {
        add_action('wp_head', array(__CLASS__, 'add_social_meta_tags'), 5);
    }

    public static function add_social_meta_tags() {
        if (!is_singular()) {
            return;
        }

        global $post;

        // Get data
        $title = self::get_title($post);
        $description = self::get_description($post);
        $image = self::get_image($post);
        $url = get_permalink();
        $site_name = get_bloginfo('name');

        // Output Open Graph tags
        echo "<meta property=\"og:title\" content=\"" . esc_attr($title) . "\" />\n";
        echo "<meta property=\"og:description\" content=\"" . esc_attr($description) . "\" />\n";
        echo "<meta property=\"og:type\" content=\"article\" />\n";
        echo "<meta property=\"og:url\" content=\"" . esc_url($url) . "\" />\n";
        echo "<meta property=\"og:site_name\" content=\"" . esc_attr($site_name) . "\" />\n";

        if ($image) {
            echo "<meta property=\"og:image\" content=\"" . esc_url($image) . "\" />\n";
        }

        // Output Twitter Card tags
        echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
        echo "<meta name=\"twitter:title\" content=\"" . esc_attr($title) . "\" />\n";
        echo "<meta name=\"twitter:description\" content=\"" . esc_attr($description) . "\" />\n";
        echo "<meta name=\"twitter:site\" content=\"@WildDragonOfficial\" />\n"; // Change or filter later
        if ($image) {
            echo "<meta name=\"twitter:image\" content=\"" . esc_url($image) . "\" />\n";
        }
    }

    /**
     * Get post/page/product title for meta
     */
    public static function get_title($post) {
        $title = apply_filters('wdseo_title', $post->post_title, $post);
        return $title;
    }

    /**
     * Get meta description or excerpt fallback
     */
    public static function get_description($post) {
        $description = '';

        if (!empty($post->post_excerpt)) {
            $description = $post->post_excerpt;
        } else {
            $description = wp_trim_words(strip_tags($post->post_content), 20, '...');
        }

        $description = apply_filters('wdseo_description', $description, $post);
        return $description;
    }

    /**
     * Get featured image URL or default logo
     */
    public static function get_image($post) {
        $image = '';

        if (has_post_thumbnail($post->ID)) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
            $image = $thumbnail[0];
        } else {
            // Optional: Use a default logo or fallback image from settings
            $default_logo = WDSEO_PLUGIN_URL . 'assets/images/default-logo.png'; // Can be filtered
            $image = apply_filters('wdseo_default_og_image', $default_logo);
        }

        return $image;
    }
}

// Hook into plugins_loaded
add_action('plugins_loaded', array('Wdseo_Social_Meta', 'init'));