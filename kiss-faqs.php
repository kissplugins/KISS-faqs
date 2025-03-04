<?php
/**
 * Plugin Name: KISS FAQs with Schema
 * Plugin URI:  https://KISSplugins.com
 * Description: Manage and display FAQs (Question = Post Title, Answer = Post Content Editor) with Google's Structured Data. Shortcode: [KISSFAQ post="ID"]. Safari-friendly toggle, displays FAQ ID in editor, and now has a column showing the shortcode/post ID.
 * Version: 1.04
 * Author: KISS Plugins
 * Author URI: https://KISSplugins.com
 * License: GPL2
 *
 * Text Domain: kiss-faqs
 * Domain Path: /languages
 */

/*
  This plugin is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This plugin is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License, version 2, for more details.

  You should have received a copy of the GNU General Public License
  along with this plugin; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Disallow direct file access
}

class KISSFAQsWithSchema {

    private static $instance = null;
    public $plugin_version = '1.04';
    public $db_table_name  = 'KISSFAQs'; // Table name (legacy)
    private static $kiss_faq_schema_data = array();

    /**
     * Singleton Instance
     */
    public static function init() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new KISSFAQsWithSchema();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );

        // Register CPT
        add_action( 'init', array( $this, 'register_faqs_cpt' ) );

        // Shortcode
        add_shortcode( 'KISSFAQ', array( $this, 'render_faq_shortcode' ) );

        // Shortcode
        add_shortcode( 'KISSFAQS', array( $this, 'render_all_faqs_shortcode' ) );

        add_action( 'init', array( $this, 'register_faq_category_taxonomy' ) );

        // Check for legacy data on admin notice
        add_action( 'admin_notices', array( $this, 'admin_notice_legacy_data' ) );

        // Show FAQ ID in editor
        add_action( 'edit_form_after_title', array( $this, 'display_faq_id_in_editor' ) );

        // "Settings" link → All FAQs
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_settings_link' ) );

        // (Optional) If you want a custom settings page
        add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );

        // **NEW**: Add a column in the CPT listing to show the shortcode/post ID
        add_filter( 'manage_kiss_faq_posts_columns', array( $this, 'add_shortcode_column' ) );
        add_action( 'manage_kiss_faq_posts_custom_column', array( $this, 'render_shortcode_column' ), 10, 2 );

        add_action('wp_footer', array($this, 'output_kiss_faq_schema'),999);
    }

    /**
     * Plugin activation
     */
    public function activate_plugin() {
        // If you still want the old table, keep dbDelta
        global $wpdb;
        $table_name      = $wpdb->prefix . $this->db_table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            faq_id bigint(20) NOT NULL,
            question text NOT NULL,
            answer longtext NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Check for leftover legacy data
        $this->check_legacy_data();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // e.g., do nothing or flush_rewrite_rules();
    }

    /**
     * Check for legacy DB records from older versions
     */
    public function check_legacy_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->db_table_name;

        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name )
        );

        if ( $table_exists === $table_name ) {
            // Count how many records
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
            if ( $count > 0 ) {
                update_option( 'kiss_faqs_legacy_data_exists', $count );
            } else {
                delete_option( 'kiss_faqs_legacy_data_exists' );
            }
        } else {
            delete_option( 'kiss_faqs_legacy_data_exists' );
        }
    }

    /**
     * Admin notice if legacy data is found
     */
    public function admin_notice_legacy_data() {
        $count = get_option( 'kiss_faqs_legacy_data_exists', 0 );
        if ( $count > 0 ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>KISS FAQs with Schema:</strong> We found <code>' . intval( $count ) . '</code> legacy record(s) in the old <code>' . esc_html( $this->db_table_name ) . '</code> table. This plugin no longer uses those records. You may remove or migrate them if you wish.</p>';
            echo '</div>';
        }
    }

    /**
     * Register Custom Post Type: kiss_faq
     * - Question = post title
     * - Answer   = post content
     */
    public function register_faqs_cpt() {
        $labels = array(
            'name'                  => __( 'FAQs', 'kiss-faqs' ),
            'singular_name'         => __( 'FAQ', 'kiss-faqs' ),
            'menu_name'             => __( 'FAQs', 'kiss-faqs' ),
            'name_admin_bar'        => __( 'FAQ', 'kiss-faqs' ),
            'add_new'               => __( 'Add New', 'kiss-faqs' ),
            'add_new_item'          => __( 'Add New FAQ', 'kiss-faqs' ),
            'new_item'              => __( 'New FAQ', 'kiss-faqs' ),
            'edit_item'             => __( 'Edit FAQ', 'kiss-faqs' ),
            'view_item'             => __( 'View FAQ', 'kiss-faqs' ),
            'all_items'             => __( 'All FAQs', 'kiss-faqs' ),
            'search_items'          => __( 'Search FAQs', 'kiss-faqs' ),
            'parent_item_colon'     => __( 'Parent FAQs:', 'kiss-faqs' ),
            'not_found'             => __( 'No FAQs found.', 'kiss-faqs' ),
            'not_found_in_trash'    => __( 'No FAQs found in Trash.', 'kiss-faqs' ),
            'featured_image'        => __( 'Featured Image', 'kiss-faqs' ),
            'set_featured_image'    => __( 'Set featured image', 'kiss-faqs' ),
            'remove_featured_image' => __( 'Remove featured image', 'kiss-faqs' ),
            'use_featured_image'    => __( 'Use as featured image', 'kiss-faqs' ),
            'archives'              => __( 'FAQ archives', 'kiss-faqs' ),
            'insert_into_item'      => __( 'Insert into FAQ', 'kiss-faqs' ),
            'uploaded_to_this_item' => __( 'Uploaded to this FAQ', 'kiss-faqs' ),
            'items_list'            => __( 'FAQs list', 'kiss-faqs' ),
            'items_list_navigation' => __( 'FAQs list navigation', 'kiss-faqs' ),
            'filter_items_list'     => __( 'Filter FAQs list', 'kiss-faqs' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'kiss-faq' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-editor-help',
            'supports'           => array( 'title', 'editor', 'revisions' ),
        );

        register_post_type( 'kiss_faq', $args );
    }

    public function register_faq_category_taxonomy() {
        register_taxonomy( 'faq_category', 'kiss_faq', array(
            'label' => __( 'Categories', 'kiss-faqs' ),
            'rewrite' => array( 'slug' => 'faq-category' ),
            'hierarchical' => true,
        ));
    }

    /**
     * Display the FAQ ID in the editor after saving
     */
    public function display_faq_id_in_editor( $post ) {
        if ( 'kiss_faq' === get_post_type( $post ) && $post->ID ) {
            if ( 'auto-draft' !== $post->post_status ) {
                echo '<div style="margin: 10px 0; padding: 10px; background: #f1f1f1; border-left: 3px solid #ccc;">';
                echo '<strong>FAQ ID:</strong> ' . absint( $post->ID ) . '<br>';
                echo '<small>You can use this ID in a shortcode: <code>[KISSFAQ post="' . absint( $post->ID ) . '" hidden="true"]</code></small>';
                echo '</div>';
            }
        }
    }

    public function render_all_faqs_shortcode( $atts ) {
        $atts = shortcode_atts([
            'hidden' => 'true',
            'category' => '',
            'exclude' => ''
        ], $atts, 'KISSFAQS');

        $args = array(
            'post_type' => 'kiss_faq',
            'posts_per_page' => -1,
            'orderby'        => 'date',  // Order by date
            'order'          => 'ASC',   // FIFO (Oldest first)
        );

        if ( ! empty( $atts['category'] ) ) {
            $args['tax_query'] = array([
                'taxonomy' => 'faq_category',
                'field' => 'slug',
                'terms' => explode(',', $atts['category'])
            ]);
        }

        if ( ! empty( $atts['exclude'] ) ) {
            $args['post__not_in'] = array_map('intval', explode(',', $atts['exclude']));
        }

        $faqs = get_posts( $args );
        if ( empty( $faqs ) ) return '<p>No FAQs found.</p>';

        // Initialize schema structure
        $schema_data = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => [],
        );

        $output = '<div class="kiss-faqs">';
        foreach ( $faqs as $index => $faq ) {
            // Q = post_title, A = post_content
            $question = $faq->post_title;
            $answer   = apply_filters( 'the_content', $faq->post_content );

            // Determine hidden setting
            $hidden = ( $index === 0 || 'false' === strtolower( $atts['hidden'] ) ) ? false : true;
            $output .= '<div class="kiss-faq-wrapper" style="margin-bottom: 1em;">';
            $output .= '<div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">
                            <span class="kiss-faq-caret" style="margin-right: 5px;">' . ($hidden ? '►' : '▼') . '</span>
                            <span>' . esc_html($question) . '</span>
                        </div>';
            $output .= '<div class="kiss-faq-answer" style="' . ($hidden ? 'display:none;' : 'display:block; margin-top: 5px;') . '">
                            ' . wp_kses_post($answer) . '
                        </div>';
            $output .= '</div>';

            // Add FAQ to schema
            $schema_data['mainEntity'][] = array(
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $answer ),
                ),
            );
        }
        $output .= '</div>';
        // Only add the JS once per page
        static $kiss_faqs_script_added = false;
        if ( ! $kiss_faqs_script_added ) :
            $kiss_faqs_script_added = true;
        ?>
            <script>
            document.addEventListener('DOMContentLoaded', function(){
                var faqWrappers = document.querySelectorAll('.kiss-faq-wrapper');
                faqWrappers.forEach(function(wrapper){
                    var questionElem = wrapper.querySelector('.kiss-faq-question');
                    var answerElem   = wrapper.querySelector('.kiss-faq-answer');
                    var caretElem    = wrapper.querySelector('.kiss-faq-caret');

                    if (questionElem && answerElem && caretElem) {
                        questionElem.addEventListener('click', function(){
                            if (answerElem.style.display === 'none') {
                                answerElem.style.display = 'block';
                                caretElem.textContent = '▼';
                            } else {
                                answerElem.style.display = 'none';
                                caretElem.textContent = '►';
                            }
                        });
                    }
                });
            });
            </script>
        <?php
        endif;
        ?>
        <script type="application/ld+json">
        <?php echo wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
        </script>
        <?php

        return $output;
    }

    /**
     * Shortcode: [KISSFAQ post="123" hidden="true"]
     * Safari-friendly toggle via DOMContentLoaded
     */
    public function render_faq_shortcode( $atts ) {
        // Default attributes
        $atts = shortcode_atts(
            array(
                'post'   => '',
                'hidden' => 'true', // default if not specified
            ),
            $atts,
            'KISSFAQ'
        );

        $faq_id = absint( $atts['post'] );
        if ( ! $faq_id ) {
            return '<p style="color:red;">FAQ ID not specified or invalid.</p>';
        }

        // Retrieve FAQ post
        $post = get_post( $faq_id );
        if ( ! $post || 'kiss_faq' !== $post->post_type ) {
            return '<p style="color:red;">FAQ not found or invalid post type.</p>';
        }

        // Q = post_title, A = post_content
        $question = $post->post_title;
        $answer   = apply_filters( 'the_content', $post->post_content );

        // Determine hidden setting
        $hidden = ( 'false' === strtolower( $atts['hidden'] ) ) ? false : true;

        // Output
        ob_start();
        ?>
        <div class="kiss-faq-wrapper" style="margin-bottom: 1em;">
            <div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">
                <span class="kiss-faq-caret" style="margin-right: 5px;"><?php echo $hidden ? '►' : '▼'; ?></span>
                <span><?php echo esc_html( $question ); ?></span>
            </div>
            <div class="kiss-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> margin-top: 5px;">
                <?php echo wp_kses_post( $answer ); ?>
            </div>
        </div>
        <?php

        // Only add the JS once per page
        static $kiss_faqs_script_added = false;
        if ( ! $kiss_faqs_script_added ) :
            $kiss_faqs_script_added = true;
        ?>
            <script>
            document.addEventListener('DOMContentLoaded', function(){
                var faqWrappers = document.querySelectorAll('.kiss-faq-wrapper');
                faqWrappers.forEach(function(wrapper){
                    var questionElem = wrapper.querySelector('.kiss-faq-question');
                    var answerElem   = wrapper.querySelector('.kiss-faq-answer');
                    var caretElem    = wrapper.querySelector('.kiss-faq-caret');

                    if (questionElem && answerElem && caretElem) {
                        questionElem.addEventListener('click', function(){
                            if (answerElem.style.display === 'none') {
                                answerElem.style.display = 'block';
                                caretElem.textContent = '▼';
                            } else {
                                answerElem.style.display = 'none';
                                caretElem.textContent = '►';
                            }
                        });
                    }
                });
            });
            </script>
        <?php
        endif;

        // JSON-LD for SEO
        self::$kiss_faq_schema_data[] = array(
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags($answer),
            ),
        );
        ?>
        <?php

        return ob_get_clean();
    }

    /**
     * Replace "Settings" link with a link to All FAQs
     */
    public function add_plugin_settings_link( $links ) {
        $all_faqs_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'edit.php?post_type=kiss_faq' ) ),
            __( 'All FAQs', 'kiss-faqs' )
        );
        array_unshift( $links, $all_faqs_link );
        return $links;
    }

    /**
     * Add a column to the CPT listing for Shortcode/Post ID
     */
    public function add_shortcode_column( $columns ) {
        // We add a custom column at the end
        $columns['kiss_faq_shortcode'] = __( 'Shortcode', 'kiss-faqs' );
        return $columns;
    }

    /**
     * Render the data in the new 'Shortcode' column
     */
    public function render_shortcode_column( $column, $post_id ) {
        if ( 'kiss_faq_shortcode' === $column ) {
            printf(
                '<code>[KISSFAQ post="%d" hidden="true"]</code>',
                absint( $post_id )
            );
        }
    }

    /**
     * (Optional) Register plugin settings page under "Settings"
     */
    public function register_settings_menu() {
        add_options_page(
            __( 'KISS FAQs Settings', 'kiss-faqs' ),
            __( 'KISS FAQs', 'kiss-faqs' ),
            'manage_options',
            'kiss_faqs_settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * (Optional) Render the plugin settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS FAQs Settings', 'kiss-faqs' ); ?></h1>
            <p><?php esc_html_e( 'Here you can configure settings for the KISS FAQs plugin.', 'kiss-faqs' ); ?></p>
            <form method="post" action="options.php">
                <?php
                // If you create custom settings, register them, then use:
                // settings_fields( 'your_setting_slug' );
                // do_settings_sections( 'your_page_slug' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Example Setting', 'kiss-faqs' ); ?></th>
                        <td>
                            <input type="text" name="kiss_faq_example_setting" value="" />
                            <p class="description"><?php esc_html_e( 'Just an example placeholder.', 'kiss-faqs' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function output_kiss_faq_schema() {
        if (!empty(self::$kiss_faq_schema_data)) {
            $schema_data = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => self::$kiss_faq_schema_data,
            );
            echo '<script type="application/ld+json">' . 
                 wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . 
                 '</script>';
        }
    }
}

// Initialize the plugin
KISSFAQsWithSchema::init();
