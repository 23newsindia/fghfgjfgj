<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Meta_Description {

    public static function init() {
        // Post types
        add_action('add_meta_boxes', array(__CLASS__, 'add_description_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_description_meta'), 10, 2);

        // Term meta (product categories)
        add_action('product_cat_add_form_fields', array(__CLASS__, 'add_term_description_field'));
        add_action('product_cat_edit_form_fields', array(__CLASS__, 'edit_term_description_field'), 10, 2);
        add_action('created_term', array(__CLASS__, 'save_term_description'), 10, 3);
        add_action('edit_term', array(__CLASS__, 'save_term_description'), 10, 3);

        // Front page / home page
        add_action('admin_init', array(__CLASS__, 'add_front_page_description_support'));

        // Output meta description tag
        add_action('wp_head', array(__CLASS__, 'output_meta_description'), 5);
    }

    /**
     * Add meta box to posts/pages/products
     */
    public static function add_description_meta_box() {
        $post_types = array('post', 'page', 'product');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'wdseo_meta_description',
                'Meta Description',
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public static function render_meta_box($post) {
        wp_nonce_field('wdseo_save_meta', 'wdseo_meta_nonce');
        $desc = get_post_meta($post->ID, '_wdseo_meta_description', true);
        echo '<textarea name="wdseo_meta_description" rows="3" style="width:100%;">' . esc_textarea($desc) . '</textarea>';
    }

    public static function save_description_meta($post_id, $post) {
        if (!isset($_POST['wdseo_meta_nonce']) || !wp_verify_nonce($_POST['wdseo_meta_nonce'], 'wdseo_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $description = isset($_POST['wdseo_meta_description']) ? sanitize_text_field($_POST['wdseo_meta_description']) : '';
        update_post_meta($post_id, '_wdseo_meta_description', $description);
    }

    /**
     * Add meta field to product categories
     */
    public static function add_term_description_field($term) {
        echo '<div class="form-field">
                <label for="wdseo_term_description">Meta Description</label>
                <textarea name="wdseo_term_description" id="wdseo_term_description"></textarea>
              </div>';
    }

    public static function edit_term_description_field($term, $taxonomy) {
        $desc = get_term_meta($term->term_id, '_wdseo_term_description', true);
        echo '<tr class="form-field">
                <th scope="row"><label for="wdseo_term_description">Meta Description</label></th>
                <td>
                    <textarea name="wdseo_term_description" id="wdseo_term_description">' . esc_textarea($desc) . '</textarea>
                </td>
              </tr>';
    }

    public static function save_term_description($term_id, $tt_id, $taxonomy) {
        if (!isset($_POST['wdseo_term_description'])) return;

        $desc = sanitize_text_field($_POST['wdseo_term_description']);
        update_term_meta($term_id, '_wdseo_term_description', $desc);
    }

    /**
     * Support meta description for front page
     */
    public static function add_front_page_description_support() {
        add_settings_field(
            'wdseo_home_meta_description',
            'Home Page Meta Description',
            array(__CLASS__, 'render_home_description_field'),
            'reading',
            'default'
        );

        register_setting('reading', 'wdseo_home_meta_description', 'sanitize_text_field');
    }

    public static function render_home_description_field() {
        $desc = get_option('wdseo_home_meta_description', '');
        echo '<textarea name="wdseo_home_meta_description" rows="3" style="width:100%;">' . esc_textarea($desc) . '</textarea>';
    }

    /**
     * Output meta description tag
     */
    public static function output_meta_description() {
        $desc = '';

        if (is_singular()) {
            global $post;
            $desc = get_post_meta($post->ID, '_wdseo_meta_description', true);
            if (!$desc) {
                $desc = self::generate_description_from_content($post);
            }
        } elseif (is_tax('product_cat')) {
            $term_id = get_queried_object()->term_id;
            $desc = get_term_meta($term_id, '_wdseo_term_robots_directive', true);
        } elseif (is_front_page() || is_home()) {
            $desc = get_option('wdseo_home_meta_description', '');
        }

        if (!empty($desc)) {
            echo "<meta name=\"description\" content=\"" . esc_attr($desc) . "\" />\n";
        }
    }

    /**
     * Generate description from title and content
     */
    public static function generate_description_from_content($post) {
        $title = get_the_title($post->ID);
        $content = strip_tags(get_the_content(null, false, $post));
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
        $content = trim($content);

        // Start with title
        $desc = '';

        if (strlen($title) <= 60) {
            $desc = $title;
        } else {
            $desc = substr($title, 0, 57) . '...';
        }

        // Add content snippet without going over 160 chars
        $remaining_length = 160 - strlen($desc);
        if ($remaining_length > 20) {
            $desc .= '. ' . wp_trim_words($content, $remaining_length / 8, '...');
        }

        return $desc;
    }
}

add_action('plugins_loaded', array('Wdseo_Meta_Description', 'init'));