<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Settings {

    private static $tabs = array(
        'general' => 'General',
        'titles' => 'Titles & Meta',
        'robots' => 'Robots Meta',
        'social' => 'Social Meta',
        'sitemap' => 'XML Sitemap'
    );

    private static $frequencies = array(
        'always' => 'Always',
        'hourly' => 'Hourly',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'never' => 'Never'
    );

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    public static function enqueue_admin_assets($hook) {
        if ('settings_page_wild-dragon-seo' !== $hook) return;

        wp_enqueue_style('wdseo-admin-style', WDSEO_PLUGIN_URL . 'assets/css/admin-style.css');
    }

    public static function add_settings_page() {
        add_options_page(
            'Wild Dragon SEO Settings',
            'Wild Dragon SEO',
            'manage_options',
            'wild-dragon-seo',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function register_settings() {
        // Register general settings
        register_setting('wdseo_settings_group', 'wdseo_remove_site_name_from_title', array(
            'type' => 'array',
            'default' => array(),
        ));

        // Register robots defaults for all public post types
        $post_types = get_post_types(array('public' => true));
        foreach ($post_types as $post_type) {
            $field_id = "wdseo_default_robots_{$post_type}";
            register_setting('wdseo_settings_group', $field_id, array(
                'type' => 'string',
                'default' => 'index,follow',
            ));
        }

        // Register robots defaults for taxonomies
        $taxonomies = get_taxonomies(array('public' => true));
        foreach ($taxonomies as $taxonomy) {
            $field_id = "wdseo_default_robots_{$taxonomy}";
            register_setting('wdseo_settings_group', $field_id, array(
                'type' => 'string',
                'default' => 'index,follow',
            ));
        }

        // Register special pages
        $special_pages = array(
            'author_archives' => 'Author Archives',
            'user_profiles' => 'User Profile Pages'
        );
        foreach ($special_pages as $key => $label) {
            $field_id = "wdseo_default_robots_{$key}";
            register_setting('wdseo_settings_group', $field_id, array(
                'type' => 'string',
                'default' => 'index,follow',
            ));
        }

        // Register blocked URLs
        register_setting('wdseo_settings_group', 'wdseo_robots_blocked_urls', array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitize_textarea_input')
        ));

        // Register social meta defaults
        register_setting('wdseo_settings_group', 'wdseo_twitter_site_handle', array(
            'type' => 'string',
            'default' => '@WildDragonOfficial',
        ));

        // Register sitemap settings
        $content_types = array(
            'homepage' => 'Homepage',
            'posts' => 'Posts',
            'pages' => 'Pages',
            'products' => 'Products',
            'product_categories' => 'Product Categories',
            'post_categories' => 'Post Categories'
        );

        foreach ($content_types as $type => $label) {
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_include", array(
                'type' => 'boolean',
                'default' => true
            ));
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_frequency", array(
                'type' => 'string',
                'default' => ($type === 'homepage' || $type === 'products') ? 'daily' : 'weekly'
            ));
            register_setting('wdseo_settings_group', "wdseo_sitemap_{$type}_priority", array(
                'type' => 'float',
                'default' => ($type === 'homepage') ? 1.0 : 0.8
            ));
        }
    }

    public static function render_settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap wdseo-settings">
            <h1>Wild Dragon SEO Settings</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach (self::$tabs as $slug => $title): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=wild-dragon-seo&tab=' . $slug)); ?>"
                       class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($title); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="options.php" method="post" class="wdseo-form">
                <?php
                settings_fields('wdseo_settings_group');
                do_settings_sections('wild-dragon-seo');

                switch ($tab):
                    case 'titles':
                        self::render_titles_section();
                        break;
                    case 'robots':
                        self::render_robots_section();
                        break;
                    case 'social':
                        self::render_social_section();
                        break;
                    case 'sitemap':
                        self::render_sitemap_section();
                        break;
                    default:
                        self::render_general_section();
                endswitch;

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private static function render_sitemap_section() {
        $content_types = array(
            'homepage' => array(
                'label' => 'Homepage',
                'default_freq' => 'daily',
                'default_priority' => '1.0'
            ),
            'posts' => array(
                'label' => 'Posts',
                'default_freq' => 'weekly',
                'default_priority' => '0.8'
            ),
            'pages' => array(
                'label' => 'Pages',
                'default_freq' => 'monthly',
                'default_priority' => '0.6'
            ),
            'products' => array(
                'label' => 'Products',
                'default_freq' => 'daily',
                'default_priority' => '0.8'
            ),
            'product_categories' => array(
                'label' => 'Product Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7'
            ),
            'post_categories' => array(
                'label' => 'Post Categories',
                'default_freq' => 'weekly',
                'default_priority' => '0.7'
            )
        );

        echo '<table class="form-table" role="presentation"><tbody>';

        foreach ($content_types as $type => $info) {
            $include = get_option("wdseo_sitemap_{$type}_include", true);
            $frequency = get_option("wdseo_sitemap_{$type}_frequency", $info['default_freq']);
            $priority = get_option("wdseo_sitemap_{$type}_priority", $info['default_priority']);

            echo "<tr>
                    <th scope=\"row\"><label>{$info['label']}</label></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type=\"checkbox\" name=\"wdseo_sitemap_{$type}_include\" value=\"1\" " . checked($include, true, false) . ">
                                Include in Sitemap
                            </label>
                            <br><br>
                            <label>
                                Update Frequency:
                                <select name=\"wdseo_sitemap_{$type}_frequency\" class=\"regular-text\">";
            
            foreach (self::$frequencies as $value => $label) {
                echo "<option value=\"{$value}\"" . selected($frequency, $value, false) . ">{$label}</option>";
            }

            echo "</select>
                            </label>
                            <br><br>
                            <label>
                                Priority:
                                <select name=\"wdseo_sitemap_{$type}_priority\" class=\"regular-text\">";
            
            for ($i = 0.0; $i <= 1.0; $i += 0.1) {
                $value = number_format($i, 1);
                echo "<option value=\"{$value}\"" . selected($priority, $value, false) . ">{$value}</option>";
            }

            echo "</select>
                            </label>
                        </fieldset>
                    </td>
                </tr>";
        }

        echo '</tbody></table>';
    }

    public static function render_general_section() {
        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">General Settings</th>
                    <td>
                        <p>Configure general SEO settings here.</p>
                    </td>
                </tr>
              </table>';
    }

    public static function render_titles_section() {
        $types = array(
            'post' => 'Posts',
            'page' => 'Pages',
            'product' => 'Products',
            'product_cat' => 'Product Categories',
            'home' => 'Home Page',
        );

        $checked = (array) get_option('wdseo_remove_site_name_from_title', array());

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Remove Site Name From Title On:</th><td>';

        foreach ($types as $key => $label) {
            echo "<label>
                    <input type=\"checkbox\" name=\"wdseo_remove_site_name_from_title[]\" value=\"$key\" " . checked(in_array($key, $checked), true, false) . ">
                    $label
                  </label><br>";
        }

        echo '</td></tr></table>';
    }

    public static function render_robots_section() {
        $post_types = get_post_types(array('public' => true));
        $taxonomies = get_taxonomies(array('public' => true));
        $special_pages = array(
            'author_archives' => 'Author Archives',
            'user_profiles' => 'User Profile Pages'
        );

        echo '<table class="form-table" role="presentation">';
        
        // Post types
        foreach ($post_types as $post_type) {
            $obj = get_post_type_object($post_type);
            $value = get_option("wdseo_default_robots_{$post_type}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$obj->label}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$post_type}", $value);
            echo "</td></tr>";
        }

        // Taxonomies
        foreach ($taxonomies as $taxonomy) {
            $obj = get_taxonomy($taxonomy);
            $value = get_option("wdseo_default_robots_{$taxonomy}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$obj->label}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$taxonomy}", $value);
            echo "</td></tr>";
        }

        // Special pages
        foreach ($special_pages as $key => $label) {
            $value = get_option("wdseo_default_robots_{$key}", 'index,follow');

            echo "<tr>
                    <th scope=\"row\">Robots Meta - {$label}</th>
                    <td>";
            self::render_robots_select("wdseo_default_robots_{$key}", $value);
            echo "</td></tr>";
        }

        // Blocked URLs
        echo "<tr>
                <th scope=\"row\">Block Specific URLs</th>
                <td>
                    <textarea name=\"wdseo_robots_blocked_urls\" rows=\"10\" class=\"large-text code\">" . 
                        esc_textarea(get_option('wdseo_robots_blocked_urls', '')) . 
                    "</textarea>
                    <p class=\"description\">Enter one URL pattern per line. Use * as wildcard.</p>
                </td>
              </tr>";

        echo '</table>';
    }

    public static function render_social_section() {
        $handle = get_option('wdseo_twitter_site_handle', '@WildDragonOfficial');

        echo '<table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Twitter Site Handle</th>
                    <td>
                        <input type="text" name="wdseo_twitter_site_handle" value="' . esc_attr($handle) . '" class="regular-text">
                        <p class="description">Used in Twitter Card meta tags.</p>
                    </td>
                </tr>
              </table>';
    }

    public static function render_robots_select($name, $value) {
        $options = array(
            'index,follow' => 'Index, Follow',
            'noindex,nofollow' => 'Noindex, Nofollow',
            'index,nofollow' => 'Index, Nofollow',
            'noindex,follow' => 'Noindex, Follow',
        );

        echo '<select name="' . esc_attr($name) . '">';

        foreach ($options as $val => $label) {
            $selected = selected($value, $val, false);
            echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
    }

    public static function sanitize_textarea_input($input) {
        $lines = explode("\n", $input);
        $cleaned = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }
}

add_action('plugins_loaded', array('Wdseo_Settings', 'init'));