<?php
/**
 * Plugin Name: KISS FAQs with Schema
 * Plugin URI:  https://KISSplugins.com
 * Description: Manage and display FAQs (Question = Post Title, Answer = Post Content Editor) with Google's Structured Data. Shortcode: [KISSFAQ post="ID"]. Safari-friendly toggle, displays FAQ ID in editor, and now has a column showing the shortcode/post ID.
 * Version: 1.04.7
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

// Include the Plugin Update Checker
require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/kissplugins/KISS-faqs',
    __FILE__,
    'kiss-faqs'
);
// Optional: Set the branch that contains the stable release.
$update_checker->setBranch( 'main' );

class KISSFAQsWithSchema {

    private static $instance = null;
    public $plugin_version = '1.04.7';
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

        add_action( 'wp_head', array( $this, 'add_faqs_inline_css' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_dashicons' ) );

        // Register CPT
        add_action( 'init', array( $this, 'register_faqs_cpt' ) );

        // Shortcode
        add_shortcode( 'KISSFAQ', array( $this, 'render_faq_shortcode' ) );

        // Shortcode
        add_shortcode( 'KISSFAQS', array( $this, 'render_all_faqs_shortcode' ) );

        add_action( 'init', array( $this, 'register_faq_category_taxonomy' ) );

        // Register settings for the layout option
        add_action( 'admin_init', array( $this, 'register_faq_layout_settings' ) );

        // Check for legacy data on admin notice
        add_action( 'admin_notices', array( $this, 'admin_notice_legacy_data' ) );

        // Show FAQ ID in editor
        add_action( 'edit_form_after_title', array( $this, 'display_faq_id_in_editor' ) );

        // "Settings" link → All FAQs
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_settings_link' ) );

        // (Optional) If you want a custom settings page
        add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );

        // **NEW**: Register README viewer page
        add_action( 'admin_menu', array( $this, 'register_readme_viewer' ) );

        // **NEW**: Add a column in the CPT listing to show the shortcode/post ID
        add_filter( 'manage_kiss_faq_posts_columns', array( $this, 'add_shortcode_column' ) );
        add_action( 'manage_kiss_faq_posts_custom_column', array( $this, 'render_shortcode_column' ), 10, 2 );

        // **NEW**: Sitemap control metabox
        add_action( 'add_meta_boxes', array( $this, 'add_sitemap_control_metabox' ) );
        add_action( 'save_post', array( $this, 'save_sitemap_control_metabox' ) );

        // **NEW**: Sitemap exclusion functionality
        add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_faqs_from_sitemap' ), 10, 2 );

        // **NEW**: Add noindex for excluded posts
        add_action( 'wp_head', array( $this, 'add_noindex_for_excluded_faqs' ) );

        add_action('wp_footer', array($this, 'output_kiss_faq_schema'),999);
    }

    /**
     * Output inline styles for FAQ elements.
     */
    public function add_faqs_inline_css() {
        ?>
        <style>
            .kiss-faq-caret img{
                width: 12px;
                display: inline-block;
            }
            .kiss-faq-caret.collapsed img{
                transform: translate(0, -2px) rotateZ(270deg);
            }
            .kiss-faq-caret.expanded img{
                transform: unset;
            }
            .kiss-faq-edit-link{
                margin-left:5px;
                text-decoration:none;
                vertical-align:middle;
            }
        </style>
        <?php
    }

    /**
     * Enqueue Dashicons on the front end for the edit link icon.
     */
    public function enqueue_dashicons() {
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            wp_enqueue_style( 'dashicons' );
        }
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

    /**
     * Register FAQ category taxonomy and display it in the admin columns.
     */
    public function register_faq_category_taxonomy() {
        register_taxonomy( 'faq_category', 'kiss_faq', array(
            'label'             => __( 'Categories', 'kiss-faqs' ),
            'rewrite'           => array( 'slug' => 'faq-category' ),
            'hierarchical'      => true,
            'show_admin_column' => true,
        ) );
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

    /**
     * Shortcode handler to display a list of FAQs.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the FAQ list.
     */
    public function render_all_faqs_shortcode( $atts ) {
        $atts = shortcode_atts([
            'hidden' => 'true',
            'category' => '',
            'sub-category' => '',
            'exclude' => '',
            'layout'   => get_option( 'kiss_faqs_layout_style', 'default' ),
        ], $atts, 'KISSFAQS');

        $args = array(
            'post_type' => 'kiss_faq',
            'posts_per_page' => -1,
            'orderby'        => 'date',  // Order by date
            'order'          => 'ASC',   // FIFO (Oldest first)
        );

        if (!empty($atts['category']) || !empty($atts['sub-category'])) {
            $faqs_tax_query = array();
        
            if (!empty($atts['category'])) {
                $faqs_tax_query[] = array(
                    'taxonomy' => 'faq_category',
                    'field'    => 'slug',
                    'terms'    => explode(',', $atts['category']),
                    'include_children' => true,
                );
            }
        
            if (!empty($atts['sub-category'])) {
                $faqs_tax_query[] = array(
                    'taxonomy' => 'faq_category',
                    'field'    => 'slug',
                    'terms'    => explode(',', $atts['sub-category']),
                    'include_children' => false,
                );
            }
        
            // If both category and sub-category are specified, use AND relation
            if (count($faqs_tax_query) > 1) {
                $faqs_tax_query['relation'] = 'AND';
            }
        
            $args['tax_query'] = $faqs_tax_query;
        }

        if ( ! empty( $atts['exclude'] ) ) {
            $args['post__not_in'] = array_map('intval', explode(',', $atts['exclude']));
        }

        $faqs = get_posts( $args );
        if ( empty( $faqs ) ) return '<p>No FAQs found.</p>';

        // Determine layout
        $layout = ( 'sleuth-ai' === $atts['layout'] ) ? 'sleuth-ai' : 'default';

        $output = '<div class="kiss-faqs">';
        foreach ( $faqs as $index => $faq ) {
            // Q = post_title, A = post_content
            $question = $faq->post_title;
            $answer   = apply_filters( 'the_content', $faq->post_content );
            if ( is_array( $answer ) ) {
                $answer = implode( '', $answer );
            }

            $edit_link = '';
            if ( current_user_can( 'edit_post', $faq->ID ) ) {
                $edit_link = sprintf(
                    '<a href="%s" class="kiss-faq-edit-link" onclick="event.stopPropagation();" aria-label="%s"><span class="dashicons dashicons-edit"></span></a>',
                    esc_url( get_edit_post_link( $faq->ID ) ),
                    esc_attr__( 'Edit FAQ', 'kiss-faqs' )
                );
            }

            // Determine hidden setting
            if ( ! empty( $atts['category'] ) ) {
                // When displaying a category, always show the first FAQ only
                $hidden = $index === 0 ? false : true;
            } else {
                $hidden = ( $index === 0 || 'false' === strtolower( $atts['hidden'] ) ) ? false : true;
            }
            if ( $layout === 'sleuth-ai' ) {
                // Sleuth AI Layout
                $output .= '<div class="kiss-faq-wrapper" style="margin-bottom: 1em;border: 1px solid #e5e5e5;">';
                $output .= '<div class="kiss-faq-question sleuth-ai-layout" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 10px;">';
                $output .= '<span style="font-size: 16px; font-weight: normal;">' . esc_html( $question ) . $edit_link . '</span>';
                $output .= '<span class="kiss-faq-toggle" style="font-size:30px;font-weight:400;">' . ( $hidden ? '+' : '−' ) . '</span>';
                $output .= '</div>';
                $output .= '<div class="kiss-faq-answer" style="' . ( $hidden ? 'display:none;' : 'display:block;' ) . ' padding: 10px; font-size: 14px;text-align:left;">' . wp_kses_post( $answer ) . '</div>';
                $output .= '</div>';
            } else {
                $output .= '<div class="kiss-faq-wrapper" style="margin-bottom: 1em;">';
                $output .= '<div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">';
                $output .= '<span class="kiss-faq-caret ' . ( $hidden ? 'collapsed' : 'expanded' ) . '" style="margin-right: 5px;">' . '<img src="' . plugins_url( 'assets/images/arrow.svg', __FILE__ ) . '" alt="toggle icon"></span>';
                $output .= '<span>' . esc_html( $question ) . '</span>' . $edit_link;
                $output .= '</div>';
                $output .= '<div class="kiss-faq-answer" style="' . ( $hidden ? 'display:none;' : 'display:block; margin-top: 5px;' ) . '">' . wp_kses_post( $answer ) . '</div>';
                $output .= '</div>';
            }

            // Add FAQ to schema
            self::$kiss_faq_schema_data[] = array(
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($answer),
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
            var arrowImg = "<?php echo esc_url( plugins_url( 'assets/images/arrow.svg', __FILE__ ) ); ?>";
            faqWrappers.forEach(function(wrapper){
                var questionElem = wrapper.querySelector('.kiss-faq-question');
                var answerElem   = wrapper.querySelector('.kiss-faq-answer');
                var toggleElem   = wrapper.querySelector('.kiss-faq-caret') || wrapper.querySelector('.kiss-faq-toggle');

                if (questionElem && answerElem && toggleElem) {
                    questionElem.addEventListener('click', function(){
                        if (answerElem.style.display === 'none') {
                            answerElem.style.display = 'block';
                            toggleElem.innerHTML = toggleElem.classList.contains('kiss-faq-caret') ? '<img src="' + arrowImg + '" alt="open">' : '−';
                            toggleElem.classList.remove('collapsed');
                            toggleElem.classList.add('expanded');
                        } else {
                            answerElem.style.display = 'none';
                            toggleElem.innerHTML = toggleElem.classList.contains('kiss-faq-caret') ? '<img src="' + arrowImg + '" alt="open">' : '+';
                            toggleElem.classList.remove('expanded');
                            toggleElem.classList.add('collapsed');
                        }
                    });
                }
            });
        });
        </script>
        <?php
        endif;

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
                'layout' => get_option( 'kiss_faqs_layout_style', 'default' ),
                
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
        if ( is_array( $answer ) ) {
            $answer = implode( '', $answer );
        }

        $edit_link = '';
        if ( current_user_can( 'edit_post', $post->ID ) ) {
            $edit_link = sprintf(
                '<a href="%s" class="kiss-faq-edit-link" onclick="event.stopPropagation();" aria-label="%s"><span class="dashicons dashicons-edit"></span></a>',
                esc_url( get_edit_post_link( $post->ID ) ),
                esc_attr__( 'Edit FAQ', 'kiss-faqs' )
            );
        }

        // Determine hidden setting
        $hidden = ( 'false' === strtolower( $atts['hidden'] ) ) ? false : true;

        $layout = ( 'sleuth-ai' === $atts['layout'] ) ? 'sleuth-ai' : 'default';

        // Output
        ob_start();
        ?>
        <?php if ( $layout === 'sleuth-ai' ) : ?>
            <!-- Sleuth AI Layout -->
            <div class="kiss-faq-wrapper" style="margin-bottom: 1em;border: 1px solid #e5e5e5;">
                <div class="kiss-faq-question sleuth-ai-layout" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid #e5e5e5;">
                    <span style="font-size: 16px; font-weight: normal;"><?php echo esc_html( $question ); ?><?php echo $edit_link; ?></span>
                    <span class="kiss-faq-toggle" style="font-size: 30px;font-weight:400;"><?php echo $hidden ? '+' : '−'; ?></span>
                </div>
                <div class="kiss-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> padding: 10px; font-size: 14px;text-align:left;">
                    <?php echo wp_kses_post( $answer ); ?>
                </div>
            </div>
        <?php else : ?>
            <!-- Default Layout -->
            <div class="kiss-faq-wrapper" style="margin-bottom: 1em;">
                <div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">
                    <span class="kiss-faq-caret <?php echo ($hidden ? 'collapsed' : 'expanded');?>" style="margin-right: 5px;"><?php echo '<img src="' . plugins_url( 'assets/images/arrow.svg', __FILE__ ) . '" alt="toggle icon">'; ?></span>
                    <span><?php echo esc_html( $question ); ?><?php echo $edit_link; ?></span>
                </div>
                <div class="kiss-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> margin-top: 5px;">
                    <?php echo wp_kses_post( $answer ); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php

        // Only add the JS once per page
        static $kiss_faqs_script_added = false;
        if ( ! $kiss_faqs_script_added ) :
            $kiss_faqs_script_added = true;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var faqWrappers = document.querySelectorAll('.kiss-faq-wrapper');
            var arrowImg = "<?php echo esc_url( plugins_url( 'assets/images/arrow.svg', __FILE__ ) ); ?>";
            faqWrappers.forEach(function(wrapper){
                var questionElem = wrapper.querySelector('.kiss-faq-question');
                var answerElem   = wrapper.querySelector('.kiss-faq-answer');
                var toggleElem   = wrapper.querySelector('.kiss-faq-caret') || wrapper.querySelector('.kiss-faq-toggle');

                if (questionElem && answerElem && toggleElem) {
                    questionElem.addEventListener('click', function(){
                        if (answerElem.style.display === 'none') {
                            answerElem.style.display = 'block';
                            toggleElem.innerHTML = toggleElem.classList.contains('kiss-faq-caret') ? '<img src="' + arrowImg + '" alt="open">' : '−';
                            toggleElem.classList.remove('collapsed');
                            toggleElem.classList.add('expanded');
                        } else {
                            answerElem.style.display = 'none';
                            toggleElem.innerHTML = toggleElem.classList.contains('kiss-faq-caret') ? '<img src="' + arrowImg + '" alt="open">' : '+';
                            toggleElem.classList.remove('expanded');
                            toggleElem.classList.add('collapsed');
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
     * Add plugin action links (Settings, All FAQs, and Read Me)
     */
    public function add_plugin_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'edit.php?post_type=kiss_faq&page=kiss_faqs_settings' ) ),
            __( 'Settings', 'kiss-faqs' )
        );

        $all_faqs_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'edit.php?post_type=kiss_faq' ) ),
            __( 'All FAQs', 'kiss-faqs' )
        );

        $readme_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'edit.php?post_type=kiss_faq&page=kiss_faqs_readme' ) ),
            __( 'Read Me', 'kiss-faqs' )
        );

        array_unshift( $links, $settings_link, $all_faqs_link, $readme_link );
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
     * Register plugin settings page under FAQ CPT menu
     */
    public function register_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=kiss_faq',
            __( 'FAQ Settings', 'kiss-faqs' ),
            __( 'Settings', 'kiss-faqs' ),
            'manage_options',
            'kiss_faqs_settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings for the plugin
     */
    public function register_faq_layout_settings() {
        register_setting(
            'kiss_faqs_settings_group', // Option group
            'kiss_faqs_layout_style',   // Option name
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'default', // Default layout
            )
        );

        register_setting(
            'kiss_faqs_settings_group',        // Option group
            'kiss_faqs_global_sitemap_inclusion', // Option name
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'yes', // Default to include in sitemap
            )
        );
    }

    /**
     * Render the plugin settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php printf( esc_html__( 'KISS FAQs Settings (v%s)', 'kiss-faqs' ), $this->plugin_version ); ?></h1>
            <p><?php esc_html_e( 'Here you can configure settings for the KISS FAQs plugin.', 'kiss-faqs' ); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'kiss_faqs_settings_group' ); // Option group
                do_settings_sections( 'kiss_faqs_settings' );  // Page slug (optional sections)
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'FAQ Layout Style', 'kiss-faqs' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="kiss_faqs_layout_style" value="sleuth-ai" <?php checked( get_option( 'kiss_faqs_layout_style', 'default' ), 'sleuth-ai' ); ?> />
                                <?php esc_html_e( 'Use Sleuth AI Layout (checkbox enabled = Sleuth AI style, unchecked = default style)', 'kiss-faqs' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Enable this to match the alternate layout option for the FAQ section.', 'kiss-faqs' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Sitemap Settings', 'kiss-faqs' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="kiss_faqs_global_sitemap_inclusion" value="yes" <?php checked( get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' ), 'yes' ); ?> />
                                <?php esc_html_e( 'Publish All FAQ Posts to Sitemap', 'kiss-faqs' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When unchecked, ALL FAQ posts will be excluded from XML sitemaps regardless of individual post settings. This helps prevent SEO cannibalization.', 'kiss-faqs' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <!-- Self-Tests Section -->
            <hr style="margin: 40px 0 20px 0;">
            <h2><?php esc_html_e( 'Self-Tests & Diagnostics', 'kiss-faqs' ); ?></h2>
            <p><?php esc_html_e( 'Run these tests to validate the sitemap functionality is working correctly:', 'kiss-faqs' ); ?></p>

            <?php $this->render_self_tests(); ?>

            <!-- Cleanup Section -->
            <hr style="margin: 40px 0 20px 0;">
            <h2><?php esc_html_e( 'Cleanup Tools', 'kiss-faqs' ); ?></h2>
            <?php $this->render_cleanup_tools(); ?>
        </div>
        <?php
    }

    /**
     * Render self-tests for sitemap functionality
     */
    public function render_self_tests() {
        ?>
        <div class="kiss-faqs-self-tests">
            <style>
                .kiss-test-result { padding: 10px; margin: 10px 0; border-radius: 4px; }
                .kiss-test-pass { background: #d1e7dd; border: 1px solid #badbcc; color: #0f5132; }
                .kiss-test-fail { background: #f8d7da; border: 1px solid #f5c2c7; color: #842029; }
                .kiss-test-warning { background: #fff3cd; border: 1px solid #ffecb5; color: #664d03; }
                .kiss-test-info { background: #d1ecf1; border: 1px solid #b8daff; color: #055160; }
            </style>

            <?php
            // Test 1: Metabox Registration
            $this->run_test_metabox_registration();

            // Test 2: Global Setting Override
            $this->run_test_global_setting_override();

            // Test 3: Sitemap Exclusion
            $this->run_test_sitemap_exclusion();

            // Test 4: Noindex Tag Generation
            $this->run_test_noindex_functionality();
            ?>
        </div>
        <?php
    }

    /**
     * Test 1: Metabox Registration
     */
    private function run_test_metabox_registration() {
        echo '<h3>' . esc_html__( 'Test 1: Metabox Registration', 'kiss-faqs' ) . '</h3>';

        // Test if add_meta_boxes hook is registered
        $metabox_hook_registered = has_action( 'add_meta_boxes', array( $this, 'add_sitemap_control_metabox' ) );

        if ( $metabox_hook_registered ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Metabox registration hook is properly registered.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-fail">';
            echo '<strong>✗ FAIL:</strong> Metabox registration hook is not registered.';
            echo '</div>';
        }

        // Force trigger metabox registration for testing
        do_action( 'add_meta_boxes', 'kiss_faq', null );

        // Now check if metabox is actually registered
        global $wp_meta_boxes;
        $metabox_exists = isset( $wp_meta_boxes['kiss_faq']['side']['default']['kiss_faq_sitemap_control'] );

        if ( $metabox_exists ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Sitemap control metabox is properly registered for FAQ posts.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-warning">';
            echo '<strong>⚠ WARNING:</strong> Metabox not found in global registry. This may be normal if not on a FAQ edit page.';
            echo '</div>';
        }

        // Test if save_post hook is registered
        $save_post_hooked = has_action( 'save_post', array( $this, 'save_sitemap_control_metabox' ) );
        if ( $save_post_hooked ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Save post hook is registered for metabox data.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-fail">';
            echo '<strong>✗ FAIL:</strong> Save post hook is not registered.';
            echo '</div>';
        }
    }

    /**
     * Test 2: Global Setting Override
     */
    private function run_test_global_setting_override() {
        echo '<h3>' . esc_html__( 'Test 2: Global Setting Override', 'kiss-faqs' ) . '</h3>';

        $global_setting = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );

        echo '<div class="kiss-test-result kiss-test-info">';
        echo '<strong>INFO:</strong> Current global setting: <code>' . esc_html( $global_setting ) . '</code>';
        echo '</div>';

        // Test the logic
        if ( 'no' === $global_setting ) {
            echo '<div class="kiss-test-result kiss-test-warning">';
            echo '<strong>⚠ WARNING:</strong> Global sitemap inclusion is DISABLED. All FAQ posts will be excluded from sitemaps regardless of individual settings.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Global sitemap inclusion is ENABLED. Individual post settings will be respected.';
            echo '</div>';
        }

        // Test settings registration
        $registered_settings = get_registered_settings();
        if ( isset( $registered_settings['kiss_faqs_global_sitemap_inclusion'] ) ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Global sitemap setting is properly registered.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-fail">';
            echo '<strong>✗ FAIL:</strong> Global sitemap setting is not registered.';
            echo '</div>';
        }
    }

    /**
     * Test 3: Sitemap Exclusion
     */
    private function run_test_sitemap_exclusion() {
        echo '<h3>' . esc_html__( 'Test 3: Sitemap Exclusion', 'kiss-faqs' ) . '</h3>';

        // Check if sitemap filter is hooked
        $filter_hooked = has_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_faqs_from_sitemap' ) );

        if ( $filter_hooked ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Sitemap exclusion filter is properly hooked.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-fail">';
            echo '<strong>✗ FAIL:</strong> Sitemap exclusion filter is not hooked.';
            echo '</div>';
        }

        // Test with sample data
        $excluded_posts = $this->get_faqs_excluded_from_sitemap();
        $total_faqs = wp_count_posts( 'kiss_faq' );
        $published_faqs = isset( $total_faqs->publish ) ? $total_faqs->publish : 0;

        echo '<div class="kiss-test-result kiss-test-info">';
        echo '<strong>INFO:</strong> Total published FAQ posts: <code>' . esc_html( $published_faqs ) . '</code><br>';
        echo '<strong>INFO:</strong> Posts excluded from sitemap: <code>' . count( $excluded_posts ) . '</code>';
        if ( ! empty( $excluded_posts ) ) {
            echo ' (IDs: ' . esc_html( implode( ', ', $excluded_posts ) ) . ')';
        }
        echo '</div>';

        // Test sitemap URL accessibility
        $sitemap_url = home_url( '/wp-sitemap-posts-kiss_faq-1.xml' );
        echo '<div class="kiss-test-result kiss-test-info">';
        echo '<strong>INFO:</strong> FAQ sitemap URL: <a href="' . esc_url( $sitemap_url ) . '" target="_blank">' . esc_html( $sitemap_url ) . '</a>';
        echo '</div>';
    }

    /**
     * Test 4: Noindex Tag Generation
     */
    private function run_test_noindex_functionality() {
        echo '<h3>' . esc_html__( 'Test 4: Noindex Tag Generation', 'kiss-faqs' ) . '</h3>';

        // Check if wp_head hook is registered
        $head_hooked = has_action( 'wp_head', array( $this, 'add_noindex_for_excluded_faqs' ) );

        if ( $head_hooked ) {
            echo '<div class="kiss-test-result kiss-test-pass">';
            echo '<strong>✓ PASS:</strong> Noindex functionality is hooked to wp_head.';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-fail">';
            echo '<strong>✗ FAIL:</strong> Noindex functionality is not hooked to wp_head.';
            echo '</div>';
        }

        // Get a sample excluded post for testing
        $excluded_posts = $this->get_faqs_excluded_from_sitemap();
        $global_setting = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );

        if ( 'no' === $global_setting ) {
            echo '<div class="kiss-test-result kiss-test-warning">';
            echo '<strong>⚠ INFO:</strong> Global setting is disabled - ALL FAQ posts will have noindex tags.';
            echo '</div>';
        } elseif ( ! empty( $excluded_posts ) ) {
            $sample_post_id = $excluded_posts[0];
            $sample_post_url = get_permalink( $sample_post_id );
            echo '<div class="kiss-test-result kiss-test-info">';
            echo '<strong>INFO:</strong> Sample excluded post for manual testing: ';
            echo '<a href="' . esc_url( $sample_post_url ) . '" target="_blank">Post ID ' . esc_html( $sample_post_id ) . '</a>';
            echo '<br><small>Visit this URL and check HTML source for: &lt;meta name="robots" content="noindex, nofollow" /&gt;</small>';
            echo '</div>';
        } else {
            echo '<div class="kiss-test-result kiss-test-info">';
            echo '<strong>INFO:</strong> No posts are currently excluded from sitemap. Create a test post and set it to "No" for sitemap inclusion to test noindex functionality.';
            echo '</div>';
        }
    }

    /**
     * Render cleanup tools
     */
    public function render_cleanup_tools() {
        // Handle cleanup action
        if ( isset( $_POST['kiss_faq_cleanup_test_posts'] ) &&
             wp_verify_nonce( $_POST['kiss_faq_cleanup_nonce'], 'kiss_faq_cleanup_action' ) ) {
            $this->cleanup_test_faq_posts();
        }

        $test_posts = $this->get_test_faq_posts();
        ?>
        <div class="kiss-faq-cleanup-tools">
            <?php if ( ! empty( $test_posts ) ) : ?>
                <div class="kiss-test-result kiss-test-warning">
                    <strong>⚠ WARNING:</strong> Found <?php echo count( $test_posts ); ?> potential test FAQ posts:
                    <ul style="margin: 10px 0;">
                        <?php foreach ( $test_posts as $post ) : ?>
                            <li>
                                <strong><?php echo esc_html( $post->post_title ); ?></strong>
                                (ID: <?php echo $post->ID; ?>, Created: <?php echo esc_html( $post->post_date ); ?>)
                                - <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Edit</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field( 'kiss_faq_cleanup_action', 'kiss_faq_cleanup_nonce' ); ?>
                    <input type="submit" name="kiss_faq_cleanup_test_posts"
                           class="button button-secondary"
                           value="<?php esc_attr_e( 'Delete Test FAQ Posts', 'kiss-faqs' ); ?>"
                           onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete these test posts? This action cannot be undone.', 'kiss-faqs' ); ?>');" />
                    <p class="description">
                        <?php esc_html_e( 'This will permanently delete FAQ posts that appear to be test posts (containing "test", "sample", "demo", etc. in the title).', 'kiss-faqs' ); ?>
                    </p>
                </form>
            <?php else : ?>
                <div class="kiss-test-result kiss-test-pass">
                    <strong>✓ CLEAN:</strong> No test FAQ posts detected.
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get potential test FAQ posts
     */
    private function get_test_faq_posts() {
        $test_keywords = array( 'test', 'sample', 'demo', 'example', 'dummy', 'lorem', 'ipsum' );

        $args = array(
            'post_type' => 'kiss_faq',
            'posts_per_page' => -1,
            'post_status' => array( 'publish', 'draft', 'private' ),
            'meta_query' => array(
                'relation' => 'OR',
            )
        );

        // Add title search for test keywords
        $title_search = array();
        foreach ( $test_keywords as $keyword ) {
            $title_search[] = array(
                'key' => 'post_title',
                'value' => $keyword,
                'compare' => 'LIKE'
            );
        }

        $all_posts = get_posts( array(
            'post_type' => 'kiss_faq',
            'posts_per_page' => -1,
            'post_status' => array( 'publish', 'draft', 'private' )
        ) );

        $test_posts = array();
        foreach ( $all_posts as $post ) {
            $title_lower = strtolower( $post->post_title );
            foreach ( $test_keywords as $keyword ) {
                if ( strpos( $title_lower, $keyword ) !== false ) {
                    $test_posts[] = $post;
                    break;
                }
            }
        }

        return $test_posts;
    }

    /**
     * Cleanup test FAQ posts
     */
    private function cleanup_test_faq_posts() {
        $test_posts = $this->get_test_faq_posts();
        $deleted_count = 0;

        foreach ( $test_posts as $post ) {
            if ( wp_delete_post( $post->ID, true ) ) { // true = force delete (skip trash)
                $deleted_count++;
            }
        }

        if ( $deleted_count > 0 ) {
            echo '<div class="notice notice-success"><p>';
            printf(
                esc_html__( 'Successfully deleted %d test FAQ posts.', 'kiss-faqs' ),
                $deleted_count
            );
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'No test posts were deleted. Please check permissions.', 'kiss-faqs' );
            echo '</p></div>';
        }
    }

    /**
     * Output JSON-LD schema for FAQs in the footer.
     *
     * This only runs if FAQs were rendered on the page.
     */
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

    /**
     * Add sitemap control metabox to FAQ post editor
     */
    public function add_sitemap_control_metabox() {
        add_meta_box(
            'kiss_faq_sitemap_control',
            __( 'Sitemap Settings', 'kiss-faqs' ),
            array( $this, 'render_sitemap_control_metabox' ),
            'kiss_faq',
            'side',
            'default'
        );
    }

    /**
     * Render the sitemap control metabox
     */
    public function render_sitemap_control_metabox( $post ) {
        // Add nonce for security
        wp_nonce_field( 'kiss_faq_sitemap_control_nonce', 'kiss_faq_sitemap_control_nonce' );

        // Get current value (default to 'yes' for new posts)
        $include_in_sitemap = get_post_meta( $post->ID, '_kiss_faq_include_in_sitemap', true );
        if ( empty( $include_in_sitemap ) ) {
            $include_in_sitemap = 'yes';
        }

        // Check if global setting overrides individual setting
        $global_sitemap_enabled = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );
        $is_globally_disabled = ( 'no' === $global_sitemap_enabled );

        ?>
        <table class="form-table">
            <tr>
                <td>
                    <label for="kiss_faq_include_in_sitemap">
                        <?php esc_html_e( 'Publish to Sitemap:', 'kiss-faqs' ); ?>
                    </label>
                    <select name="kiss_faq_include_in_sitemap" id="kiss_faq_include_in_sitemap"
                            <?php echo $is_globally_disabled ? 'disabled' : ''; ?>>
                        <option value="yes" <?php selected( $include_in_sitemap, 'yes' ); ?>>
                            <?php esc_html_e( 'Yes', 'kiss-faqs' ); ?>
                        </option>
                        <option value="no" <?php selected( $include_in_sitemap, 'no' ); ?>>
                            <?php esc_html_e( 'No', 'kiss-faqs' ); ?>
                        </option>
                    </select>
                    <?php if ( $is_globally_disabled ) : ?>
                        <p class="description" style="color: #d63638;">
                            <?php esc_html_e( 'Global sitemap inclusion is disabled. This setting is overridden.', 'kiss-faqs' ); ?>
                        </p>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e( 'Choose whether this FAQ should be included in XML sitemaps.', 'kiss-faqs' ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the sitemap control metabox data
     */
    public function save_sitemap_control_metabox( $post_id ) {
        // Check if nonce is valid
        if ( ! isset( $_POST['kiss_faq_sitemap_control_nonce'] ) ||
             ! wp_verify_nonce( $_POST['kiss_faq_sitemap_control_nonce'], 'kiss_faq_sitemap_control_nonce' ) ) {
            return;
        }

        // Check if user has permission to edit the post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check if this is an autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check if this is the correct post type
        if ( get_post_type( $post_id ) !== 'kiss_faq' ) {
            return;
        }

        // Save the sitemap inclusion setting
        if ( isset( $_POST['kiss_faq_include_in_sitemap'] ) ) {
            $include_in_sitemap = sanitize_text_field( $_POST['kiss_faq_include_in_sitemap'] );
            if ( in_array( $include_in_sitemap, array( 'yes', 'no' ), true ) ) {
                update_post_meta( $post_id, '_kiss_faq_include_in_sitemap', $include_in_sitemap );
            }
        }
    }

    /**
     * Register README viewer page
     */
    public function register_readme_viewer() {
        add_submenu_page(
            'edit.php?post_type=kiss_faq',
            __( 'Read Me', 'kiss-faqs' ),
            __( 'Read Me', 'kiss-faqs' ),
            'manage_options',
            'kiss_faqs_readme',
            array( $this, 'render_readme_viewer' )
        );
    }

    /**
     * Render the README viewer page
     */
    public function render_readme_viewer() {
        $readme_path = plugin_dir_path( __FILE__ ) . 'README.md';
        $readme_content = '';

        if ( file_exists( $readme_path ) ) {
            $readme_content = file_get_contents( $readme_path );
        }

        ?>
        <div class="wrap">
            <h1><?php printf( esc_html__( 'KISS FAQs - Read Me (v%s)', 'kiss-faqs' ), $this->plugin_version ); ?></h1>

            <?php if ( empty( $readme_content ) ) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'README.md file not found.', 'kiss-faqs' ); ?></p>
                </div>
            <?php else : ?>
                <div class="kiss-readme-content" style="max-width: 800px; line-height: 1.6;">
                    <?php echo $this->markdown_to_html( $readme_content ); ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .kiss-readme-content h1 { font-size: 2em; margin: 1em 0 0.5em 0; border-bottom: 2px solid #ddd; padding-bottom: 0.3em; }
            .kiss-readme-content h2 { font-size: 1.5em; margin: 1.5em 0 0.5em 0; border-bottom: 1px solid #eee; padding-bottom: 0.2em; }
            .kiss-readme-content h3 { font-size: 1.3em; margin: 1.2em 0 0.4em 0; }
            .kiss-readme-content h4 { font-size: 1.1em; margin: 1em 0 0.3em 0; }
            .kiss-readme-content p { margin: 1em 0; }
            .kiss-readme-content ul, .kiss-readme-content ol { margin: 1em 0; padding-left: 2em; }
            .kiss-readme-content li { margin: 0.5em 0; }
            .kiss-readme-content code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
            .kiss-readme-content pre { background: #f4f4f4; padding: 1em; border-radius: 5px; overflow-x: auto; }
            .kiss-readme-content pre code { background: none; padding: 0; }
            .kiss-readme-content blockquote { border-left: 4px solid #ddd; margin: 1em 0; padding: 0.5em 1em; background: #f9f9f9; }
            .kiss-readme-content strong { font-weight: bold; }
            .kiss-readme-content em { font-style: italic; }
            .kiss-readme-content a { color: #0073aa; text-decoration: none; }
            .kiss-readme-content a:hover { text-decoration: underline; }
            .kiss-readme-content hr { border: none; border-top: 1px solid #ddd; margin: 2em 0; }
        </style>
        <?php
    }

    /**
     * Simple markdown to HTML converter
     */
    private function markdown_to_html( $markdown ) {
        // Escape HTML first
        $html = esc_html( $markdown );

        // Headers
        $html = preg_replace( '/^#### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $html );
        $html = preg_replace( '/^# (.+)$/m', '<h1>$1</h1>', $html );

        // Bold and italic
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );

        // Code blocks (triple backticks)
        $html = preg_replace( '/```([^`]+)```/s', '<pre><code>$1</code></pre>', $html );

        // Inline code
        $html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );

        // Links
        $html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html );

        // Horizontal rules
        $html = preg_replace( '/^---$/m', '<hr>', $html );

        // Lists (simple implementation)
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/^(\d+)\. (.+)$/m', '<li>$2</li>', $html );

        // Wrap consecutive <li> elements in <ul>
        $html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
        $html = preg_replace( '/<\/ul>\s*<ul>/', '', $html ); // Merge consecutive lists

        // Blockquotes
        $html = preg_replace( '/^> (.+)$/m', '<blockquote>$1</blockquote>', $html );

        // Paragraphs (convert double line breaks to paragraphs)
        $html = preg_replace( '/\n\n/', '</p><p>', $html );
        $html = '<p>' . $html . '</p>';

        // Clean up empty paragraphs and fix formatting
        $html = preg_replace( '/<p><\/p>/', '', $html );
        $html = preg_replace( '/<p>(<h[1-6]>)/', '$1', $html );
        $html = preg_replace( '/(<\/h[1-6]>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<hr>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<ul>)/', '$1', $html );
        $html = preg_replace( '/(<\/ul>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<blockquote>)/', '$1', $html );
        $html = preg_replace( '/(<\/blockquote>)<\/p>/', '$1', $html );
        $html = preg_replace( '/<p>(<pre>)/', '$1', $html );
        $html = preg_replace( '/(<\/pre>)<\/p>/', '$1', $html );

        return $html;
    }

    /**
     * Exclude FAQ posts from sitemap based on settings
     */
    public function exclude_faqs_from_sitemap( $args, $post_type ) {
        // Only apply to kiss_faq post type
        if ( 'kiss_faq' !== $post_type ) {
            return $args;
        }

        // Check global setting first
        $global_sitemap_enabled = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );
        if ( 'no' === $global_sitemap_enabled ) {
            // Global setting disabled - exclude all FAQ posts
            $args['post__in'] = array( 0 ); // This will return no posts
            return $args;
        }

        // Global setting is enabled, check individual post settings
        $excluded_posts = $this->get_faqs_excluded_from_sitemap();
        if ( ! empty( $excluded_posts ) ) {
            $args['post__not_in'] = $excluded_posts;
        }

        return $args;
    }

    /**
     * Get FAQ posts that should be excluded from sitemap
     */
    private function get_faqs_excluded_from_sitemap() {
        global $wpdb;

        $excluded_posts = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = %s
             AND pm.meta_value = %s
             AND p.post_type = %s
             AND p.post_status = 'publish'",
            '_kiss_faq_include_in_sitemap',
            'no',
            'kiss_faq'
        ) );

        return array_map( 'intval', $excluded_posts );
    }

    /**
     * Add noindex meta tag for FAQ posts excluded from sitemap
     */
    public function add_noindex_for_excluded_faqs() {
        // Only run on single FAQ posts
        if ( ! is_singular( 'kiss_faq' ) ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }

        // Check if this post should be excluded from sitemap
        $should_exclude = $this->should_exclude_faq_from_sitemap( $post_id );

        if ( $should_exclude ) {
            echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
        }
    }

    /**
     * Check if a specific FAQ post should be excluded from sitemap
     */
    private function should_exclude_faq_from_sitemap( $post_id ) {
        // Check global setting first
        $global_sitemap_enabled = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );
        if ( 'no' === $global_sitemap_enabled ) {
            return true; // Global setting overrides individual setting
        }

        // Check individual post setting
        $include_in_sitemap = get_post_meta( $post_id, '_kiss_faq_include_in_sitemap', true );

        // Default to 'yes' if not set
        if ( empty( $include_in_sitemap ) ) {
            $include_in_sitemap = 'yes';
        }

        return ( 'no' === $include_in_sitemap );
    }
}

// Initialize the plugin
KISSFAQsWithSchema::init();

/*
Changelog:
1.04.7 - Fix layout rendering syntax and add docblocks.
1.04.6 - Fixed syntax error in update checker and bumped version.
1.04.5 - Added admin category column, front-end edit icon, and PHPDoc comments.
*/
