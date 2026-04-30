<?php

/**
 * Plugin Name: Couplands Listing System
 * Description: Advanced filtering system for Caravans, Motorhomes, and Products with AJAX support, CSV Importer, Listing Gallery, and Shortcodes.
 * Version: 2.8.6
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 * Text Domain: couplands-listing
 */

// Prevent direct file access for security
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Listing_Registrar
 * Handles OOP Creation of Post Types and Taxonomies
 */
class Listing_Registrar
{
    public function init()
    {
        add_action('init', array($this, 'register_data'));
    }

    public function register_data()
    {
        $this->register_post_types();
        $this->register_taxonomies();
        $this->insert_default_terms();
    }

    private function register_post_types()
    {
        $types = ['caravan', 'motorhome', 'campervan'];

        foreach ($types as $type) {
            $labels = array(
                'name'               => ucfirst($type) . 's',
                'singular_name'      => ucfirst($type),
                'menu_name'          => ucfirst($type) . 's',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New ' . ucfirst($type),
                'edit_item'          => 'Edit ' . ucfirst($type),
                'new_item'           => 'New ' . ucfirst($type),
                'view_item'          => 'View ' . ucfirst($type),
                'search_items'       => 'Search ' . ucfirst($type) . 's',
                'not_found'          => 'No ' . $type . 's found',
                'not_found_in_trash' => 'No ' . $type . 's found in Trash',
            );

            $args = array(
                'labels'              => $labels,
                'public'              => true,
                'has_archive'         => false,
                'publicly_queryable'  => true,
                'query_var'           => true,
                'rewrite'             => array('slug' => $type),
                'capability_type'     => 'post',
                'hierarchical'        => false,
                'supports'            => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
                'menu_position'       => 5,
                'show_in_menu'        => false, // We use a custom admin menu in Listing_System
                'show_in_rest'        => true,
            );

            register_post_type($type, $args);
        }
    }

    private function register_taxonomies()
    {
        // Register Listing Make & Model
        register_taxonomy('listing-make-model', ['caravan', 'motorhome', 'campervan'], [
            'labels' => [
                'name'          => 'Makes & Models',
                'singular_name' => 'Make & Model',
                'search_items'  => 'Search Makes & Models',
                'all_items'     => 'All Makes & Models',
                'parent_item'   => 'Parent Make',
                'edit_item'     => 'Edit Make/Model',
                'update_item'   => 'Update Make/Model',
                'add_new_item'  => 'Add New Make/Model',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
        ]);

        // Register Listing Locations
        register_taxonomy('listing-location', ['caravan', 'motorhome', 'campervan'], [
            'labels' => [
                'name'          => 'Locations',
                'singular_name' => 'Location',
                'search_items'  => 'Search Locations',
                'all_items'     => 'All Locations',
                'edit_item'     => 'Edit Location',
                'update_item'   => 'Update Location',
                'add_new_item'  => 'Add New Location',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ]);

        // NEW: Register Listing Category
        register_taxonomy('listing-category', ['caravan', 'motorhome', 'campervan'], [
            'labels' => [
                'name'          => 'Listing Categories',
                'singular_name' => 'Listing Category',
                'search_items'  => 'Search Categories',
                'all_items'     => 'All Categories',
                'edit_item'     => 'Edit Category',
                'update_item'   => 'Update Category',
                'add_new_item'  => 'Add New Category',
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Helper: Insert Default Term if it doesn't exist
     */
    private function insert_default_terms()
    {
        if (!term_exists('Listing', 'listing-category')) {
            wp_insert_term('Listing', 'listing-category');
        }
    }
}


class Listing_System
{

    public function __construct()
    {
        // Initialize Registration
        $registrar = new Listing_Registrar();
        $registrar->init();

        // --- Front-end actions (Filters) ---

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_filter_caravans', array($this, 'ajax_filter_caravans'));
        add_action('wp_ajax_nopriv_filter_caravans', array($this, 'ajax_filter_caravans'));

        // --- Front-end actions (New Shortcodes) ---
        add_shortcode('caravan_filter', array($this, 'render_caravan_filter'));
        add_shortcode('location_details', array($this, 'render_location_details'));
        add_shortcode('listing_selection', array($this, 'render_listing_selection'));
        add_shortcode('pricing', array($this, 'render_pricing'));
        add_shortcode('listing_gallery', array($this, 'render_listing_gallery'));
        add_shortcode('listing_feature', array($this, 'render_listing_feature'));
        add_shortcode('listing_meta_fields', array($this, 'render_listing_meta_fields'));
        add_shortcode('listing_filter_mobile', array($this, 'render_listing_filter_mobile'));

        // --- NEW: Sorting Shortcode ---
        add_shortcode('listing_sorting', array($this, 'render_listing_sorting'));

        // --- NEW: Manufacturer Search Shortcode ---
        add_shortcode('manufacturer_search', array($this, 'render_manufacturer_search'));

        // --- NEW: View Model URL Shortcode ---
        add_shortcode('view_model_url', array($this, 'render_view_model_url'));

        // --- NEW: Child Grid Shortcode (Based on Filter Builder) ---
        add_shortcode('current_term_image', array($this, 'render_current_term_image_shortcode'));
        add_shortcode('listing_model_grid', array($this, 'render_listing_model_grid'));



        // --- NEW: Child Page Template Override ---
        add_filter('template_include', array($this, 'load_first_level_child_template'));

        // --- Admin actions (Menu & Importer) ---
        // Priority 99 ensures this runs AFTER post types are registered so we can move them
        add_action('admin_menu', array($this, 'register_admin_page'), 99);

        // --- Menu Highlighting Filters ---
        add_filter('parent_file', array($this, 'highlight_parent_menu'));
        add_filter('submenu_file', array($this, 'highlight_submenu_item'));
        add_filter('admin_head', array($this, 'fix_parent_css_highlight')); // Styling fix

        // --- NEW: AJAX Import Hooks ---
        add_action('wp_ajax_couplands_import_upload', array($this, 'ajax_import_upload'));
        add_action('wp_ajax_couplands_import_process', array($this, 'ajax_import_process'));

        // --- Admin actions (Listing Gallery Meta Box) ---
        // 1. Register the Meta Box for specific Post Types
        add_action('add_meta_boxes', array($this, 'listing_gallery_add_meta_box'));
        // 2. Register Page Filter Builder Meta Box
        add_action('add_meta_boxes', array($this, 'add_page_filter_builder_meta_box'));

        // 3. Save the Data
        add_action('save_post', array($this, 'listing_gallery_save_meta'));
        add_action('save_post', array($this, 'save_page_filter_builder_meta'));

        // 4. Load Scripts for Media Manager
        add_action('admin_footer', array($this, 'listing_gallery_admin_scripts'));

        // --- ADMIN: Register Settings ---
        add_action('admin_init', array($this, 'register_plugin_settings'));
    }



    /**
     * Shortcode callback to retrieve and display ACF fields or term names from current post terms.
     * Retrieves terms for a given taxonomy on the current post, loops through them, 
     * and conditionally fetches specified ACF fields or the native term name.
     * Supports formatting outputs as telephone links, email links, or taxonomy term names.
     * Allows omitting the HTML wrapper element by setting the 'wrapper' attribute to '0'.
     *
     * @param array $atts Shortcode attributes (taxonomy, meta_keys, separator, wrapper, type).
     * @return string HTML output of the concatenated values.
     */
    public function render_location_details($atts)
    {
        // Parse and sanitize shortcode attributes
        $atts = shortcode_atts(
            array(
                'taxonomy'   => 'listing-location', // The target taxonomy name
                'meta_keys'  => '',                 // Comma-separated list of ACF field names
                'separator'  => ', ',               // Separator for the output string
                'wrapper'    => 'span',             // HTML element to wrap the final output (set to '0' for no wrapper)
                'type'       => 'default',          // Output type formatting: default, tel, email, or term_name
            ),
            $atts,
            'location_acf'
        );

        $post_id = get_the_ID();
        if (! $post_id) {
            return '';
        }

        $taxonomy  = sanitize_key($atts['taxonomy']);
        // Convert comma-separated string into a clean array of meta keys
        $meta_keys = array_filter(array_map('trim', explode(',', sanitize_text_field($atts['meta_keys']))));
        $type      = sanitize_text_field($atts['type']);

        // Require meta_keys unless the requested output is strictly the term name
        if (empty($meta_keys) && 'term_name' !== $type) {
            return '';
        }

        // Retrieve terms attached to the current post
        $terms = wp_get_post_terms($post_id, $taxonomy);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $output_values = array();

        // Iterate through each term to extract the requested data
        foreach ($terms as $term) {

            // Bypass ACF field retrieval if strictly displaying the term name
            if ('term_name' === $type) {
                $output_values[] = esc_html($term->name);
                continue;
            }

            // Otherwise, iterate through the requested ACF meta keys
            foreach ($meta_keys as $meta_key) {
                // Pass the WP_Term object directly to ACF (supported in ACF 5+)
                $field_value = get_field($meta_key, $term);

                if (! empty($field_value)) {

                    // Handle complex array returns (e.g., choice fields, basic arrays)
                    if (is_array($field_value)) {
                        // Flatten arrays containing scalar values
                        $display_value = esc_html(implode(' ', array_filter($field_value, 'is_scalar')));
                    } else {
                        $display_value = esc_html($field_value);
                    }

                    // Apply link formatting based on the requested type
                    if ('tel' === $type) {
                        // Strip non-numeric characters for the href, keeping '+' for international codes
                        $tel_href = preg_replace('/[^0-9+]/', '', $field_value);
                        $output_values[] = sprintf('<a href="tel:%s">%s</a>', esc_attr($tel_href), $display_value);
                    } elseif ('email' === $type) {
                        // Sanitize email string for the href attribute
                        $email_href = sanitize_email($field_value);
                        $output_values[] = sprintf('<a href="mailto:%s">%s</a>', esc_attr($email_href), $display_value);
                    } else {
                        $output_values[] = $display_value;
                    }
                }
            }
        }

        if (empty($output_values)) {
            return '';
        }

        // Format final output
        $separator = esc_html($atts['separator']);
        $content   = implode($separator, $output_values);

        // Conditionally return raw content if the wrapper is explicitly disabled
        if ('0' === $atts['wrapper']) {
            return $content;
        }

        $wrapper = tag_escape($atts['wrapper']);
        return sprintf('<%1$s class="dd-location-acf-output">%2$s</%1$s>', $wrapper, $content);
    }
    /**
     * Retrieves the raw CSS content for a specific Elementor template/post.
     *
     * @param int $post_id The ID of the Elementor template or post.
     * @return string|false The CSS content or false if Elementor is not active/invalid ID.
     */
    public function get_elementor_template_css($post_id)
    {
        if (! class_exists('\Elementor\Core\Files\CSS\Post')) {
            return false;
        }

        // Instantiate the CSS file handler for the specific post ID
        $css_file = new \Elementor\Core\Files\CSS\Post($post_id);

        // Ensure the CSS file exists; if not, this triggers a regeneration
        $css_file->enqueue();

        // Retrieve the content directly from the generated file
        return $css_file->get_content();
    }

    /**
     * Helper: Get the custom archive link based on backend settings
     */

    public function get_listing_archive_link($post_type)
    {
        // Products still use standard Woo archive
        if ($post_type === 'product') {
            return get_post_type_archive_link('product');
        }

        // Check for saved page ID
        $page_id = get_option('couplands_archive_page_' . $post_type);
        if ($page_id) {
            return get_permalink($page_id);
        }

        // Fallback to standard (won't work if archive disabled, but safe fallback)
        return get_post_type_archive_link($post_type);
    }

    /**
     * Helper: Check if current page is the designated listing page
     */
    public function is_listing_page($post_type)
    {
        if ($post_type === 'product') {
            return is_post_type_archive('product') || is_tax('product_cat') || is_tax('product_tag');
        }

        $page_id = get_option('couplands_archive_page_' . $post_type);

        // Check if we are on the specific page ID
        if ($page_id && is_page($page_id)) {
            return true;
        }

        return false;
    }

    /**
     * Helper: Get Elementor Templates
     */
    public function get_elementor_templates()
    {
        $templates = get_posts(array(
            'post_type' => 'elementor_library',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $options = array(0 => 'Select Template');
        if (!empty($templates)) {
            foreach ($templates as $template) {
                $options[$template->ID] = $template->post_title;
            }
        }

        return $options;
    }

    /**
     * NEW: Load First Level Child Page Template Logic
     * Logic: If current page is a direct child of the selected "Archive Page", load the Elementor template.
     */
    public function load_first_level_child_template($template)
    {
        global $post;

        // Must be a standard Page, and must have a parent
        if (!is_page() || empty($post->post_parent)) {
            return $template;
        }

        $parent_id = $post->post_parent;
        $target_template_id = 0;

        // 1. Check if parent is the Caravan Archive Page
        $caravan_archive_id = get_option('couplands_archive_page_caravan');
        if ($caravan_archive_id && $parent_id == $caravan_archive_id) {
            $target_template_id = get_option('couplands_child_template_caravan');
        }

        // 2. Check if parent is the Motorhome Archive Page
        $motorhome_archive_id = get_option('couplands_archive_page_motorhome');
        if ($motorhome_archive_id && $parent_id == $motorhome_archive_id) {
            $target_template_id = get_option('couplands_child_template_motorhome');
        }

        // 3. Check if parent is the Campervan Archive Page
        $campervan_archive_id = get_option('couplands_archive_page_campervan');
        if ($campervan_archive_id && $parent_id == $campervan_archive_id) {
            $target_template_id = get_option('couplands_child_template_campervan');
        }

        // Apply Template if found
        if ($target_template_id && $target_template_id != 0) {
            // If a valid Elementor template is selected, we override the render.
            // We use get_header() and get_footer() to maintain theme assets.
            // Then we inject the Elementor template via shortcode.

            get_header();
            echo do_shortcode('[elementor-template id="' . $target_template_id . '"]');
            get_footer();
            exit; // Stop further template loading
        }

        return $template;
    }

    /**
     * 1. Enqueue Scripts (Front End)
     */
    public function enqueue_scripts()
    {
        wp_register_script('caravan-filter-js', false, array('jquery'));
        wp_enqueue_script('caravan-filter-js');

        wp_add_inline_script('caravan-filter-js', '
            var caravan_ajax_url = "' . admin_url('admin-ajax.php') . '";
        ');
    }

    /* ==========================================================================
       NEW SHORTCODE METHODS
       ========================================================================== */

    /**
     * Generates a 3-column data grid populated by the configured backend ACF fields.
     * Incorporates custom styling to precisely match the target design interface.
     * Features logic for overriding default labels and formatting prices dynamically.
     *
     * @param array $atts Shortcode attributes.
     * @return string Structured HTML of the generated Grid.
     */
    public function render_listing_meta_fields($atts)
    {
        $post_id = get_the_ID();
        if (!$post_id) return '';

        $saved_config = get_option('couplands_meta_fields_config', []);

        // Fallback: If no config is saved yet, dynamically load the default sequence directly from ACF
        if (empty($saved_config) && function_exists('acf_get_fields')) {
            $target_groups = [541, 2642, 2443];
            foreach ($target_groups as $group_id) {
                $fields = acf_get_fields($group_id);
                if ($fields) {
                    foreach ($fields as $field) {
                        $saved_config[] = [
                            'name'         => $field['name'],
                            'label'        => $field['label'],
                            'visible'      => 1,
                            'custom_label' => '',
                            'is_price'     => 0
                        ];
                    }
                }
            }
        }

        if (empty($saved_config)) return '';

        ob_start();
?>
        <div class="couplands-meta-grid">
            <?php
            foreach ($saved_config as $field_conf) {
                if (empty($field_conf['visible'])) continue;

                $field_name = $field_conf['name'];

                // Determine Label (Override default if custom_label is provided)
                $default_label = isset($field_conf['label']) ? $field_conf['label'] : '';
                $custom_label  = isset($field_conf['custom_label']) ? $field_conf['custom_label'] : '';
                $label         = !empty($custom_label) ? $custom_label : $default_label;

                // Determine if field should be formatted as Price
                $is_price      = isset($field_conf['is_price']) && $field_conf['is_price'] == 1;

                // Retrieve the value via ACF natively if active, or fallback to standard post meta
                $value = function_exists('get_field') ? get_field($field_name, $post_id) : get_post_meta($post_id, $field_name, true);

                if (empty($value)) continue;

                // Handle complex multi-value data structures (e.g., Checkboxes, relational fields)
                if (is_array($value)) {
                    $formatted_vals = [];
                    foreach ($value as $v) {
                        if (is_array($v) && isset($v['label'])) {
                            $formatted_vals[] = $v['label'];
                        } elseif (is_array($v) && isset($v['name'])) {
                            $formatted_vals[] = $v['name'];
                        } elseif (is_string($v) || is_numeric($v)) {
                            $formatted_vals[] = $v;
                        }
                    }
                    $value = implode(', ', $formatted_vals);
                }

                // Apply Price Formatting if toggled
                if ($is_price && !empty($value)) {
                    // Strip out non-numeric characters (keep decimals) to parse cleanly
                    $clean_val = preg_replace('/[^\d.]/', '', (string)$value);
                    if (is_numeric($clean_val)) {
                        // Check if it's a whole number or has decimals to format correctly
                        $formatted_num = (floatval($clean_val) == intval($clean_val))
                            ? number_format((int)$clean_val)
                            : number_format((float)$clean_val, 2);

                        $value = '£' . $formatted_num;
                    }
                }
            ?>
                <div class="meta-grid-item">
                    <div class="meta-grid-label"><?php echo esc_html($label); ?></div>
                    <div class="meta-grid-value"><?php echo wp_kses_post($value); ?></div>
                </div>
            <?php } ?>
        </div>
        <style>
            .couplands-meta-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                column-gap: 20px;
            }

            .meta-grid-item {
                display: flex;
                flex-direction: column;
                border-bottom: 1px solid #D3DAE6;
                padding: 18px 0;
            }

            .meta-grid-label {
                font-size: 14px;
                color: #5d6168;
                margin-bottom: 8px;
            }

            .meta-grid-value {
                font-size: 15px;
                font-weight: 700;
                color: #181C21;
            }

            @media (max-width: 991px) {
                .couplands-meta-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 575px) {
                .couplands-meta-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Mobile Filter Trigger
     * [listing_filter_mobile]
     * 
     * Outputs a button designed to trigger the caravan filter modal.
     * Inherently hidden on viewports > 1024px via associated CSS.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML structure of the filter trigger.
     */
    public function render_listing_filter_mobile($atts)
    {
        ob_start();
    ?>
        <button id="cls-mobile-filter-trigger" class="cls-mobile-filter-btn" type="button" aria-label="Open Filters">
            Filters
            <!-- Standard filter icon mirroring the provided design constraint -->
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 18H14V16H10V18ZM3 6V8H21V6H3ZM6 13H18V11H6V13Z" fill="currentColor" />
            </svg>
        </button>
    <?php
        return ob_get_clean();
    }


    /**
     * Shortcode: View Model URL
     * [view_model_url]
     * Returns the URL of the parent page with current page filters appended.
     */
    public function render_view_model_url($atts)
    {

        // 1. Get current Page ID
        $post_id = get_the_ID();

        // 2. Get Parent Page ID
        $parent_id = wp_get_post_parent_id($post_id);

        // 3. Fallback if no parent exists, return home URL or empty
        if (!$parent_id) {
            return home_url();
        }

        $url = get_permalink($parent_id);

        $term = get_queried_object();

        $args['model'] = $term->slug;
        $url = add_query_arg($args, $url);


        // 4. Retrieve Filter Builder Data from Meta
        $filters = get_post_meta($post_id, '_couplands_page_filters', true);


        // 5. Append filters to URL
        if (!empty($filters) && is_array($filters)) {
            $args = [];
            foreach ($filters as $filter) {
                // If value is missing, skip
                if (empty($filter['value'])) continue;

                // Use custom url_param if set, otherwise fallback to the key/slug
                $param_key = !empty($filter['url_param']) ? $filter['url_param'] : $filter['key'];

                $args[$param_key] = $filter['value'];
            }

            // Generate URL with query args
            $url = add_query_arg($args, $url);
        }



        return esc_url($url);
    }

    /**
     * Shortcode: Child Grid (Model/Range) based on Page Filters
     * [listing_model_grid]
     */
    public function render_listing_model_grid($atts)
    {
        ob_start();

        // 1. Get current Page ID
        $page_id = get_the_ID();

        // 2. Retrieve Filter Builder Data from Meta
        $filters = get_post_meta($page_id, '_couplands_page_filters', true);
        if (empty($filters) || !is_array($filters)) {
            return '';
        }

        // 3. Get the Template ID for the Grid Item from Global Settings
        $template_id = get_option('couplands_model_grid_item_template', 0);
        if (!$template_id) {
            return '';
        }

        // --- NEW: Determine Post Type based on Parent Page (Archive Mapping) ---
        $parent_id = wp_get_post_parent_id($page_id);

        // Default fallback
        $target_post_type = ['caravan', 'motorhome', 'campervan'];

        if ($parent_id) {
            if ($parent_id == get_option('couplands_archive_page_caravan')) {
                $target_post_type = 'caravan';
            } elseif ($parent_id == get_option('couplands_archive_page_motorhome')) {
                $target_post_type = 'motorhome';
            } elseif ($parent_id == get_option('couplands_archive_page_campervan')) {
                $target_post_type = 'campervan';
            }
        }

        // --- NEW: Filter Terms by "Make" if defined in Page Filters ---
        $make_parent_id = 0;
        foreach ($filters as $filter) {
            // Check if this filter is for the Make/Model taxonomy
            if (isset($filter['key']) && $filter['key'] === 'listing-make-model' && !empty($filter['value'])) {

                $tax_field = isset($filter['tax_field']) ? $filter['tax_field'] : 'term_id';

                // If the filter uses a slug, we must convert it to an ID for the get_terms 'parent' arg
                if ($tax_field === 'slug') {
                    $make_term = get_term_by('slug', $filter['value'], 'listing-make-model');
                    if ($make_term && !is_wp_error($make_term)) {
                        $make_parent_id = $make_term->term_id;
                    }
                } else {
                    // Assume it's an ID
                    $make_parent_id = intval($filter['value']);
                }
                break; // Found the make, stop looping
            }
        }

        // 4. Retrieve child terms (Models/Ranges)
        $term_args = array(
            'taxonomy'   => 'listing-make-model',
            'hide_empty' => true,
        );

        // If a Make filter was found, only fetch children of that Make
        if ($make_parent_id > 0) {
            $term_args['parent'] = $make_parent_id;
        }

        $model_terms = get_terms($term_args);

        // We will filter these terms manually based on whether they have posts that match our Page Filters
        $valid_terms = [];

        if (!empty($model_terms) && !is_wp_error($model_terms)) {
            foreach ($model_terms as $term) {
                // Skip top-level parents if we didn't filter by parent (just in case)
                if ($term->parent == 0) continue;

                // 5. Construct Query to check existence of posts for this Term + Page Filters
                $args = array(
                    'post_type'      => $target_post_type, // Restricted Post Type
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'tax_query'      => array(
                        'relation' => 'AND',
                        array(
                            'taxonomy' => 'listing-make-model',
                            'field'    => 'term_id',
                            'terms'    => $term->term_id,
                        )
                    ),
                    'meta_query'     => array('relation' => 'AND')
                );

                // Apply Page Filters to this check
                $archive_query_args = []; // Array to build URL params later

                foreach ($filters as $filter) {
                    // If value is missing, skip
                    if (empty($filter['value'])) continue;

                    $type = isset($filter['type']) ? $filter['type'] : 'meta';
                    $key = isset($filter['key']) ? $filter['key'] : ''; // This is the Slug/Key
                    $url_param = isset($filter['url_param']) ? $filter['url_param'] : $key; // Fallback to key if empty
                    $compare = isset($filter['compare']) ? $filter['compare'] : '=';

                    // Retrieve the specific field setting (Default to 'term_id' if not set)
                    $tax_field = isset($filter['tax_field']) ? $filter['tax_field'] : 'term_id';

                    // Store for URL generation
                    $archive_query_args[$url_param] = $filter['value'];

                    if ($type === 'taxonomy') {
                        $args['tax_query'][] = array(
                            'taxonomy' => $key,
                            'field'    => $tax_field, // Dynamic: term_id or slug
                            'terms'    => $filter['value'],
                        );
                    } else {
                        // Meta
                        $args['meta_query'][] = array(
                            'key'      => $key,
                            'value'    => $filter['value'],
                            'compare' => $compare
                        );
                    }
                }

                // Run Check
                $check_query = new WP_Query($args);
                if ($check_query->have_posts()) {
                    // Determine Archive Link for this Term
                    $link_type = is_array($target_post_type) ? 'caravan' : $target_post_type;
                    $target_archive = $this->get_listing_archive_link($link_type);

                    // Add the specific term (Model) to the URL params
                    $archive_query_args['model'] = $term->term_id;

                    // Generate URL
                    $term_link = add_query_arg($archive_query_args, $target_archive);

                    // Attach link to term object for template usage
                    $term->custom_archive_link = $term_link;

                    $valid_terms[] = $term;
                }
            }
        }

        if (empty($valid_terms)) {
            return '<p>No models found matching current criteria.</p>';
        }

        $css_content = $this->get_elementor_template_css($template_id);

        if ($css_content) {
            echo '<style id="elementor-post-' . $template_id . '">' . $css_content . '</style>';
        }

    ?>

        <div class="couplands-child-grid">
            <?php foreach ($valid_terms as $term) : ?>
                <div class="child-grid-item" data-term-id="<?php echo esc_attr($term->term_id); ?>">
                    <?php
                    // Set global for Elementor template to access
                    $this->force_set_term_object($term->term_id, 'listing-make-model');
                    echo do_shortcode('[elementor-template id="' . $template_id . '"]');

                    ?>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            .couplands-child-grid {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
        </style>
    <?php

        return ob_get_clean();
    }
    /**
     * Plugin Name:       DD Term Image Shortcode
     * Description:       A shortcode to output an ACF image field from the current taxonomy term.
     * Version:           1.0.0
     * Author:            Digitally Disruptive - Donald Raymundo
     * Author URI:        https://digitallydisruptive.co.uk/
     * Text Domain:       dd-term-image
     */

    /**
     * Shortcode: [dd_term_image]
     * * Outputs an image from an ACF field attached to the current taxonomy term.
     * Intended for use on Taxonomy Archive templates.
     * * Usage: [dd_term_image field="my_acf_image_field" size="medium" class="custom-class"]
     * * @param array $atts User defined attributes in shortcode tag.
     * @return string HTML output of the image or empty string on failure.
     */
    function render_current_term_image_shortcode()
    {
        // Extract shortcode attributes with defaults
        $atts = shortcode_atts(
            array(
                'field' => 'background_image',      // The ACF field name (Key)
                'size'  => 'full',  // Image size (thumbnail, medium, large, full, etc.)
                'class' => '',      // CSS class for the image
                'alt'   => '',      // Optional override for alt text
            ),
            $atts,
            'dd_term_image'
        );

        // Ensure a field name was provided
        if (empty($atts['field'])) {
            return '';
        }

        // Retrieve the current queried object
        $queried_object = get_queried_object();

        // Verify the current page is a Taxonomy Archive and the object is a WP_Term
        if (! is_a($queried_object, 'WP_Term')) {
            return '';
        }

        // Construct the ACF selector for terms: 'term_{term_id}'
        // This allows ACF to locate the metadata for the specific term
        $acf_term_id = $queried_object->taxonomy . '_' . $queried_object->term_id;

        // Retrieve the image field
        // We expect the return format in ACF to be 'Image Array' or 'Image ID'
        $image = get_field($atts['field'], $acf_term_id);

        if (empty($image)) {
            return '';
        }

        // Initialize output variable
        $image_html = '';

        // Handle Image Array format (Recommended ACF Return Format)
        if (is_array($image)) {
            $src = isset($image['sizes'][$atts['size']]) ? $image['sizes'][$atts['size']] : $image['url'];
            $alt = ! empty($atts['alt']) ? $atts['alt'] : $image['alt'];

            $image_html = sprintf(
                '<img src="%s" alt="%s" class="%s" />',
                esc_url($src),
                esc_attr($alt),
                esc_attr($atts['class'])
            );
        }
        // Handle Image ID format
        elseif (is_numeric($image)) {
            $image_html = wp_get_attachment_image(
                $image,
                $atts['size'],
                false,
                array('class' => $atts['class'])
            );
        }
        // Handle Image URL format (Not recommended, but fallback support)
        elseif (is_string($image)) {
            $image_html = sprintf(
                '<img src="%s" alt="%s" class="%s" />',
                esc_url($image),
                esc_attr($atts['alt']), // Fallback to provided alt or empty
                esc_attr($atts['class'])
            );
        }

        return $image_html;
    }

    /**
     * Shortcode: Manufacturer Search Grid
     * [manufacturer_search year="2026"]
     */
    public function render_manufacturer_search($atts)
    {
        ob_start();

        $atts = shortcode_atts(array(
            'year' => date('Y'),
        ), $atts);

        $target_year = sanitize_text_field($atts['year']);

        $terms = get_terms(array(
            'taxonomy'   => 'listing-make-model',
            'parent'     => 0,
            'hide_empty' => true,
        ));

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $post_types = [
            'caravan'   => 'Caravans',
            'motorhome' => 'Motorhomes',
            'campervan' => 'Campervans'
        ];

    ?>
        <div class="manufacturer-search-grid">
            <?php foreach ($terms as $term) : ?>
                <?php
                $logo = get_field('logo', $term);
                $accent_color = get_field('accent_color', $term);
                $bg_style = $accent_color ? 'background-color: ' . esc_attr($accent_color) . ';' : 'background-color: #333;';

                $counts = [];
                $total_year_count = 0;

                foreach ($post_types as $pt_slug => $pt_label) {
                    $args = array(
                        'post_type'      => $pt_slug,
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'tax_query'      => array(
                            array(
                                'taxonomy'         => 'listing-make-model',
                                'field'            => 'term_id',
                                'terms'            => $term->term_id,
                                'include_children' => true,
                            ),
                        ),
                        'meta_query'     => array(
                            array(
                                'key'      => 'year',
                                'value'    => $target_year,
                                'compare' => '=',
                            )
                        )
                    );

                    $query = new WP_Query($args);
                    $count = $query->found_posts;

                    if ($count > 0) {
                        $counts[$pt_slug] = $count;
                        $total_year_count += $count;
                    }
                }

                if ($total_year_count === 0) {
                    continue;
                }
                ?>

                <div class="manufacturer-card">
                    <div class="manufacturer-logo" style="<?php echo $bg_style; ?>">
                        <?php
                        if ($logo) {
                            echo wp_get_attachment_image($logo, 'medium');
                        } else {
                            echo '<h3 style="color:#fff;">' . esc_html($term->name) . '</h3>';
                        }
                        ?>
                    </div>

                    <div class="manufacturer-buttons">
                        <?php foreach ($post_types as $pt_slug => $pt_label) : ?>
                            <?php if (isset($counts[$pt_slug])) : ?>
                                <?php
                                // UPDATED: Use helper to get link, then add query args
                                $archive_url = $this->get_listing_archive_link($pt_slug);
                                $archive_link = add_query_arg(
                                    array(
                                        'make' => $term->term_id,
                                        'vehicle_year' => $target_year
                                    ),
                                    $archive_url
                                );
                                ?>
                                <a href="<?php echo esc_url($archive_link); ?>" class="manu-btn">
                                    <?php echo esc_html($pt_label); ?> (<?php echo intval($counts[$pt_slug]); ?>)
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>

        <style>
            .manufacturer-search-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-bottom: 40px;
            }

            .manufacturer-card {
                border-radius: 20px;
                display: flex;
                flex-direction: column;
                text-align: center;
                transition: transform 0.3s ease;
                background-color: #FFFFFF;
                overflow: hidden;
            }

            .manufacturer-card:hover {
                transform: translateY(-5px);
            }

            .manufacturer-logo {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 70px 20px;
                width: 100%;
            }

            .manufacturer-logo img {
                max-height: 100%;
                height: 160px;
                width: auto;
                object-fit: contain;
            }

            .manufacturer-buttons {
                display: flex;
                flex-direction: column;
                gap: 10px;
                width: 100%;
                padding: 30px;
            }

            .manu-btn {
                display: block;
                background: rgba(255, 255, 255, 0.15);
                border: 1px solid #181C21;
                text-decoration: none;
                padding: 10px 15px;
                border-radius: 4px;
                font-size: 19px;
                font-weight: 500;
                transition: all 0.2s ease;
                width: 100%;
                box-sizing: border-box;
                color: #181C21;
            }

            .manu-btn:hover {
                background: #181C21;
                color: #fff;
                border-color: #181C21;
            }

            @media(max-width: 1199px) {
                .manufacturer-logo {
                    padding: 30px 20px;
                }

                .manufacturer-logo img {
                    height: 120px;
                }
            }

            @media(max-width: 1024px) {
                .manufacturer-search-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media(max-width: 575px) {
                .manufacturer-search-grid .manu-btn {
                    font-size: 16px;
                    padding: 10px 10px;
                }

                .manufacturer-buttons {
                    padding: 10px;
                }

                .manufacturer-logo img {
                    height: 50px;
                }
            }

            @media(max-width: 480px) {
                .manufacturer-search-grid .manu-btn {
                    font-size: 14px;
                }

                .manufacturer-search-grid {
                    gap: 10px;
                }
            }
        </style>
    <?php

        return ob_get_clean();
    }

    /**
     * Shortcode: Listing Selection Form
     * [listing_selection]
     */
    public function render_listing_selection()
    {
        ob_start();

        // 1. Detect the current active type using Helper
        $active_type = 'caravan';
        $initial_action = $this->get_listing_archive_link('caravan');

        if ($this->is_listing_page('caravan')) {
            $active_type = 'caravan';
            $initial_action = $this->get_listing_archive_link('caravan');
        } elseif ($this->is_listing_page('motorhome')) {
            $active_type = 'motorhome';
            $initial_action = $this->get_listing_archive_link('motorhome');
        } elseif ($this->is_listing_page('campervan')) {
            $active_type = 'campervan';
            $initial_action = $this->get_listing_archive_link('campervan');
        }

        // Check for existing GET param override
        if (isset($_GET['vehicle_type'])) {
            $allowed = ['caravan', 'motorhome', 'campervan'];
            if (in_array($_GET['vehicle_type'], $allowed)) {
                $active_type = sanitize_text_field($_GET['vehicle_type']);
                $initial_action = $this->get_listing_archive_link($active_type);
            }
        }

        $active_condition = !empty($_GET['condition']) ? sanitize_text_field($_GET['condition']) : 'New';
        $form_id = 'listing_form_' . uniqid();
    ?>
        <form id="<?php echo esc_attr($form_id); ?>" action="<?php echo esc_url($initial_action); ?>" method="GET">
            <div class="listing-selection">
                <div class="listing-selection-inner">

                    <div class="listing-selection-item">
                        <span>Search by</span>
                        <div class="listing-selection-choices">

                            <label class="radio-label">
                                <input archive_url="<?= $this->get_listing_archive_link('caravan') ?>" type="radio" name="vehicle_type" value="caravan" <?php checked($active_type, 'caravan'); ?>>
                                <span>Caravan</span>
                            </label>

                            <label class="radio-label">
                                <input archive_url="<?= $this->get_listing_archive_link('motorhome') ?>" type="radio" name="vehicle_type" value="motorhome" <?php checked($active_type, 'motorhome'); ?>>
                                <span>Motorhome</span>
                            </label>

                            <label class="radio-label">
                                <input archive_url="<?= $this->get_listing_archive_link('campervan') ?>" type="radio" name="vehicle_type" value="campervan" <?php checked($active_type, 'campervan'); ?>>
                                <span>Campervan</span>
                            </label>

                        </div>
                    </div>

                    <div class="listing-selection-item">
                        <span>Condition</span>
                        <div class="listing-selection-choices">

                            <label class="radio-label">
                                <input type="radio" name="condition" value="New" <?php checked($active_condition, 'New'); ?>>
                                <span>New</span>
                            </label>

                            <label class="radio-label">
                                <input type="radio" name="condition" value="Used" <?php checked($active_condition, 'Used'); ?>>
                                <span>Used</span>
                            </label>

                        </div>
                    </div>

                    <div class="listing-selection-item">
                        <button type="submit" class="search-submit-btn">Search all</button>
                    </div>

                </div>
            </div>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.getElementById('<?php echo $form_id; ?>');
                if (!form) return;

                var radios = form.querySelectorAll('input[name="vehicle_type"]');

                radios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        var newAction = this.getAttribute('archive_url');
                        if (newAction) {
                            form.action = newAction;
                        }
                    });
                });
            });
        </script>

    <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Pricing Display
     * [pricing]
     */
    public function render_pricing()
    {
        ob_start();
        $price = function_exists('get_field') ? get_field('price') : '';
        $per_month = function_exists('get_field') ? get_field('per_month') : '';

        // Helper to format price if function doesn't exist to prevent errors
        $fmt_price = function_exists('price_format') ? price_format($price) : $price;
        $fmt_month = function_exists('price_format') ? price_format($per_month) : $per_month;
    ?>
        <div class="pricing">
            <div class="pricing-box">
                <span class="prefix-suffix">Only</span>
                <span class="value"><?= $fmt_price; ?></span>
            </div>
            <?php if ($fmt_month) { ?>
                <div class="pricing-box">
                    <span class="prefix-suffix">From</span>
                    <span class="value"><?= $fmt_month; ?></span>
                    <span class="prefix-suffix">per <br>month</span>
                </div>
            <?php } ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Front-end Listing Gallery
     * [listing_gallery is_archive="1"]
     */
    public function render_listing_gallery($atts)
    {
        ob_start();
        extract(
            shortcode_atts(
                array(
                    'is_archive'  => 1,
                ),
                $atts
            )
        );

        // Get the Post ID
        $post_id = get_the_ID();

        // 1. Get the existing Gallery IDs
        // --- UPDATED: Check for WooCommerce Product type ---
        if (get_post_type($post_id) === 'product') {
            // WooCommerce Product Gallery
            $product = function_exists('wc_get_product') ? wc_get_product($post_id) : false;
            // Fallback to meta if wc_get_product fails or isn't available
            if ($product) {
                $gallery_ids = $product->get_gallery_image_ids();
            } else {
                $gallery_ids_raw = get_post_meta($post_id, '_product_image_gallery', true);
                $gallery_ids = (!empty($gallery_ids_raw)) ? explode(',', $gallery_ids_raw) : array();
            }
        } else {
            // Standard Listing Gallery
            $gallery_ids_raw = get_post_meta($post_id, '_listing_gallery_ids', true);
            $gallery_ids = (!empty($gallery_ids_raw)) ? explode(',', $gallery_ids_raw) : array();
        }

        // 2. Get the Featured Image ID
        $featured_img_id = get_post_thumbnail_id($post_id);

        // 3. Add Featured Image to the beginning
        if ($featured_img_id) {
            array_unshift($gallery_ids, $featured_img_id);
        }

        // 4. Cleanup
        $gallery_ids = array_unique($gallery_ids);
        $gallery_ids = array_filter($gallery_ids);

        // --- UPDATE: Limit to 4 images if is_archive is true ---
        if ($is_archive) {
            $gallery_ids = array_slice($gallery_ids, 0, 4);
        }

        // Determine image size
        $size = ($is_archive) ? 'listing-grid' : 'large';

        // Create a unique ID for this instance
        $slider_id = 'gallery-' . uniqid();

        if (!empty($gallery_ids)) {
            // --- ADDED: Enqueue Fancybox via CDN for immediate functionality --- 
            // (Note: Ideally, enqueue these in your functions.php using wp_enqueue_script)
        ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
            <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

            <?php if ($is_archive == 0) { ?>
                <?php
                $per_month = function_exists('get_field') ? get_field('per_month') : '';
                $videos = function_exists('get_field') ? get_field('videos') : '';
                $fmt_month = function_exists('price_format') ? price_format($per_month) : $per_month;
                ?>
                <div class="listing-single-gallery">
                    <div class="listing-single-gallery-left">

                        <div class="tags">
                            <?php if ($videos) { ?>
                                <div>Video</div>
                            <?php } ?>
                            <div>Matterport</div>
                            <div><?= count($gallery_ids) ?> images</div>
                        </div>

                    <?php } ?>
                    <div class="swiper-holder">
                        <?php if (!$is_archive) { ?>
                            <a class="open-gallery" href="<?= wp_get_attachment_image_url($gallery_ids[0], 'full') ?>"
                                data-fancybox="<?php echo $slider_id; ?>"
                                data-caption="<?php echo wp_get_attachment_caption($gallery_ids[0]); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-image-fill" viewBox="0 0 16 16">
                                    <path d="M.002 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-12a2 2 0 0 1-2-2zm1 9v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062zm5-6.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0" />
                                </svg>
                                Open Gallery
                            </a>
                        <?php } ?>
                        <?php if (!$is_archive) { ?>
                            <?= do_shortcode('[elementor-template id="734"]') ?>
                        <?php } ?>

                        <div id="<?php echo esc_attr($slider_id); ?>" class="swiper swiper-gallery">
                            <?php if ($is_archive) { ?>
                                <a class="fake-button" href="<?= get_the_permalink($post_id) ?>">
                                </a>
                            <?php } ?>
                            <div class="swiper-wrapper">
                                <?php foreach ($gallery_ids as $gallery_id) { ?>
                                    <div class="swiper-slide">
                                        <?php if ($is_archive == 0) { ?>
                                            <a href="<?= wp_get_attachment_image_url($gallery_id, 'full') ?>"
                                                data-fancybox="<?php echo $slider_id; ?>"
                                                data-caption="<?php echo wp_get_attachment_caption($gallery_id); ?>">
                                            <?php } ?>

                                            <?= wp_get_attachment_image($gallery_id, $size) ?>

                                            <?php if ($is_archive == 0) { ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-button-next"></div>
                        </div>
                    </div>
                    <?php if (!$is_archive) { ?>
                        <?php if ($fmt_month) { ?>
                            <div class="price-circle">
                                <div class="pricing-box">
                                    <span class="prefix-suffix">From</span>
                                    <span class="value"><?= $fmt_month; ?></span>
                                    <span class="prefix-suffix">per month</span>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($is_archive == 0) { ?>
                    </div><?php
                            $gallery_grid = array_slice($gallery_ids, 1, 4);
                            ?>
                    <div class="listing-single-gallery-right">
                        <div class="gallery-grid">
                            <?php foreach ($gallery_grid as $gallery_id) { ?>
                                <div class="gallery-grid-item">
                                    <a href="<?= wp_get_attachment_image_url($gallery_id, 'full') ?>"
                                        data-fancybox="<?php echo $slider_id; ?>"
                                        data-caption="<?php echo wp_get_attachment_caption($gallery_id); ?>">
                                        <?= wp_get_attachment_image($gallery_id, $size) ?>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div><?php } ?>

            <script>
                jQuery(document).ready(function($) {

                    // 1. Initialize Fancybox
                    // Uses the data-fancybox attribute we added to the HTML
                    if (typeof Fancybox !== 'undefined') {
                        Fancybox.bind("[data-fancybox]", {
                            // Your custom options here
                            Thumbs: {
                                type: "modern"
                            }
                        });
                    }


                    // 3. Initialize Swiper
                    if (typeof Swiper !== 'undefined') {
                        new Swiper('#<?php echo $slider_id; ?>', {
                            loop: false,
                            slidesPerView: 1,
                            spaceBetween: 0,
                            autoHeight: true,
                            navigation: {
                                nextEl: '#<?php echo $slider_id; ?> .swiper-button-next',
                                prevEl: '#<?php echo $slider_id; ?> .swiper-button-prev',
                            },
                        });
                    }
                });
            </script>

            <style>
                .swiper-gallery {
                    width: 100%;
                    height: auto;
                    position: relative;
                    overflow: hidden;
                }

                .swiper-slide img {
                    width: 100%;
                    height: auto;
                    display: block;
                }

                /* Optional: Style to indicate image is clickable */
                .swiper-slide a,
                .gallery-grid-item a {
                    cursor: zoom-in;
                    display: block;
                }
            </style>
        <?php
        }
        return ob_get_clean();
    }

    /**
     * Shortcode: Listing Feature List
     * [listing_feature key="interior_features"]
     */
    public function render_listing_feature($atts)
    {
        // 1. Extract shortcode attributes
        $atts = shortcode_atts(array(
            'key' => 'interior_features', // Default meta key
        ), $atts, 'listing');

        // 2. Get the current Post ID
        $post_id = get_the_ID();

        // 3. Retrieve the meta value (string)
        $meta_value = get_post_meta($post_id, $atts['key'], true);

        // 4. Check if data exists
        if (empty($meta_value)) {
            return ''; // Return nothing if the field is empty
        }

        // 5. Convert comma-separated string to an array and clean whitespace
        $items = array_map('trim', explode(',', $meta_value));

        // 6. Build the HTML Output
        $output = '<ul class="listing-features checklist">';

        foreach ($items as $item) {
            // Skip empty items caused by trailing commas
            if (!empty($item)) {
                $output .= '<li>' . esc_html($item) . '</li>';
            }
        }

        $output .= '</ul>';

        // 7. Return the HTML (Shortcodes must return, not echo)
        return $output;
    }

    /**
     * Shortcode: Listing Sorting Dropdown
     * [listing_sorting]
     */
    public function render_listing_sorting()
    {
        ob_start();
        // Check for existing GET param
        $selected_sort = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : '';
        ?>
        <div class="listing-sorting-container">
            <select class="listing-sort-dropdown" name="sort_by">
                <option value="">Default Sorting</option>
                <option value="title_asc" <?php selected($selected_sort, 'title_asc'); ?>>Title (A-Z)</option>
                <option value="title_desc" <?php selected($selected_sort, 'title_desc'); ?>>Title (Z-A)</option>
                <option value="price_asc" <?php selected($selected_sort, 'price_asc'); ?>>Price (Low to High)</option>
                <option value="price_desc" <?php selected($selected_sort, 'price_desc'); ?>>Price (High to Low)</option>
            </select>
        </div>

        <?php
        return ob_get_clean();
    }

    /* ==========================================================================
       ADMIN MENU & IMPORTER FUNCTIONS
       ========================================================================== */

    /* ==========================================================================
       ADMIN MENU HIGHLIGHTING LOGIC
       ========================================================================== */

    /**
     * Forces the "Listings" parent menu to be active when viewing CPTs or Taxonomies
     */
    public function highlight_parent_menu($parent_file)
    {
        global $current_screen;

        // Define our types and tax
        $cpts = ['caravan', 'motorhome', 'campervan'];
        $taxonomies = ['listing-location', 'listing-make-model', 'listing-category'];

        // If we are on a screen related to our CPTs (List, Edit, Add New)
        if ($current_screen && in_array($current_screen->post_type, $cpts)) {
            return 'couplands-listings';
        }

        // If we are on a screen related to our Taxonomies
        if ($current_screen && in_array($current_screen->taxonomy, $taxonomies)) {
            return 'couplands-listings';
        }

        return $parent_file;
    }

    /**
     * Forces the specific SUBMENU item to be active
     */
    public function highlight_submenu_item($submenu_file)
    {
        global $current_screen;

        $cpts = ['caravan', 'motorhome', 'campervan'];
        $taxonomies = ['listing-location', 'listing-make-model', 'listing-category'];

        if ($current_screen) {
            // 1. Highlight Post Type Menus
            // Regardless if we are on 'edit.php' (list) or 'post-new.php' (add), we want the list menu active
            if (in_array($current_screen->post_type, $cpts)) {
                return 'edit.php?post_type=' . $current_screen->post_type;
            }

            // 2. Highlight Taxonomy Menus
            // We need to match the EXACT slug registered in 'add_submenu_page'
            // In register_admin_page(), we added '&post_type=caravan' to the URL.
            if (in_array($current_screen->taxonomy, $taxonomies)) {
                return 'edit-tags.php?taxonomy=' . $current_screen->taxonomy . '&post_type=caravan';
            }
        }

        return $submenu_file;
    }

    /**
     * Force the PARENT menu styling to be active (Visual Fix)
     */
    public function fix_parent_css_highlight()
    {
        global $current_screen;
        $cpts = ['caravan', 'motorhome', 'campervan'];
        $taxonomies = ['listing-location', 'listing-make-model', 'listing-category'];

        // Check if we are on a screen related to our listing system
        if ($current_screen) {
            if (in_array($current_screen->post_type, $cpts) || in_array($current_screen->taxonomy, $taxonomies)) {
        ?>
                <style>
                    /* Highlight the parent menu background/text */
                    .toplevel_page_couplands-listings.toplevel_page_couplands-listings>a.menu-top {
                        background: #2271b1 !important;
                        color: #fff !important;
                    }

                    /* Ensure submenu arrow is white */
                    .toplevel_page_couplands-listings.toplevel_page_couplands-listings>a.menu-top:after {
                        color: #fff !important;
                    }

                    .toplevel_page_couplands-listings.toplevel_page_couplands-listings .wp-submenu.wp-submenu.wp-submenu {
                        position: static !important;
                        min-width: unset !important;
                        width: auto !important;
                        margin: 0 !important;
                        border: none !important
                    }
                </style>
                <script>
                    jQuery(document).ready(function($) {
                        // Force WordPress menu JS to treat this as open
                        $('.toplevel_page_couplands-listings').removeClass('wp-not-current-submenu').addClass('wp-has-current-submenu wp-menu-open');
                    });
                </script>
        <?php
            }
        }
    }

    /**
     * Register the Admin Menu Structure
     */
    public function register_admin_page()
    {
        // 1. Create the Main "Listings" Menu
        // We use 'manage_options' as cap, and point it to the first submenu 'couplands-listings'
        add_menu_page(
            'Listings',
            'Listings',
            'manage_options',
            'couplands-listings',
            null, // No callback, because we will direct traffic via submenus
            'dashicons-list-view',
            6
        );

        // 2. Add Submenus for each Post Type
        // We manually add the links to the post type edit screens
        $post_types = [
            'caravan'   => 'Caravans',
            'motorhome' => 'Motorhomes',
            'campervan' => 'Campervans'
        ];

        foreach ($post_types as $slug => $label) {
            // Add as submenu under "Listings"
            add_submenu_page(
                'couplands-listings',  // Parent slug
                $label,                // Page title
                $label,                // Menu title
                'edit_posts',          // Capability
                'edit.php?post_type=' . $slug // Menu slug (Direct link to post type)
            );

            // Remove the original top-level menu item for this post type
            remove_menu_page('edit.php?post_type=' . $slug);
        }

        // 3. Add Taxonomy Submenus
        // Note: For highlighting to work correctly on these submenus, we explicitly attach
        // them to 'post_type=caravan' in the query string. The highlighter function below looks for this.

        add_submenu_page(
            'couplands-listings',
            'Locations',
            'Locations',
            'manage_categories', // Capability
            'edit-tags.php?taxonomy=listing-location&post_type=caravan'
        );

        add_submenu_page(
            'couplands-listings',
            'Makes & Models',
            'Makes & Models',
            'manage_categories', // Capability
            'edit-tags.php?taxonomy=listing-make-model&post_type=caravan'
        );

        add_submenu_page(
            'couplands-listings',
            'Listing Categories',
            'Categories',
            'manage_categories', // Capability
            'edit-tags.php?taxonomy=listing-category&post_type=caravan'
        );

        // --- NEW: Settings Page (Renamed from Filter Settings) ---
        add_submenu_page(
            'couplands-listings',
            'Listing Settings', // Page Title
            'Settings',         // Menu Title (Renamed)
            'manage_options',
            'couplands-filter-settings',
            array($this, 'render_filter_settings_page')
        );

        // 4. Add the "Import Listings" Page under "Listings"
        add_submenu_page(
            'couplands-listings',
            'Import Listings',
            'Import Listings',
            'manage_options',
            'couplands-importer',
            array($this, 'render_importer_page')
        );

        // 5. Fix: The add_menu_page creates a duplicate first submenu item named "Listings".
        // We remove it so the first item is "Caravans".
        global $submenu;
        if (isset($submenu['couplands-listings'])) {
            // The auto-generated submenu has the same slug as the parent
            foreach ($submenu['couplands-listings'] as $key => $item) {
                if ($item[2] === 'couplands-listings') {
                    unset($submenu['couplands-listings'][$key]);
                    break;
                }
            }
        }
    }

    /**
     * Register Settings (Fix: Use distinct groups per tab to prevent overwrites)
     */
    public function register_plugin_settings()
    {
        // Filters
        foreach (['caravan', 'motorhome', 'campervan', 'product'] as $type) {
            register_setting('couplands_filters_' . $type . '_group', 'couplands_filters_' . $type);
        }

        // General Archive Page Settings
        register_setting('couplands_general_settings', 'couplands_archive_page_caravan');
        register_setting('couplands_general_settings', 'couplands_archive_page_motorhome');
        register_setting('couplands_general_settings', 'couplands_archive_page_campervan');

        // NEW: Single Post Template Settings
        register_setting('couplands_general_settings', 'couplands_child_template_caravan');
        register_setting('couplands_general_settings', 'couplands_child_template_motorhome');
        register_setting('couplands_general_settings', 'couplands_child_template_campervan');

        // NEW: Model Grid Template Settings
        register_setting('couplands_general_settings', 'couplands_model_grid_item_template');

        // --- ADDED: Meta Fields Settings ---
        register_setting('couplands_meta_fields_group', 'couplands_meta_fields_config');
    }

    /**
     * Render the Filter Settings Page (Backend UI)
     */
    public function render_filter_settings_page()
    {
        // Enqueue media for styling consistency
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // --- NEW: Enqueue Sortable for Drag and Drop ---
        wp_enqueue_script('jquery-ui-sortable');

        $tabs = [
            'general'     => 'General Settings',
            'caravan'     => 'Caravan Filters',
            'motorhome'   => 'Motorhome Filters',
            'campervan'   => 'Campervan Filters',
            'product'     => 'Product Filters',
            'meta_fields' => 'Meta Fields Display' // --- ADDED: Meta Field Tab ---
        ];

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

        ?>
        <div class="wrap">
            <h1>Listing Settings</h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label): ?>
                    <a href="?page=couplands-filter-settings&tab=<?php echo $slug; ?>" class="nav-tab <?php echo $active_tab == $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                if ($active_tab === 'general') {
                    settings_fields('couplands_general_settings');
                    $this->render_general_settings_tab();
                } elseif ($active_tab === 'meta_fields') {
                    settings_fields('couplands_meta_fields_group');
                    $this->render_meta_fields_tab();
                } else {
                    settings_fields('couplands_filters_' . $active_tab . '_group');
                    $this->render_filter_settings_tab($active_tab);
                }
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Renders the Meta Fields Backend Tab UI. 
     * Handles the display of a sortable drag-and-drop table populated dynamically from specified ACF Field Groups.
     * Incorporates backend settings to establish rendering order, label overrides, price formatting, and exclusion visibility rules.
     */
    private function render_meta_fields_tab()
    {
        if (!function_exists('acf_get_fields')) {
            echo '<p>Error: ACF plugin is not active or fields cannot be loaded. Ensure ACF is installed.</p>';
            return;
        }

        // Establish the target ACF groups corresponding to the client requirements
        $target_groups = [541, 2642, 2443];
        $acf_fields = [];

        // Iterate targeted ACF Groups to harvest all relevant metadata fields
        foreach ($target_groups as $group_id) {
            $fields = acf_get_fields($group_id);
            if ($fields) {
                foreach ($fields as $field) {
                    $acf_fields[$field['name']] = $field;
                }
            }
        }

        // Retrieve existing database configuration settings
        $saved_config = get_option('couplands_meta_fields_config', []);

        // Logic reconciliation: Merges actual ACF fields with the saved array config to safely preserve user-defined state
        $display_fields = [];
        if (!empty($saved_config)) {
            foreach ($saved_config as $saved_field) {
                $name = $saved_field['name'];
                if (isset($acf_fields[$name])) {
                    $display_fields[] = array_merge($acf_fields[$name], [
                        'visible'      => isset($saved_field['visible']) ? $saved_field['visible'] : 0,
                        'custom_label' => isset($saved_field['custom_label']) ? $saved_field['custom_label'] : '',
                        'is_price'     => isset($saved_field['is_price']) ? $saved_field['is_price'] : 0,
                    ]);
                    unset($acf_fields[$name]); // remove so we can append unassigned remainders
                }
            }
        }

        // Append any new or remaining ACF fields that lack config visibility declarations
        foreach ($acf_fields as $name => $field) {
            $display_fields[] = array_merge($field, [
                'visible'      => 1, // Set default visibility fallback to True
                'custom_label' => '',
                'is_price'     => 0
            ]);
        }

    ?>
        <div style="margin-top: 20px;">
            <h3>Meta Output Layout Builder</h3>
            <p>Drag and drop the fields below to intuitively establish their render hierarchy on the front end. Utilize the checkboxes to strictly exclude certain metrics from appearing dynamically via the <code>[listing_meta_fields]</code> shortcode, apply custom labels, or format specific items as GBP prices.</p>

            <table class="widefat striped" id="meta-fields-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Sort</th>
                        <th>Field Label (Default)</th>
                        <th>Custom Label Override</th>
                        <th>Field Name (Reference Key)</th>
                        <th style="width: 100px; text-align: center;">Is Price?</th>
                        <th style="width: 150px; text-align: center;">Show on Frontend</th>
                    </tr>
                </thead>
                <tbody id="meta-fields-rows">
                    <?php foreach ($display_fields as $index => $field) : ?>
                        <tr style="background: #fff; cursor: move;">
                            <td class="drag-handle" style="text-align: center; vertical-align: middle;">
                                <span class="dashicons dashicons-menu" style="color: #999;"></span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($field['label']); ?></strong>
                                <input type="hidden" name="couplands_meta_fields_config[<?php echo $index; ?>][label]" value="<?php echo esc_attr($field['label']); ?>">
                            </td>
                            <td>
                                <input type="text" class="regular-text" name="couplands_meta_fields_config[<?php echo $index; ?>][custom_label]" value="<?php echo esc_attr(isset($field['custom_label']) ? $field['custom_label'] : ''); ?>" placeholder="Optional label override">
                            </td>
                            <td>
                                <code><?php echo esc_html($field['name']); ?></code>
                                <input type="hidden" name="couplands_meta_fields_config[<?php echo $index; ?>][name]" value="<?php echo esc_attr($field['name']); ?>">
                            </td>
                            <td style="text-align: center;">
                                <input type="hidden" name="couplands_meta_fields_config[<?php echo $index; ?>][is_price]" value="0">
                                <input type="checkbox" name="couplands_meta_fields_config[<?php echo $index; ?>][is_price]" value="1" <?php checked(isset($field['is_price']) ? $field['is_price'] : 0, 1); ?>>
                            </td>
                            <td style="text-align: center;">
                                <input type="hidden" name="couplands_meta_fields_config[<?php echo $index; ?>][visible]" value="0">
                                <input type="checkbox" name="couplands_meta_fields_config[<?php echo $index; ?>][visible]" value="1" <?php checked(isset($field['visible']) ? $field['visible'] : 1, 1); ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Initialize jQuery UI drag/drop sortable interface
                $('#meta-fields-rows').sortable({
                    handle: '.drag-handle',
                    axis: 'y',
                    helper: function(e, tr) {
                        var $originals = tr.children();
                        var $helper = tr.clone();
                        $helper.children().each(function(index) {
                            $(this).width($originals.eq(index).width());
                        });
                        return $helper;
                    },
                    update: function() {
                        // Dynamically re-index structural arrays to guarantee payload sequence alignment when storing layout in db
                        $('#meta-fields-rows tr').each(function(index) {
                            $(this).find('input, select, textarea').each(function() {
                                var name = $(this).attr('name');
                                if (name) {
                                    var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                                    $(this).attr('name', newName);
                                }
                            });
                        });
                    }
                });
            });
        </script>
    <?php
    }

    private function render_general_settings_tab()
    {
        // Fetch Elementor Templates for Dropdown
        $elementor_templates = $this->get_elementor_templates();
    ?>
        <h3>Archive Page Mapping</h3>
        <p>Since the default archives are disabled, select the WordPress pages that will display listings for each type.</p>
        <table class="form-table">
            <tr>
                <th scope="row">Caravan Archive Page</th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'couplands_archive_page_caravan',
                        'selected' => get_option('couplands_archive_page_caravan'),
                        'show_option_none' => 'Select Page',
                        'option_none_value' => 0
                    ));
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Motorhome Archive Page</th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'couplands_archive_page_motorhome',
                        'selected' => get_option('couplands_archive_page_motorhome'),
                        'show_option_none' => 'Select Page',
                        'option_none_value' => 0
                    ));
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Campervan Archive Page</th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'couplands_archive_page_campervan',
                        'selected' => get_option('couplands_archive_page_campervan'),
                        'show_option_none' => 'Select Page',
                        'option_none_value' => 0
                    ));
                    ?>
                </td>
            </tr>
        </table>

        <hr>

        <h3>First Level Child Page Templates</h3>
        <p>Select an Elementor Template to be used for <strong>direct child pages</strong> of the selected Archive Pages above.</p>
        <table class="form-table">
            <tr>
                <th scope="row">Caravan Child Page Template</th>
                <td>
                    <select name="couplands_child_template_caravan">
                        <?php foreach ($elementor_templates as $id => $title) : ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(get_option('couplands_child_template_caravan'), $id); ?>>
                                <?php echo esc_html($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Motorhome Child Page Template</th>
                <td>
                    <select name="couplands_child_template_motorhome">
                        <?php foreach ($elementor_templates as $id => $title) : ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(get_option('couplands_child_template_motorhome'), $id); ?>>
                                <?php echo esc_html($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Campervan Child Page Template</th>
                <td>
                    <select name="couplands_child_template_campervan">
                        <?php foreach ($elementor_templates as $id => $title) : ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(get_option('couplands_child_template_campervan'), $id); ?>>
                                <?php echo esc_html($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <hr>

        <h3>Model/Range Grid Settings</h3>
        <p>Settings for the grid displayed via <code>[listing_model_grid]</code> on child pages.</p>
        <table class="form-table">
            <tr>
                <th scope="row">Model Grid Item Template</th>
                <td>
                    <select name="couplands_model_grid_item_template">
                        <?php foreach ($elementor_templates as $id => $title) : ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected(get_option('couplands_model_grid_item_template'), $id); ?>>
                                <?php echo esc_html($title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Select the Elementor Template used for individual items in the model grid.</p>
                </td>
            </tr>
        </table>
    <?php
    }

    private function render_filter_settings_tab($active_tab)
    {
        $option_name = 'couplands_filters_' . $active_tab;
        $filters = get_option($option_name, []);
    ?>
        <p>Select which fields should appear in the frontend filter for each post type. </p>
        <p><strong>Note:</strong> If you add the taxonomy <code>listing-make-model</code>, the system will automatically enable the dependent "Make -> Model" logic.</p>

        <style>
            .drag-handle {
                cursor: move;
                color: #ccc;
                width: 30px;
                text-align: center;
            }

            .drag-handle:hover {
                color: #333;
            }

            .ui-sortable-helper {
                background: #fff;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                display: table;
            }

            .ui-state-highlight {
                height: 40px;
                background: #f0f0f1;
                border: 1px dashed #ccc;
            }
        </style>

        <table class="widefat" id="filter-table">
            <thead>
                <tr>
                    <th style="width: 40px;">Sort</th>
                    <th>Type</th>
                    <th>Key / Slug</th>
                    <th>Label</th>
                    <th>Input Type</th>
                    <th>Default Option Label</th>
                    <th>URL Param / Input Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="filter-rows">
                <?php
                if (!empty($filters)) {
                    foreach ($filters as $index => $filter) {
                        $this->render_filter_row($index, $filter, $option_name);
                    }
                }
                ?>
            </tbody>
        </table>

        <p>
            <button type="button" class="button" id="add-row">Add Filter</button>
        </p>

        <script>
            jQuery(document).ready(function($) {
                let rowCount = <?php echo !empty($filters) ? count($filters) : 0; ?>;
                const optionName = "<?php echo $option_name; ?>";

                // Initialize Sortable
                $('#filter-rows').sortable({
                    handle: '.drag-handle',
                    placeholder: 'ui-state-highlight',
                    axis: 'y',
                    helper: function(e, tr) {
                        var $originals = tr.children();
                        var $helper = tr.clone();
                        $helper.children().each(function(index) {
                            // Set helper cell sizes to match original sizes
                            $(this).width($originals.eq(index).width());
                        });
                        return $helper;
                    },
                    update: function(event, ui) {
                        reindexRows();
                    }
                });

                // Helper to re-index input names after sorting so order is saved correctly
                function reindexRows() {
                    $('#filter-rows tr').each(function(index) {
                        $(this).find('input, select').each(function() {
                            const name = $(this).attr('name');
                            if (name) {
                                // Replace the first occurrence of [number] with [new_index]
                                const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', newName);
                            }
                        });
                    });
                }

                $('#add-row').click(function() {
                    const html = `
                            <tr>
                                <td class="drag-handle"><span class="dashicons dashicons-menu"></span></td>
                                <td>
                                    <select name="${optionName}[${rowCount}][type]">
                                        <option value="meta">Meta Key</option>
                                        <option value="taxonomy">Taxonomy</option>
                                    </select>
                                </td>
                                <td><input type="text" name="${optionName}[${rowCount}][slug]" placeholder="e.g. price or listing-make-model" class="regular-text"></td>
                                <td><input type="text" name="${optionName}[${rowCount}][label]" placeholder="Label" class="regular-text"></td>
                                <td>
                                    <select name="${optionName}[${rowCount}][input_type]">
                                        <option value="select">Select Dropdown</option>
                                        <option value="checkbox">Checkboxes</option>
                                        <option value="radio">Radio Buttons</option>
                                    </select>
                                </td>
                                <td><input type="text" name="${optionName}[${rowCount}][default_label]" placeholder="e.g. Any Price" class="regular-text"></td>
                                <td><input type="text" name="${optionName}[${rowCount}][custom_name]" placeholder="e.g. min_price" class="regular-text"></td>
                                <td><button type="button" class="button remove-row">Remove</button></td>
                            </tr>
                        `;
                    $('#filter-rows').append(html);
                    rowCount++;
                    reindexRows(); // Ensure index is correct if rows were deleted previously
                });

                $(document).on('click', '.remove-row', function() {
                    $(this).closest('tr').remove();
                    reindexRows();
                });
            });
        </script>
    <?php
    }

    private function render_filter_row($index, $data, $option_name)
    {
    ?>
        <tr>
            <td class="drag-handle"><span class="dashicons dashicons-menu"></span></td>
            <td>
                <select name="<?php echo $option_name; ?>[<?php echo $index; ?>][type]">
                    <option value="meta" <?php selected($data['type'], 'meta'); ?>>Meta Key</option>
                    <option value="taxonomy" <?php selected($data['type'], 'taxonomy'); ?>>Taxonomy</option>
                </select>
            </td>
            <td><input type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][slug]" value="<?php echo esc_attr($data['slug']); ?>" class="regular-text"></td>
            <td><input type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][label]" value="<?php echo esc_attr($data['label']); ?>" class="regular-text"></td>
            <td>
                <select name="<?php echo $option_name; ?>[<?php echo $index; ?>][input_type]">
                    <option value="select" <?php selected($data['input_type'], 'select'); ?>>Select Dropdown</option>
                    <option value="checkbox" <?php selected($data['input_type'], 'checkbox'); ?>>Checkboxes</option>
                    <option value="radio" <?php selected($data['input_type'], 'radio'); ?>>Radio Buttons</option>
                </select>
            </td>
            <td><input type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][default_label]" value="<?php echo isset($data['default_label']) ? esc_attr($data['default_label']) : ''; ?>" class="regular-text"></td>
            <td><input type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][custom_name]" value="<?php echo isset($data['custom_name']) ? esc_attr($data['custom_name']) : ''; ?>" class="regular-text"></td>
            <td><button type="button" class="button remove-row">Remove</button></td>
        </tr>
    <?php
    }

    /* ==========================================================================
       NEW: PAGE FILTER BUILDER META BOX (Updated)
       ========================================================================== */

    public function add_page_filter_builder_meta_box()
    {
        add_meta_box(
            'couplands_page_filter_builder',
            'Listing Filter Builder',
            array($this, 'render_page_filter_builder'),
            'page', // Available on all Pages
            'normal',
            'high'
        );
    }

    /**
     * Forces the global WordPress query to recognize a specific Taxonomy Term
     * as the current queried object.
     * * This effectively mocks a Taxonomy Archive page.
     *
     * @param int     $term_id  The ID of the term to set.
     * @param string $taxonomy The taxonomy slug (e.g., 'category', 'post_tag', 'my_custom_tax').
     * @return bool            True on success, False if term is invalid.
     */
    public function force_set_term_object(int $term_id, string $taxonomy): bool
    {
        global $wp_query;

        // 1. Verify the term exists and is valid
        $term = get_term($term_id, $taxonomy);

        if (! $term || is_wp_error($term)) {
            // Log error or handle gracefully
            return false;
        }

        // 2. Set the Queried Object
        $wp_query->queried_object    = $term;
        $wp_query->queried_object_id = (int) $term_id;

        // 3. Reset Global Conditionals (Clean slate)
        // We must ensure WordPress doesn't think it's still on a Page or Single Post
        $wp_query->is_singular = false;
        $wp_query->is_page     = false;
        $wp_query->is_single   = false;
        $wp_query->is_home     = false;
        $wp_query->is_404      = false;

        // 4. Set Archive Conditionals
        $wp_query->is_archive = true;

        // 5. Set Specific Taxonomy Conditionals
        // WordPress handles Categories and Tags slightly differently than custom taxes
        if ('category' === $taxonomy) {
            $wp_query->is_category = true;
            $wp_query->is_tax      = false; // Standard categories are technically not is_tax() in some contexts, but usually safe.
            $wp_query->is_tag      = false;
        } elseif ('post_tag' === $taxonomy) {
            $wp_query->is_tag      = true;
            $wp_query->is_category = false;
            $wp_query->is_tax      = false;
        } else {
            // Custom Taxonomy
            $wp_query->is_tax      = true;
            $wp_query->is_category = false;
            $wp_query->is_tag      = false;
        }

        // 6. Optional: Update the 'tax_query' var allows WP_Query to fetch associated posts
        // if you plan to run the main loop after this change.
        $wp_query->set('taxonomy', $taxonomy);
        $wp_query->set('term', $term->slug);
        $wp_query->set('term_id', $term_id);

        return true;
    }

    public function render_page_filter_builder($post)
    {
        wp_nonce_field('save_couplands_page_filters', 'couplands_page_filters_nonce');
        $filters = get_post_meta($post->ID, '_couplands_page_filters', true);

        // Sortable script for page meta box if needed, or simple append
        wp_enqueue_script('jquery-ui-sortable');
    ?>
        <style>
            #page-filter-list {
                margin-bottom: 10px;
            }

            .page-filter-row {
                display: flex;
                gap: 10px;
                margin-bottom: 5px;
                align-items: center;
                background: #fff;
                padding: 10px;
                border: 1px solid #ddd;
                cursor: move;
            }

            .page-filter-row input,
            .page-filter-row select {
                width: 100%;
            }

            .col-drag {
                flex: 0 0 30px;
                text-align: center;
                color: #ccc;
            }

            .col-type {
                flex: 0 0 100px;
            }

            .col-key {
                flex: 1;
            }

            .col-op {
                flex: 0 0 80px;
            }

            .col-val {
                flex: 1;
            }

            .col-url {
                flex: 1;
            }

            .col-tax-field {
                flex: 0 0 100px;
            }

            .col-act {
                flex: 0 0 60px;
                text-align: right;
            }
        </style>

        <div id="page-filter-container">
            <p>Define filters to restrict which listings are considered for the <code>[listing_model_grid]</code> on this page.</p>
            <div id="page-filter-list">
                <?php
                if (!empty($filters) && is_array($filters)) {
                    foreach ($filters as $index => $filter) {
                        $this->render_page_filter_single_row($index, $filter);
                    }
                }
                ?>
            </div>
            <button type="button" class="button button-primary" id="add-page-filter-row">Add Condition</button>
        </div>

        <script>
            jQuery(document).ready(function($) {
                let rowCount = <?php echo !empty($filters) ? count($filters) : 0; ?>;

                $('#page-filter-list').sortable({
                    handle: '.col-drag',
                    axis: 'y',
                    update: function() {
                        reindexPageRows();
                    }
                });

                function reindexPageRows() {
                    $('#page-filter-list .page-filter-row').each(function(index) {
                        $(this).find('input, select').each(function() {
                            const name = $(this).attr('name');
                            if (name) {
                                const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', newName);
                            }
                        });
                    });
                }

                // Helper to hide tax field if meta is selected
                function checkTaxFieldVisibility($row) {
                    var type = $row.find('select.page-filter-type').val();
                    if (type === 'meta') {
                        $row.find('.col-tax-field').hide();
                    } else {
                        $row.find('.col-tax-field').show();
                    }
                }

                // Initial check
                $('.page-filter-row').each(function() {
                    checkTaxFieldVisibility($(this));
                });

                // Change event
                $(document).on('change', 'select.page-filter-type', function() {
                    checkTaxFieldVisibility($(this).closest('.page-filter-row'));
                });

                $('#add-page-filter-row').on('click', function() {
                    const html = `
                        <div class="page-filter-row">
                            <div class="col-drag"><span class="dashicons dashicons-menu"></span></div>
                            <div class="col-type">
                                <label>Type</label>
                                <select name="page_filters[${rowCount}][type]" class="page-filter-type">
                                    <option value="meta">Meta</option>
                                    <option value="taxonomy">Taxonomy</option>
                                </select>
                            </div>
                            <div class="col-key">
                                <label>Key / Slug</label>
                                <input type="text" name="page_filters[${rowCount}][key]" placeholder="e.g. year or listing-make-model">
                            </div>
                            <div class="col-op">
                                <label>Compare</label>
                                <select name="page_filters[${rowCount}][compare]">
                                    <option value="=">=</option>
                                    <option value="!=">!=</option>
                                    <option value="LIKE">LIKE</option>
                                    <option value="IN">IN</option>
                                </select>
                            </div>
                            <div class="col-val">
                                <label>Value</label>
                                <input type="text" name="page_filters[${rowCount}][value]" placeholder="e.g. 2025 or term_id">
                            </div>
                            <div class="col-url">
                                <label>URL Param</label>
                                <input type="text" name="page_filters[${rowCount}][url_param]" placeholder="e.g. vehicle_year">
                            </div>
                            <div class="col-tax-field">
                                <label>Tax Field</label>
                                <select name="page_filters[${rowCount}][tax_field]">
                                    <option value="term_id">Term ID</option>
                                    <option value="slug">Term Slug</option>
                                </select>
                            </div>
                            <div class="col-act">
                                <button type="button" class="button remove-page-filter">X</button>
                            </div>
                        </div>
                    `;
                    var $newRow = $(html);
                    $('#page-filter-list').append($newRow);
                    checkTaxFieldVisibility($newRow); // Run check on new row
                    rowCount++;
                    reindexPageRows();
                });

                $(document).on('click', '.remove-page-filter', function() {
                    $(this).closest('.page-filter-row').remove();
                    reindexPageRows();
                });
            });
        </script>
    <?php
    }

    private function render_page_filter_single_row($index, $data)
    {
        $type = isset($data['type']) ? esc_attr($data['type']) : 'meta';
        $key = isset($data['key']) ? esc_attr($data['key']) : '';
        $value = isset($data['value']) ? esc_attr($data['value']) : '';
        $compare = isset($data['compare']) ? esc_attr($data['compare']) : '=';
        $url_param = isset($data['url_param']) ? esc_attr($data['url_param']) : '';
        $tax_field = isset($data['tax_field']) ? esc_attr($data['tax_field']) : 'term_id';
    ?>
        <div class="page-filter-row">
            <div class="col-drag"><span class="dashicons dashicons-menu"></span></div>
            <div class="col-type">
                <label>Type</label>
                <select name="page_filters[<?php echo $index; ?>][type]" class="page-filter-type">
                    <option value="meta" <?php selected($type, 'meta'); ?>>Meta</option>
                    <option value="taxonomy" <?php selected($type, 'taxonomy'); ?>>Taxonomy</option>
                </select>
            </div>
            <div class="col-key">
                <label>Key / Slug</label>
                <input type="text" name="page_filters[<?php echo $index; ?>][key]" value="<?php echo $key; ?>" placeholder="e.g. year">
            </div>
            <div class="col-op">
                <label>Compare</label>
                <select name="page_filters[<?php echo $index; ?>][compare]">
                    <option value="=" <?php selected($compare, '='); ?>>=</option>
                    <option value="!=" <?php selected($compare, '!='); ?>>!=</option>
                    <option value="LIKE" <?php selected($compare, 'LIKE'); ?>>LIKE</option>
                    <option value="IN" <?php selected($compare, 'IN'); ?>>IN</option>
                </select>
            </div>
            <div class="col-val">
                <label>Value</label>
                <input type="text" name="page_filters[<?php echo $index; ?>][value]" value="<?php echo $value; ?>" placeholder="e.g. 2025">
            </div>
            <div class="col-url">
                <label>URL Param</label>
                <input type="text" name="page_filters[<?php echo $index; ?>][url_param]" value="<?php echo $url_param; ?>" placeholder="e.g. vehicle_year">
            </div>
            <div class="col-tax-field">
                <label>Tax Field</label>
                <select name="page_filters[<?php echo $index; ?>][tax_field]">
                    <option value="term_id" <?php selected($tax_field, 'term_id'); ?>>Term ID</option>
                    <option value="slug" <?php selected($tax_field, 'slug'); ?>>Term Slug</option>
                </select>
            </div>
            <div class="col-act">
                <button type="button" class="button remove-page-filter">X</button>
            </div>
        </div>
    <?php
    }

    public function save_page_filter_builder_meta($post_id)
    {
        if (!isset($_POST['couplands_page_filters_nonce']) || !wp_verify_nonce($_POST['couplands_page_filters_nonce'], 'save_couplands_page_filters')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['page_filters']) && is_array($_POST['page_filters'])) {
            $filters = array_values($_POST['page_filters']); // Reindex array
            // Sanitize
            $clean_filters = [];
            foreach ($filters as $f) {
                if (!empty($f['key'])) {
                    $clean_filters[] = [
                        'type'      => sanitize_text_field($f['type']),
                        'key'       => sanitize_text_field($f['key']),
                        'value'     => sanitize_text_field($f['value']),
                        'compare'   => sanitize_text_field($f['compare']),
                        'url_param' => sanitize_text_field($f['url_param']),
                        'tax_field' => sanitize_text_field($f['tax_field']),
                    ];
                }
            }
            update_post_meta($post_id, '_couplands_page_filters', $clean_filters);
        } else {
            delete_post_meta($post_id, '_couplands_page_filters');
        }
    }

    /**
     * Render the Importer Page and Handle Submission
     */
    public function render_importer_page()
    {
        // Enqueue script for batch processing
        wp_enqueue_script('jquery');
    ?>
        <div class="wrap">
            <h1>Import Listings (Live Update)</h1>
            <p>Upload a CSV file to import listings. Ensure your CSV headers match the required format.</p>

            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h3>CSV Column Format Guide:</h3>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><code>post_title</code> (Required): Name of the listing.</li>
                    <li><code>post_type</code> (Required): caravan, motorhome, or campervan.</li>
                    <li><code>images</code>: Comma separated URLs or IDs. First image = Featured. Rest = Gallery.</li>
                    <li><code>make</code>: Parent term (e.g., Swift).</li>
                    <li><code>model</code>: Child term (e.g., Challenger).</li>
                    <li><code>tax:listing-location</code>: Location terms.</li>
                    <li><code>meta:key_name</code>: Dynamic meta data (e.g., <code>meta:interior_features</code>).</li>
                </ul>

                <hr>

                <div id="couplands-import-ui">
                    <input type="file" id="csv_file_input" accept=".csv" />
                    <button id="start-import-btn" class="button button-primary">Start Import</button>
                    <div id="import-progress-bar" style="width: 100%; background: #ddd; height: 20px; margin-top: 15px; display:none;">
                        <div id="import-progress-fill" style="width: 0%; background: #2271b1; height: 100%;"></div>
                    </div>
                    <div id="import-log" style="background: #f0f0f1; padding: 10px; margin-top: 15px; border: 1px solid #ccc; height: 300px; overflow-y: scroll; display:none;">
                        <p><strong>Import Log:</strong></p>
                    </div>
                </div>

            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#start-import-btn').on('click', function(e) {
                    e.preventDefault();
                    var file_data = $('#csv_file_input').prop('files')[0];
                    if (!file_data) {
                        alert("Please select a file.");
                        return;
                    }

                    // UI Reset
                    $('#import-log').show().append('<p>Uploading file...</p>');
                    $('#import-progress-bar').show();
                    $('#start-import-btn').prop('disabled', true);

                    // Step 1: Upload File
                    var form_data = new FormData();
                    form_data.append('file', file_data);
                    form_data.append('action', 'couplands_import_upload');
                    form_data.append('security', '<?php echo wp_create_nonce("couplands_import_ajax"); ?>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        contentType: false,
                        processData: false,
                        data: form_data,
                        success: function(response) {
                            if (response.success) {
                                var filePath = response.data.path;
                                var totalRows = response.data.count;
                                $('#import-log').append('<p>File uploaded. Found ' + totalRows + ' rows. Starting batch process...</p>');
                                processBatch(filePath, 0, totalRows);
                            } else {
                                $('#import-log').append('<p style="color:red">Error: ' + response.data + '</p>');
                                $('#start-import-btn').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#import-log').append('<p style="color:red">Upload failed.</p>');
                        }
                    });
                });

                // Step 2: Recursive Batch Processing
                function processBatch(filePath, currentIndex, totalRows) {
                    if (currentIndex >= totalRows) {
                        $('#import-log').append('<p style="color:green; font-weight:bold;">Import Complete!</p>');
                        $('#start-import-btn').prop('disabled', false);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'couplands_import_process',
                            file_path: filePath,
                            row_index: currentIndex,
                            security: '<?php echo wp_create_nonce("couplands_import_ajax"); ?>'
                        },
                        success: function(response) {
                            var percent = Math.round(((currentIndex + 1) / totalRows) * 100);
                            $('#import-progress-fill').css('width', percent + '%');

                            if (response.success) {
                                $('#import-log').append('<p>' + response.data.message + '</p>');
                            } else {
                                $('#import-log').append('<p style="color:red;">Row ' + (currentIndex + 1) + ' Failed: ' + response.data + '</p>');
                            }

                            // Scroll to bottom
                            var logDiv = document.getElementById("import-log");
                            logDiv.scrollTop = logDiv.scrollHeight;

                            // Next Row
                            processBatch(filePath, currentIndex + 1, totalRows);
                        },
                        error: function() {
                            $('#import-log').append('<p style="color:red">AJAX Error on row ' + (currentIndex + 1) + '. Retrying...</p>');
                            // Retry after 2 seconds
                            setTimeout(function() {
                                processBatch(filePath, currentIndex, totalRows);
                            }, 2000);
                        }
                    });
                }
            });
        </script>
    <?php
    }

    /**
     * AJAX 1: Upload CSV and Count Rows
     */
    public function ajax_import_upload()
    {
        check_ajax_referer('couplands_import_ajax', 'security');

        if (!current_user_can('manage_options') || empty($_FILES['file'])) {
            wp_send_json_error('Permission denied or no file.');
        }

        // Upload file to temp dir
        $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }

        $file_path = $upload['file'];

        // Count rows (minus header)
        $line_count = 0;
        $handle = fopen($file_path, "r");
        while (!feof($handle)) {
            $line = fgets($handle);
            if (!empty(trim($line))) $line_count++;
        }
        fclose($handle);

        // Return success
        wp_send_json_success([
            'path' => $file_path,
            'count' => $line_count - 1 // Minus header
        ]);
    }

    /**
     * AJAX 2: Process Single Row
     */
    public function ajax_import_process()
    {
        check_ajax_referer('couplands_import_ajax', 'security');

        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $row_index = isset($_POST['row_index']) ? intval($_POST['row_index']) : 0;

        if (!file_exists($file_path)) wp_send_json_error('File missing.');

        // Use SplFileObject to seek directly to the line
        $file = new SplFileObject($file_path);
        $file->setFlags(SplFileObject::READ_CSV);

        // Get Header
        $file->seek(0);
        $headers = array_map('trim', array_map('strtolower', $file->current()));

        // Get Row (+1 because index 0 is data row 1, but file line 0 is header)
        $file->seek($row_index + 1);

        if (!$file->valid()) {
            wp_send_json_error('EOF');
        }

        $data = $file->current();

        // Validate Data match
        if (count($headers) !== count($data)) {
            if (empty(implode('', $data))) wp_send_json_error('Empty Row skipped.');
            wp_send_json_error('Column mismatch.');
        }

        $row = array_combine($headers, $data);

        // Process
        $result_title = $this->process_listing_row($row);

        wp_send_json_success(['message' => "Row " . ($row_index + 1) . ": Imported '" . $result_title . "' successfully."]);
    }

    /**
     * Logic to create/update a single listing from CSV row
     * @param array $row Associative array of column_name => value
     * @return string Title of post
     */
    private function process_listing_row($row)
    {
        $title = isset($row['post_title']) ? sanitize_text_field($row['post_title']) : 'Untitled';
        $post_type = isset($row['post_type']) ? sanitize_text_field($row['post_type']) : 'caravan';

        $post_data = array(
            'post_title'   => $title,
            'post_type'    => $post_type,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) return "Error creating post";

        // 2. Process Meta and Taxonomies
        $make = '';
        $model = '';

        foreach ($row as $key => $value) {
            if (empty($value)) continue;

            // Handle Dynamic Meta
            // FIX: Removed comma splitting to solve the truncated value bug.
            if (strpos($key, 'meta:') === 0) {
                $meta_key = substr($key, 5); // remove 'meta:'
                update_post_meta($post_id, $meta_key, sanitize_text_field($value));
            }

            // Handle Dynamic Taxonomies
            if (strpos($key, 'tax:') === 0) {
                $tax_name = substr($key, 4);
                $terms = array_map('trim', explode(',', $value)); // Taxonomies still split by comma
                wp_set_object_terms($post_id, $terms, $tax_name);
            }

            // NEW: Handle Images Column
            if ($key === 'images') {
                $this->handle_listing_images($post_id, $value);
            }

            if ($key === 'make') $make = sanitize_text_field($value);
            if ($key === 'model') $model = sanitize_text_field($value);
        }

        if (!empty($make)) {
            $this->set_hierarchical_terms($post_id, 'listing-make-model', $make, $model);
        }

        return $title;
    }

    /**
     * NEW: Handle Image Import (Featured + Gallery)
     */
    private function handle_listing_images($post_id, $image_string)
    {
        $images = array_map('trim', explode(',', $image_string));
        $gallery_ids = [];

        foreach ($images as $index => $img_source) {
            $attachment_id = 0;

            // Check if numeric ID (internal)
            if (is_numeric($img_source)) {
                if (wp_attachment_is_image($img_source)) {
                    $attachment_id = intval($img_source);
                }
            }
            // Check if URL (external)
            elseif (filter_var($img_source, FILTER_VALIDATE_URL)) {
                $attachment_id = $this->sideload_image_if_not_exists($img_source, $post_id);
            }

            if ($attachment_id) {
                // First image is Featured Image ONLY
                if ($index === 0) {
                    set_post_thumbnail($post_id, $attachment_id);
                } else {
                    // Subsequent images go to Gallery
                    $gallery_ids[] = $attachment_id;
                }
            }
        }

        // Save Gallery Meta
        if (!empty($gallery_ids)) {
            $existing = get_post_meta($post_id, '_listing_gallery_ids', true);
            update_post_meta($post_id, '_listing_gallery_ids', implode(',', $gallery_ids));
        }
    }

    /**
     * Helper to sideload image
     */
    private function sideload_image_if_not_exists($url, $post_id)
    {
        // 1. Check if image already exists in Media Library by URL
        $existing_id = attachment_url_to_postid($url);
        if ($existing_id) {
            return $existing_id;
        }

        // 2. If not, sideload it
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $id = media_sideload_image($url, $post_id, null, 'id');

        if (is_wp_error($id)) return 0;
        return $id;
    }

    /**
     * Helper: Handle Parent > Child Taxonomy Assignment
     * @param int $post_id
     * @param string $taxonomy
     * @param string $parent_term e.g., "Swift"
     * @param string $child_term e.g., "Challenger"
     */
    private function set_hierarchical_terms($post_id, $taxonomy, $parent_term, $child_term = '')
    {
        // 1. Handle Parent
        $parent_id = 0;
        $parent_check = term_exists($parent_term, $taxonomy);

        if ($parent_check) {
            $parent_id = is_array($parent_check) ? $parent_check['term_id'] : $parent_check;
        } else {
            $new_parent = wp_insert_term($parent_term, $taxonomy);
            if (!is_wp_error($new_parent)) {
                $parent_id = $new_parent['term_id'];
            }
        }

        $terms_to_assign = array((int)$parent_id);

        // 2. Handle Child (if exists)
        if (!empty($child_term) && $parent_id > 0) {
            $child_check = term_exists($child_term, $taxonomy, $parent_id);

            $child_id = 0;
            if ($child_check) {
                $child_id = is_array($child_check) ? $child_check['term_id'] : $child_check;
            } else {
                $new_child = wp_insert_term($child_term, $taxonomy, array('parent' => $parent_id));
                if (!is_wp_error($new_child)) {
                    $child_id = $new_child['term_id'];
                }
            }

            if ($child_id > 0) {
                $terms_to_assign[] = (int)$child_id;
            }
        }

        // 3. Assign Terms (Append to existing terms)
        wp_set_object_terms($post_id, $terms_to_assign, $taxonomy, true);
    }

    /* ==========================================================================
       LISTING GALLERY ADMIN FUNCTIONS (Backend Meta Box)
       ========================================================================== */

    /**
     * 1. Register the Meta Box for specific Post Types
     */
    public function listing_gallery_add_meta_box()
    {
        $screens = ['campervan', 'caravan', 'motorhome'];

        foreach ($screens as $screen) {
            add_meta_box(
                'listing_gallery_metabox',           // Unique ID
                'Listing Gallery',                   // Box Title
                array($this, 'listing_gallery_metabox_html'), // Content Callback
                $screen,                             // Post Type
                'side',                              // Context (side looks like Woo)
                'low'                                // Priority
            );
        }
    }

    /**
     * 2. Render the Meta Box HTML
     */
    public function listing_gallery_metabox_html($post)
    {
        // Add a nonce for security
        wp_nonce_field('listing_gallery_save', 'listing_gallery_nonce');

        // Retrieve existing gallery data
        $gallery_ids = get_post_meta($post->ID, '_listing_gallery_ids', true);
    ?>

        <div id="listing_gallery_container">
            <ul class="listing-gallery-images">
                <?php
                if ($gallery_ids) {
                    $ids = explode(',', $gallery_ids);
                    foreach ($ids as $attachment_id) {
                        $img = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                        if ($img) {
                            echo '<li class="image" data-attachment_id="' . esc_attr($attachment_id) . '">
                                     <img src="' . esc_url($img[0]) . '" />
                                     <a href="#" class="remove-image" title="Remove image">×</a>
                                   </li>';
                        }
                    }
                }
                ?>
            </ul>

            <input type="hidden" id="listing_gallery_ids" name="listing_gallery_ids" value="<?php echo esc_attr($gallery_ids); ?>" />

            <p class="add_listing_gallery_images hide-if-no-js">
                <a href="#" class="button" id="manage_listing_gallery">Add/Edit Gallery Images</a>
            </p>
        </div>

        <style>
            .listing-gallery-images {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -5px;
                padding: 0;
            }

            .listing-gallery-images li.image {
                width: 80px;
                height: 80px;
                margin: 5px;
                position: relative;
                list-style: none;
                border: 1px solid #ccc;
                background: #f1f1f1;
                cursor: move;
                /* Hints at sortable capability */
            }

            .listing-gallery-images li.image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .listing-gallery-images .remove-image {
                position: absolute;
                top: 0;
                right: 0;
                background: #cc0000;
                color: #fff;
                font-weight: bold;
                text-decoration: none;
                width: 20px;
                height: 20px;
                line-height: 18px;
                text-align: center;
                border-radius: 0 0 0 4px;
            }
        </style>
    <?php
    }

    /**
     * 3. Save the Data
     */
    public function listing_gallery_save_meta($post_id)
    {
        // Security checks
        if (!isset($_POST['listing_gallery_nonce']) || !wp_verify_nonce($_POST['listing_gallery_nonce'], 'listing_gallery_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the data
        if (isset($_POST['listing_gallery_ids'])) {
            update_post_meta($post_id, '_listing_gallery_ids', sanitize_text_field($_POST['listing_gallery_ids']));
        } else {
            delete_post_meta($post_id, '_listing_gallery_ids');
        }
    }

    /**
     * 4. Load Scripts for Media Manager
     */
    public function listing_gallery_admin_scripts()
    {
        global $post;

        // Only load on our specific post types
        $allowed_types = ['campervan', 'caravan', 'motorhome', 'page']; // Added page for filter builder styling
        if (!$post || !in_array($post->post_type, $allowed_types)) {
            return;
        }

        // Enqueue WordPress Media API
        wp_enqueue_media();

        // Check if jQuery UI Sortable is needed (optional, for drag-and-drop reordering)
        wp_enqueue_script('jquery-ui-sortable');

    ?>
        <script>
            jQuery(document).ready(function($) {
                var frame;
                var imageContainer = $('.listing-gallery-images');
                var hiddenInput = $('#listing_gallery_ids');

                // Make the list sortable
                imageContainer.sortable({
                    update: function(event, ui) {
                        updateHiddenInput();
                    }
                });

                // Open Media Manager
                $('#manage_listing_gallery').on('click', function(e) {
                    e.preventDefault();

                    // If the frame already exists, re-open it.
                    if (frame) {
                        frame.open();
                        return;
                    }

                    // Create the media frame.
                    frame = wp.media({
                        title: 'Select Images for Gallery',
                        button: {
                            text: 'Add to Gallery'
                        },
                        multiple: true // Allow selecting multiple images
                    });

                    // When an image is selected, run a callback.
                    frame.on('select', function() {
                        var selection = frame.state().get('selection');

                        selection.map(function(attachment) {
                            attachment = attachment.toJSON();

                            // Prevent adding duplicates if you want
                            if (imageContainer.find('li[data-attachment_id="' + attachment.id + '"]').length === 0) {
                                var html = '<li class="image" data-attachment_id="' + attachment.id + '">';
                                html += '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" />';
                                html += '<a href="#" class="remove-image" title="Remove image">×</a>';
                                html += '</li>';
                                imageContainer.append(html);
                            }
                        });

                        updateHiddenInput();
                    });

                    frame.open();
                });

                // Remove Image Logic
                imageContainer.on('click', '.remove-image', function(e) {
                    e.preventDefault();
                    $(this).parent().remove();
                    updateHiddenInput();
                });

                // Helper: Update the hidden input with comma-separated IDs
                function updateHiddenInput() {
                    var ids = [];
                    imageContainer.find('li').each(function() {
                        ids.push($(this).data('attachment_id'));
                    });
                    hiddenInput.val(ids.join(','));
                }
            });
        </script>
    <?php
    }

    /* ==========================================================================
       EXISTING FILTER LOGIC
       ========================================================================== */

    /**
     * 2. Helper: Get Unique Meta Values
     */
    private function get_unique_meta_values($key)
    {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC
        ", $key));
        return $results;
    }

    /**
     * 2.1 Helper: Render Filter Accordion Item
     * * @param string $label   Title of the accordion
     * * @param string $name     Name attribute (e.g., 'price')
     * * @param array  $options Associative array [value => label]
     * * @param string $type     'select', 'checkbox', or 'radio'
     * * @param array  $args     ['active', 'disabled', 'default_label', 'id', 'selected']
     */
    private function render_filter_item($label, $name, $options, $type = 'select', $args = array())
    {
        $id = isset($args['id']) ? $args['id'] : 'filter-' . $name;
        $is_active = (isset($args['active']) && $args['active']) ? 'active' : '';
        $is_disabled = (isset($args['disabled']) && $args['disabled']) ? 'disabled' : '';
        $default_label = isset($args['default_label']) && !empty($args['default_label']) ? $args['default_label'] : 'Select ' . $label;
        $selected_value = isset($args['selected']) ? $args['selected'] : '';

        // Handle array syntax for checkboxes
        $input_name = ($type === 'checkbox') ? $name . '[]' : $name;

        // Auto-expand accordion if value selected
        if (!empty($selected_value)) {
            $is_active = 'active';
        }

        ob_start();
    ?>
        <div class="filter-accordion <?php echo esc_attr($is_active); ?>">
            <div class="accordion-header"><?php echo esc_html($label); ?></div>
            <div class="accordion-content">

                <?php if ($type === 'select') : ?>
                    <select name="<?php echo esc_attr($input_name); ?>" id="<?php echo esc_attr($id); ?>" class="filter-input" <?php echo $is_disabled; ?>>
                        <option value=""><?php echo esc_html($default_label); ?></option>
                        <?php foreach ($options as $val => $text) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_value, $val); ?>><?php echo esc_html($text); ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'checkbox' || $type === 'radio') : ?>
                    <div class="input--<?php echo ($type === 'checkbox') ? 'checkboxes' : 'radios'; ?> input-style-v2">
                        <?php foreach ($options as $val => $text) : ?>
                            <?php
                            $checked = '';
                            if (is_array($selected_value)) {
                                if (in_array($val, $selected_value)) $checked = 'checked';
                            } else {
                                if ($val == $selected_value) $checked = 'checked';
                            }
                            ?>
                            <label>
                                <input type="<?php echo $type; ?>"
                                    name="<?php echo esc_attr($input_name); ?>"
                                    value="<?php echo esc_attr($val); ?>"
                                    class="filter-input"
                                    <?php echo $checked; ?>
                                    <?php echo $is_disabled; ?>>

                                <span> <span class="pseudo-input"></span> <?php echo esc_html($text); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * 3. Shortcode Output
     */
    public function render_caravan_filter()
    {
        $post_type = 'caravan';
        $condition = isset($_GET['condition']) ? $_GET['condition'] : 'New';

        // Check if current page is mapped to a specific post type
        if ($this->is_listing_page('caravan')) {
            $post_type = 'caravan';
        } elseif ($this->is_listing_page('motorhome')) {
            $post_type = 'motorhome';
        } elseif ($this->is_listing_page('campervan')) {
            $post_type = 'campervan';
        } elseif (is_post_type_archive('product') || is_tax('category', 'product') || is_page('product')) {
            $post_type = 'product';
            $condition = false; // Products usually don't filter by New/Used via URL param same way
        }

        // --- Fetch Dynamic Configuration ---
        $config = get_option('couplands_filters_' . $post_type, []);
        $filter_fields_js = []; // For passing to JS

        ob_start();
    ?>
        <div class="caravan-filter-container cls-filter-modal-wrapper" id="cls-filter-modal-wrapper">
            <div class="caravan-sidebar cls-filter-modal-content">
                <!-- Injected Modal Close Button (CSS handles visibility) -->
                <div class="modal--header">
                    <h4>Filters</h4>
                    <button id="cls-filter-modal-close" class="cls-filter-modal-close cls-filter-modal-close-trigger" type="button" aria-label="Close Filters">Close <span>&times;</span></button>
                </div>
                <form id="caravan-filter-form">
                    <input type="hidden" value="<?= $post_type ?>" name="post_type" id="post_type">
                    <?php if ($condition): ?>
                        <input type="hidden" value="<?= $condition ?>" name="condition">
                    <?php endif; ?>

                    <?php
                    // Loop through configured filters
                    if (!empty($config)) {
                        foreach ($config as $filter) {
                            $slug = $filter['slug'];
                            $label = $filter['label'];
                            $type = $filter['type'];
                            $input_type = $filter['input_type'];
                            $default_label = isset($filter['default_label']) ? $filter['default_label'] : '';

                            // Determine Input Name (Use Custom or Default Slug)
                            $input_name = !empty($filter['custom_name']) ? $filter['custom_name'] : $slug;

                            // Pass the input name to JS array so facets update correctly
                            $filter_fields_js[] = $input_name;

                            // Special Case: listing-make-model (Parent/Child Logic)
                            if ($slug === 'listing-make-model' && $type === 'taxonomy') {
                                // 1. Render Parent (Make)
                                $make_terms = get_terms(['taxonomy' => 'listing-make-model', 'parent' => 0, 'hide_empty' => true]);
                                // UPDATED: Use 'slug' instead of 'term_id'
                                $make_options = wp_list_pluck($make_terms, 'name', 'slug');

                                // Check for Auto-Select on Make
                                $selected_make = isset($_GET['make']) ? $_GET['make'] : '';

                                echo $this->render_filter_item($label, 'make', $make_options, 'select', ['active' => true, 'id' => 'filter-make', 'selected' => $selected_make]);

                                // 2. Render Child (Model) - Initially Disabled unless Make is selected
                                $model_disabled = true;
                                $model_options = [];
                                $selected_model = isset($_GET['model']) ? $_GET['model'] : '';

                                // If Make is pre-selected via GET, enable Model
                                if ($selected_make) {
                                    // Since $selected_make is now a SLUG, we need to get the term object first to get its ID for 'parent' check
                                    $make_term_obj = get_term_by('slug', $selected_make, 'listing-make-model');

                                    if ($make_term_obj && !is_wp_error($make_term_obj)) {
                                        $model_disabled = false;
                                        // Fetch models for this make to pre-populate
                                        $model_terms = get_terms([
                                            'taxonomy' => 'listing-make-model',
                                            'parent' => $make_term_obj->term_id,
                                            'hide_empty' => true
                                        ]);
                                        // UPDATED: Use 'slug' instead of 'term_id'
                                        $model_options = wp_list_pluck($model_terms, 'name', 'slug');
                                    }
                                }

                                echo $this->render_filter_item('Model', 'model', $model_options, 'select', ['disabled' => $model_disabled, 'id' => 'filter-model', 'selected' => $selected_model]);
                                continue;
                            }

                            // General Case: Get Options
                            $options = [];
                            if ($type === 'taxonomy') {
                                $terms = get_terms(['taxonomy' => $slug, 'hide_empty' => true]);
                                if (!is_wp_error($terms)) {
                                    $options = wp_list_pluck($terms, 'name', 'term_id');
                                }
                            } else {
                                // Meta
                                $raw_values = $this->get_unique_meta_values($slug);
                                if ($slug === 'price' || $slug === 'per_month') {
                                    foreach ($raw_values as $p) $options[$p] = number_format((float)$p);
                                } else {
                                    $options = array_combine($raw_values, $raw_values);
                                }
                            }

                            $args = [];
                            if ($default_label) $args['default_label'] = $default_label;

                            // Check for Auto-Selection via URL Param
                            if (isset($_GET[$input_name])) {
                                $args['selected'] = $_GET[$input_name];
                            }

                            echo $this->render_filter_item($label, $input_name, $options, $input_type, $args);
                        }
                    } else {
                        echo '<p>No filters configured.</p>';
                    }
                    ?>

                </form>
                <div class="apply-filter-holder cls-filter-modal-close-trigger">
                    <span>Apply Filters & Search</span>
                </div>
                <div class="showing">
                    <span class="elementor-heading-title elementor-size-default">
                        Show
                        <span class="post-count" style="font-weight: 600">0</span> <span class="title-make"></span>
                        <span class="title-listing">
                        </span>
                    </span>
                </div>
                <div class="reset-holder-box reset-filter">
                    <a href="#">Reset</a>
                </div>
            </div>
        </div>

        <?php $this->output_js($filter_fields_js); ?>

    <?php
        return ob_get_clean();
    }

    /**
     * 4. AJAX Handler
     */
    public function ajax_filter_caravans()
    {
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'caravan';
        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1; // NEW: Get page number

        // Load Configuration
        $config = get_option('couplands_filters_' . $post_type, []);

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => 12, // NEW: Limit to 12
            'paged' => $paged,      // NEW: Apply pagination
            'tax_query' => ['relation' => 'AND'],
            'meta_query' => ['relation' => 'AND'],
        );

        // --- NEW: STRICTLY LIMIT TO "LISTING" CATEGORY ---
        $args['tax_query'][] = array(
            'taxonomy' => 'listing-category',
            'field'    => 'name',
            'terms'    => 'Listing',
            'operator' => 'IN'
        );

        // Handle Default 'Condition' hidden field if present and not in config
        if (isset($_POST['condition']) && !empty($_POST['condition'])) {
            // Only add if not already handled by config loop (unlikely for hidden field)
            $found = false;
            foreach ($config as $c) {
                if ($c['slug'] === 'condition') $found = true;
            }
            if (!$found) {
                $args['meta_query'][] = [
                    'key' => 'condition',
                    'value' => sanitize_text_field($_POST['condition']),
                    'compare' => '='
                ];
            }
        }

        // --- NEW: Handle Sorting ---
        if (isset($_POST['sort_by']) && !empty($_POST['sort_by'])) {
            $sort_by = sanitize_text_field($_POST['sort_by']);

            switch ($sort_by) {
                case 'title_asc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'ASC';
                    break;
                case 'title_desc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'DESC';
                    break;
                case 'price_asc':
                    $args['order'] = 'ASC';
                    $args['orderby'] = 'meta_value_num';
                    // Determine which price meta key to use
                    if ($post_type === 'product') {
                        $args['meta_key'] = '_price'; // WooCommerce price
                    } else {
                        $args['meta_key'] = 'price'; // Custom vehicle price
                    }
                    break;
                case 'price_desc':
                    $args['order'] = 'DESC';
                    $args['orderby'] = 'meta_value_num';
                    // Determine which price meta key to use
                    if ($post_type === 'product') {
                        $args['meta_key'] = '_price'; // WooCommerce price
                    } else {
                        $args['meta_key'] = 'price'; // Custom vehicle price
                    }
                    break;
            }
        }

        // --- Build Query Dynamically based on Config ---
        foreach ($config as $filter) {
            $slug = $filter['slug'];
            $type = $filter['type'];

            // Determine the input name to look for in POST data
            $input_name = !empty($filter['custom_name']) ? $filter['custom_name'] : $slug;

            // Special Case: Make/Model
            if ($slug === 'listing-make-model') {
                if (!empty($_POST['model'])) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'listing-make-model',
                        // UPDATED: Use 'slug' field
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($_POST['model']),
                    ];
                } elseif (!empty($_POST['make'])) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'listing-make-model',
                        // UPDATED: Use 'slug' field
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($_POST['make']),
                        'include_children' => true,
                    ];
                }
                continue;
            }

            // General Fields
            if (isset($_POST[$input_name]) && !empty($_POST[$input_name])) {
                $value = $_POST[$input_name];

                // Sanitize recursive
                if (is_array($value)) {
                    $value = map_deep($value, 'sanitize_text_field');
                    $compare = 'IN';
                } else {
                    $value = sanitize_text_field($value);
                    $compare = '=';
                }

                if ($type === 'taxonomy') {
                    $args['tax_query'][] = [
                        'taxonomy' => $slug,
                        'field'    => 'term_id',
                        'terms'    => $value,
                        'operator' => 'IN' // Standard for checkboxes/selects
                    ];
                } else {
                    $args['meta_query'][] = [
                        'key'       => $slug,
                        'value'     => $value,
                        'compare' => $compare
                    ];
                }
            }
        }

        // --- Calculate Facets (Correctly) ---
        // If we only query 12 items, facets will be incomplete.
        // We only calculate facets on the first page load or filter change (paged = 1).
        $available = [
            'models' => [],
        ];

        // Initialize available arrays
        foreach ($config as $filter) {
            $input_name = !empty($filter['custom_name']) ? $filter['custom_name'] : $filter['slug'];
            $available[$input_name] = [];
        }

        // We run a separate query for facets if this is page 1, so the user sees all available options
        if ($paged === 1) {
            $facet_args = $args;
            $facet_args['posts_per_page'] = -1; // Get all matching posts
            unset($facet_args['paged']);

            $facet_query = new WP_Query($facet_args);

            if ($facet_query->have_posts()) {
                while ($facet_query->have_posts()) {
                    $facet_query->the_post();
                    $id = get_the_ID();

                    foreach ($config as $filter) {
                        $slug = $filter['slug'];
                        $type = $filter['type'];
                        $input_name = !empty($filter['custom_name']) ? $filter['custom_name'] : $slug;

                        if ($slug === 'listing-make-model') {
                            $terms = get_the_terms($id, 'listing-make-model');
                            if ($terms && !is_wp_error($terms)) {
                                foreach ($terms as $term) {
                                    // UPDATED: Populate models array using SLUG as key
                                    if ($term->parent != 0) $available['models'][$term->slug] = $term->name;
                                }
                            }
                            continue;
                        }

                        if ($type === 'taxonomy') {
                            $terms = get_the_terms($id, $slug);
                            if ($terms && !is_wp_error($terms)) {
                                foreach ($terms as $t) $available[$input_name][] = $t->term_id;
                            }
                        } else {
                            // Meta
                            $val = get_post_meta($id, $slug, true);
                            if (!empty($val)) {
                                if (is_array($val)) {
                                    $available[$input_name] = array_merge($available[$input_name], $val);
                                } else {
                                    $available[$input_name][] = $val;
                                }
                            }
                        }
                    }
                }
                wp_reset_postdata();
            }

            // Deduplicate and re-index
            foreach (array_keys($available) as $k) {
                if ($k === 'models') continue;
                $available[$k] = array_values(array_unique(array_filter($available[$k])));
            }
        }

        // --- Run Actual Paginated Query to get Max Pages ---
        $query = new WP_Query($args);
        $max_pages = $query->max_num_pages;
        wp_reset_postdata();

        // --- Get HTML ---
        $html = $this->get_caravan_listing_html($args);

        wp_send_json_success([
            'html'      => $html,
            'facets'    => $available,
            'max_pages' => $max_pages // NEW: Return max pages for JS logic
        ]);
    }

    /**
     * 5. HTML Generator
     */
    private function get_caravan_listing_html($args = [])
    {
        if (empty($args)) $args = ['post_type' => 'caravan', 'posts_per_page' => -1];

        $query = new WP_Query($args);
        ob_start();

        $template_id = ($args['post_type'] == 'product') ? 1049 : 549;

        if ($query->have_posts()) :
            $i = 0;
            while ($query->have_posts()) : $query->the_post();
                if (class_exists('\Elementor\Plugin')) {
                    echo do_shortcode('[elementor-template id="' . $template_id . '"]');
                    if ($i == 2 && $args['post_type'] != 'product') {
                        echo do_shortcode('[elementor-template id="662"]');
                    }
                } else {
                    echo '<div>' . get_the_title() . '</div>';
                }
                $i++;
            endwhile;
        else :
            echo '<p>No items found.</p>';
        endif;
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * 6. JavaScript (jQuery)
     */
    public function output_js($filter_fields = [])
    {
    ?>
        <style>
            /* Base trigger styling */
            .cls-mobile-filter-btn {
                display: none;
                align-items: center;
                justify-content: space-between;
                background-color: #fff !important;
                color: #000 !important;
                padding: 14px;
                border-radius: 5px !important;
                border: none;
                cursor: pointer !important;
                font-size: 16px;
                font-family: ;
                gap: 12px;
                transition: background-color 0.2s ease;
                width: 100%;
                min-height: 55px;
            }

            .cls-mobile-filter-btn:hover {
                background-color: #DDE0E3;
            }

            .cls-filter-modal-close {
                display: none;
            }



            /* Modal Enforcement Breakpoint */
            @media (max-width: 1024px) {
                .cls-mobile-filter-btn {
                    display: flex;
                }

                .cls-filter-modal-close span {
                    font-size: 40px;
                    line-height: 1;
                }

                /* Overlay Background */
                .cls-filter-modal-wrapper {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    background: rgba(0, 0, 0, 0.6);
                    z-index: 99999;
                    display: flex;
                    align-items: stretch;
                    /* Forces the drawer to be full height */
                    justify-content: flex-start;
                    /* Aligns drawer to the left */

                    /* Hide using visibility instead of display to allow transitions */
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                }

                /* Modal Content (The Drawer) */
                .cls-filter-modal-content {
                    background: #fff;
                    width: 85%;
                    max-width: 400px;
                    height: 100vh;
                    max-height: 100vh;
                    overflow-y: auto;
                    position: relative;
                    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3);
                    border-radius: 0;

                    /* Start off-screen to the left */
                    transform: translateX(-100%);
                    /* Smooth easing for the slide-in */
                    transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
                }

                /* Active State */
                .cls-filter-modal-wrapper.is-active {
                    opacity: 1;
                    visibility: visible;
                }

                /* Slide the drawer in when active */
                .cls-filter-modal-wrapper.is-active .cls-filter-modal-content {
                    transform: translateX(0);
                }

                /* Close Button Styling */
                .cls-filter-modal-close.cls-filter-modal-close {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: none;
                    font-size: 12px;
                    line-height: 1;
                    cursor: pointer;
                    color: #000;
                    transition: background 0.2s ease;
                    background-color: transparent !important;
                    padding: 0;
                    gap: 10px;
                    border-radius: 0 !important;
                }

                .cls-filter-modal-close:hover {
                    background: #e2e4e7;
                }
            }

            @media(max-width: 767px) {
                .cls-mobile-filter-btn.cls-mobile-filter-btn {
                    font-size: 12px;
                    min-height: 47px;
                }
                .cls-filter-modal-content {
                    width: calc(100% - 25px);
                }
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                const $form = $('#caravan-filter-form');
                const $resultContainer = $('#my-loop-grid-container');

                // NEW: State for pagination
                let currentPage = 1;
                let maxPages = 1;

                // Active filters passed from PHP (Custom Names)
                const activeFilters = <?php echo json_encode($filter_fields); ?>;

                // Toggle Accordion
                $('.accordion-header').on('click', function() {
                    $(this).parent().toggleClass('active');
                });

                // Update Titles on Change
                $('#filter-make').on('change', function() {
                    $('.title-make').text($(this).find('option:selected').text());
                });
                $('#filter-year').on('change', function() {
                    $('.title-year').text($(this).find('option:selected').text());
                });

                // NEW: Update Title Make on Load if selected
                if ($('#filter-make').val()) {
                    $('.title-make').text($('#filter-make option:selected').text());
                }

                // Trigger Filter (Reset to Page 1)
                $form.on('change', '.filter-input', function() {
                    if ($(this).attr('name') === 'make') {
                        $('#filter-model').val(''); // Reset model if make changes
                    }
                    currentPage = 1; // Reset page
                    fetchCaravans(false);
                });

                // --- NEW: Sort Dropdown Change Listener ---
                $(document).on('change', '.listing-sort-dropdown', function() {
                    currentPage = 1; // Reset page
                    fetchCaravans(false);
                });

                // Reset
                $('.reset-filter a').on('click', function(e) {
                    e.preventDefault();
                    $form[0].reset();
                    $('#filter-model').html('<option value="">Select Model</option>').prop('disabled', true);

                    // Reset Sort Dropdown
                    $('.listing-sort-dropdown').val('');

                    // Reset Visuals
                    $('select.filter-input option').prop('disabled', false);
                    $('input.filter-input').prop('disabled', false).closest('label').css('opacity', '1');
                    $('select.filter-input').prop('disabled', false);

                    // Uncheck checkboxes/radios
                    $('input[type="checkbox"], input[type="radio"]').prop('checked', false);

                    currentPage = 1; // Reset page
                    fetchCaravans(false);
                });

                // NEW: Load More Click Handler
                $(document).on('click', '#listing-load-more-btn', function(e) {
                    e.preventDefault();
                    if (currentPage < maxPages) {
                        currentPage++;
                        fetchCaravans(true);
                    }
                });



                /**
                 * Modal UI State Controller
                 * Manages class toggling and body scroll locking for the filter view.
                 */
                const filterTrigger = document.getElementById('cls-mobile-filter-trigger');
                const filterModal = document.getElementById('cls-filter-modal-wrapper');
                const filterCloseElements = document.querySelectorAll('.cls-filter-modal-close-trigger');




                if (filterTrigger && filterModal) {
                    filterTrigger.addEventListener('click', function() {
                        filterModal.classList.add('is-active');
                        $('body').css('overflow', 'hidden'); // Prevent background scrolling
                    });
                }

                const closeModal = function() {
                    if (filterModal) {
                        filterModal.classList.remove('is-active');
                        $('body').css('overflow', ''); // Restore scroll
                    }
                };


                if (filterCloseElements.length > 0) {
                    filterCloseElements.forEach(function(el) {
                        el.addEventListener('click', closeModal);
                    });
                }

                // Close on exterior overlay click
                $(window).on('click', function(event) {
                    if (event.target === filterModal) {
                        closeModal();
                    }
                });

                function fetchCaravans(isLoadMore) {
                    $resultContainer.addClass('caravan-loader');
                    var formData = new FormData($form[0]);
                    formData.append('action', 'filter_caravans');
                    formData.append('paged', currentPage); // Send current page

                    // --- NEW: Append Sort Value ---
                    if ($('.listing-sort-dropdown').length) {
                        formData.append('sort_by', $('.listing-sort-dropdown').val());
                    }

                    $.ajax({
                        url: caravan_ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Handle HTML Append vs Replace
                                if (isLoadMore) {
                                    $resultContainer.append(response.data.html);
                                } else {
                                    $resultContainer.html(response.data.html);
                                }

                                $('.post-count').text($('#my-loop-grid-container .e-loop-item').length);

                                // Only update facets if we are on page 1 (re-filtering)
                                // Facets are returned empty/partial on paged > 1 requests to save processing
                                if (!isLoadMore && response.data.facets) {
                                    updateFilters(response.data.facets);
                                }

                                // NEW: Update Max Pages and Button Visibility
                                if (response.data.max_pages !== undefined) {
                                    maxPages = response.data.max_pages;
                                }

                                updateLoadMoreButton();
                            }
                            $resultContainer.removeClass('caravan-loader');
                        },
                        error: function() {
                            $resultContainer.removeClass('caravan-loader');
                        }
                    });
                }

                // NEW: Logic to add/remove/hide Load More Button
                function updateLoadMoreButton() {
                    const btnId = 'listing-load-more-btn';
                    let $btn = $('#' + btnId);

                    // If page 1 and no button exists, inject it AFTER result container
                    if ($btn.length === 0) {
                        $resultContainer.after('<div class="load-more-wrapper" style="text-align:center; margin-top:30px;"><button id="' + btnId + '" class="button" style="padding:10px 30px; cursor:pointer;">Load More</button></div>');
                        $btn = $('#' + btnId);
                    }

                    if (currentPage >= maxPages) {
                        $btn.parent().hide();
                    } else {
                        $btn.parent().show();
                        $btn.text('Load More'); // Reset text if needed
                    }
                }

                /**
                 * Update Filter Facets (Disable empty options)
                 */
                function updateFilters(facets) {

                    // 1. Update Models (Dependent Logic)
                    const $modelSelect = $('#filter-model');
                    const currentModel = $modelSelect.val();
                    const makeVal = $('#filter-make').val();

                    if ($modelSelect.length) {
                        $modelSelect.html('<option value="">Select Model</option>');
                        if (makeVal && facets.models && Object.keys(facets.models).length > 0) {
                            $modelSelect.prop('disabled', false);
                            $.each(facets.models, function(id, name) {
                                let option = $('<option></option>').attr("value", id).text(name);
                                if (id == currentModel) option.prop('selected', true);
                                $modelSelect.append(option);
                            });
                        } else {
                            $modelSelect.prop('disabled', true);
                        }
                    }

                    // 2. Generic Field Updater
                    const updateField = (fieldName, availableValues) => {
                        // Ensure availableValues is an array and convert to strings
                        const validStr = Array.isArray(availableValues) ? availableValues.map(String) : [];

                        // Select the input element(s)
                        const $el = $('[name="' + fieldName + '"], [name="' + fieldName + '[]"]');

                        if ($el.is('select')) {
                            // --- HANDLE SELECT DROPDOWN ---
                            $el.find('option').each(function() {
                                const val = $(this).val();
                                if (val === "") return; // Skip placeholder

                                if (validStr.includes(val)) {
                                    $(this).prop('disabled', false);
                                } else {
                                    $(this).prop('disabled', true);
                                }
                            });
                        } else {
                            // --- HANDLE CHECKBOX / RADIO ---
                            $el.each(function() {
                                const val = $(this).val();
                                const isValid = validStr.includes(val);

                                $(this).prop('disabled', !isValid);
                                $(this).closest('label').css('opacity', isValid ? '1' : '0.5');
                            });
                        }
                    };

                    // Run updates Loop through active filters passed from PHP
                    if (activeFilters && Array.isArray(activeFilters)) {
                        activeFilters.forEach(function(fieldSlug) {
                            // Skip listing-make-model (make/model inputs) as they are handled separately
                            if (fieldSlug !== 'make' && fieldSlug !== 'model' && facets[fieldSlug]) {
                                updateField(fieldSlug, facets[fieldSlug]);
                            }
                        });
                    }
                }

                // Note: Only call fetchCaravans if URL params are present OR simply load default
                // Since render_caravan_filter() handles initial state via PHP, we don't strictly need 
                // to call fetchCaravans() immediately on page load unless you want to re-sync facets immediately.
                // However, standard behavior is to fetch to get facets for the initial selection.
                fetchCaravans(false);
            });
        </script>
<?php
    }
}
new Listing_System();
?>