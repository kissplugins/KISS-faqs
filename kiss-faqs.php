<?php
/**
 * Plugin Name: KISS FAQs with Schema
 * Plugin URI:  https://KISSplugins.com
 * Description: Manage and display FAQs (Question = Post Title, Answer = Post Content Editor) with Google's Structured Data. Includes ID display, Safari reveal/hide fix, and checks for legacy DB data.
 * Version: 1.03
 * Author: KISS plugins
 * Author URI: https://KISSplugins.com
 * License: GPL2
 *
 * Text Domain: hypercart-faqs
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

class HypercartFAQsWithSchema {

    private static $instance = null;
    public $plugin_version = '1.03';
    public $db_table_name  = 'HypercartFAQs'; // Table from older versions (legacy)

    /**
     * Singleton Instance
     */
    public static function init() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new HypercartFAQsWithSchema();
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
        add_shortcode( 'HTPFAQ', array( $this, 'render_faq_shortcode' ) );

        // Check for legacy data on admin notice
        add_action( 'admin_notices', array( $this, 'admin_notice_legacy_data' ) );

        // Show FAQ ID in editor
        add_action( 'edit_form_after_title', array( $this, 'display_faq_id_in_editor' ) );

        // Replace "Settings" link with link to All FAQs
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_settings_link' ) );

        // (Optional) If you still want a custom settings page, you can keep or remove
        add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );
    }

    /**
     * Plugin activation
     */
    public function activate_plugin() {
        // If you no longer need the legacy table, you can remove dbDelta creation.
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

        // Check if any legacy data remains
        $this->check_legacy_data();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // e.g., no action taken but could flush_rewrite_rules() or drop table, etc.
    }

    /**
     * Check for legacy DB records from older plugin versions
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
                update_option( 'hypercart_faqs_legacy_data_exists', $count );
            } else {
                delete_option( 'hypercart_faqs_legacy_data_exists' );
            }
        } else {
            delete_option( 'hypercart_faqs_legacy_data_exists' );
        }
    }

    /**
     * Admin notice if legacy data is found
     */
    public function admin_notice_legacy_data() {
        $count = get_option( 'hypercart_faqs_legacy_data_exists', 0 );
        if ( $count > 0 ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Hypercart FAQs with Schema:</strong> We found <code>' . intval( $count ) . '</code> legacy record(s) in the old <code>' . esc_html( $this->db_table_name ) . '</code> table. This plugin no longer uses those records. You may remove or migrate them if you wish.</p>';
            echo '</div>';
            // If you only want this to display once, uncomment:
            // delete_option( 'hypercart_faqs_legacy_data_exists' );
        }
    }

    /**
     * Register Custom Post Type: hypercart_faq
     * - Question = post title
     * - Answer   = post content
     */
    public function register_faqs_cpt() {
        $labels = array(
            'name'                  => __( 'FAQs', 'hypercart-faqs' ),
            'singular_name'         => __( 'FAQ', 'hypercart-faqs' ),
            'menu_name'             => __( 'FAQs', 'hypercart-faqs' ),
            'name_admin_bar'        => __( 'FAQ', 'hypercart-faqs' ),
            'add_new'               => __( 'Add New', 'hypercart-faqs' ),
            'add_new_item'          => __( 'Add New FAQ', 'hypercart-faqs' ),
            'new_item'              => __( 'New FAQ', 'hypercart-faqs' ),
            'edit_item'             => __( 'Edit FAQ', 'hypercart-faqs' ),
            'view_item'             => __( 'View FAQ', 'hypercart-faqs' ),
            'all_items'             => __( 'All FAQs', 'hypercart-faqs' ),
            'search_items'          => __( 'Search FAQs', 'hypercart-faqs' ),
            'parent_item_colon'     => __( 'Parent FAQs:', 'hypercart-faqs' ),
            'not_found'             => __( 'No FAQs found.', 'hypercart-faqs' ),
            'not_found_in_trash'    => __( 'No FAQs found in Trash.', 'hypercart-faqs' ),
            'featured_image'        => __( 'Featured Image', 'hypercart-faqs' ),
            'set_featured_image'    => __( 'Set featured image', 'hypercart-faqs' ),
            'remove_featured_image' => __( 'Remove featured image', 'hypercart-faqs' ),
            'use_featured_image'    => __( 'Use as featured image', 'hypercart-faqs' ),
            'archives'              => __( 'FAQ archives', 'hypercart-faqs' ),
            'insert_into_item'      => __( 'Insert into FAQ', 'hypercart-faqs' ),
            'uploaded_to_this_item' => __( 'Uploaded to this FAQ', 'hypercart-faqs' ),
            'items_list'            => __( 'FAQs list', 'hypercart-faqs' ),
            'items_list_navigation' => __( 'FAQs list navigation', 'hypercart-faqs' ),
            'filter_items_list'     => __( 'Filter FAQs list', 'hypercart-faqs' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'hypercart-faq' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-editor-help',
            'supports'           => array( 'title', 'editor', 'revisions' ),
        );

        register_post_type( 'hypercart_faq', $args );
    }

    /**
     * Display the FAQ ID in the editor after saving
     */
    public function display_faq_id_in_editor( $post ) {
        if ( 'hypercart_faq' === get_post_type( $post ) && $post->ID ) {
            // Show only if it's not an auto-draft
            if ( 'auto-draft' !== $post->post_status ) {
                echo '<div style="margin: 10px 0; padding: 10px; background: #f1f1f1; border-left: 3px solid #ccc;">';
                echo '<strong>FAQ ID:</strong> ' . absint( $post->ID ) . '<br>';
                echo '<small>You can use this ID in a shortcode: <code>[HTPFAQ post="' . absint( $post->ID ) . '" hidden="true"]</code></small>';
                echo '</div>';
            }
        }
    }

    /**
     * Shortcode: [HTPFAQ post="123" hidden="true"]
     * Uses DOMContentLoaded approach for Safari-friendly reveal/hide
     */
    public function render_faq_shortcode( $atts ) {
        // Default attributes
        $atts = shortcode_atts(
            array(
                'post'   => '',
                'hidden' => 'true', // default if not specified
            ),
            $atts,
            'HTPFAQ'
        );

        $faq_id = absint( $atts['post'] );
        if ( ! $faq_id ) {
            return '<p style="color:red;">FAQ ID not specified or invalid.</p>';
        }

        // Retrieve FAQ post
        $post = get_post( $faq_id );
        if ( ! $post || 'hypercart_faq' !== $post->post_type ) {
            return '<p style="color:red;">FAQ not found or invalid post type.</p>';
        }

        // The question = post_title, answer = post_content
        $question = $post->post_title;
        // Convert the post content to HTML via WP's the_content filters
        $answer   = apply_filters( 'the_content', $post->post_content );

        // Determine hidden setting
        $hidden = ( 'false' === strtolower( $atts['hidden'] ) ) ? false : true;

        // Output the FAQ wrapper
        ob_start();
        ?>
        <div class="hypercart-faq-wrapper" style="margin-bottom: 1em;">
            <div class="hypercart-faq-question" style="cursor: pointer; font-weight: bold;">
                <span class="hypercart-faq-caret" style="margin-right: 5px;">►</span>
                <span><?php echo esc_html( $question ); ?></span>
            </div>
            <div class="hypercart-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> margin-top: 5px;">
                <?php echo wp_kses_post( $answer ); ?>
            </div>
        </div>
        <?php

        // Only add the reveal/hide JS once per page
        static $hypercart_faqs_script_added = false;
        if ( ! $hypercart_faqs_script_added ) :
            $hypercart_faqs_script_added = true;
        ?>
            <script>
            document.addEventListener('DOMContentLoaded', function(){
                // Find all FAQ wrappers on the page
                var faqWrappers = document.querySelectorAll('.hypercart-faq-wrapper');
                faqWrappers.forEach(function(wrapper){
                    var questionElement = wrapper.querySelector('.hypercart-faq-question');
                    var answerElement   = wrapper.querySelector('.hypercart-faq-answer');
                    var caretElement    = wrapper.querySelector('.hypercart-faq-caret');
                    
                    if (questionElement && answerElement && caretElement) {
                        questionElement.addEventListener('click', function(){
                            if (answerElement.style.display === 'none') {
                                answerElement.style.display = 'block';
                                caretElement.textContent = '▼';
                            } else {
                                answerElement.style.display = 'none';
                                caretElement.textContent = '►';
                            }
                        });
                    }
                });
            });
            </script>
        <?php
        endif;

        // Generate JSON-LD for FAQ structured data
        $schema_data = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array(
                array(
                    '@type'          => 'Question',
                    'name'           => $question,
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => wp_strip_all_tags( $answer ),
                    ),
                ),
            ),
        );
        ?>
        <script type="application/ld+json">
        <?php echo wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Replace "Settings" link with a link to "All FAQs"
     */
    public function add_plugin_settings_link( $links ) {
        $all_faqs_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'edit.php?post_type=hypercart_faq' ) ),
            __( 'All FAQs', 'hypercart-faqs' )
        );
        array_unshift( $links, $all_faqs_link );
        return $links;
    }

    /**
     * (Optional) If you want to keep a custom settings page
     */
    public function register_settings_menu() {
        add_options_page(
            __( 'Hypercart FAQs Settings', 'hypercart-faqs' ),
            __( 'Hypercart FAQs', 'hypercart-faqs' ),
            'manage_options',
            'hypercart_faqs_settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * (Optional) Render the plugin settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Hypercart FAQs Settings', 'hypercart-faqs' ); ?></h1>
            <p><?php esc_html_e( 'Here you can configure settings for the Hypercart FAQs plugin.', 'hypercart-faqs' ); ?></p>
            <form method="post" action="options.php">
                <?php
                // If you create custom settings, register them with register_setting().
                // Then do settings_fields( 'your_setting_slug' ), do_settings_sections( 'your_page_slug' ), etc.
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Example Setting', 'hypercart-faqs' ); ?></th>
                        <td>
                            <input type="text" name="hypercart_faq_example_setting" value="" />
                            <p class="description"><?php esc_html_e( 'Just an example placeholder.', 'hypercart-faqs' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
HypercartFAQsWithSchema::init();
