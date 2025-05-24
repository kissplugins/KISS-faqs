<?php
/**
 * Plugin Name: KISS FAQs with Schema
 * Plugin URI:  https://KISSplugins.com
 * Description: Manage and display FAQs (Question = Post Title, Answer = Post Content Editor) with Google's Structured Data. Shortcode: [KISSFAQ post="ID"]. Safari-friendly toggle, displays FAQ ID in editor, and now has a column showing the shortcode/post ID.
 * Version: 1.05
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
require plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/kissplugins/KISS-faqs',
    __FILE__,
    'kiss-faqs'
);
// Optional: Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

/**
 * Main class for KISS FAQs with Schema plugin.
 * Handles CPT registration, shortcodes, schema output, and admin functionalities.
 */
class KISSFAQsWithSchema {

    /**
     * Singleton instance of the class.
     * @var KISSFAQsWithSchema|null
     */
    private static $instance = null;

    /**
     * Plugin version.
     * @var string
     */
    public $plugin_version = '1.05';

    /**
     * Legacy database table name.
     * @var string
     */
    public $db_table_name  = 'KISSFAQs'; // Table name (legacy)

    /**
     * Array to store schema data for output in the footer.
     * @var array
     */
    private static $kiss_faq_schema_data = array();

    /**
     * Initializes the plugin by setting up hooks and actions.
     * Singleton Instance.
     *
     * @return KISSFAQsWithSchema The singleton instance of this class.
     */
    public static function init() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new KISSFAQsWithSchema();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     * Private to ensure singleton pattern. Sets up WordPress hooks.
     */
    private function __construct() {
        // Activation/Deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );

        add_action('wp_head', array($this, 'add_faqs_inline_css'));

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

        // Add a column in the CPT listing to show the shortcode/post ID
        add_filter( 'manage_kiss_faq_posts_columns', array( $this, 'add_shortcode_column' ) );
        add_action( 'manage_kiss_faq_posts_custom_column', array( $this, 'render_shortcode_column' ), 10, 2 );

        // Add a column in the CPT listing to show categories
        add_filter( 'manage_kiss_faq_posts_columns', array( $this, 'add_faq_categories_column' ) );
        add_action( 'manage_kiss_faq_posts_custom_column', array( $this, 'render_faq_categories_column' ), 10, 2 );

        add_action('wp_footer', array($this, 'output_kiss_faq_schema'),999);
    }

    /**
     * Adds inline CSS to the head for FAQ styling.
     *
     * @return void
     */
    public function add_faqs_inline_css() {
        ?>
        <style>
            .kiss-faq-caret img{
                width: 12px;
            }
            .kiss-faq-caret.collapsed img{
                transform: translate(0, -2px) rotateZ(270deg);
            }
            .kiss-faq-caret.expanded img{
                transform: unset;
            }
        </style>
        <?php
    }


    /**
     * Plugin activation tasks.
     * Creates a legacy database table if it doesn't exist and checks for legacy data.
     *
     * @return void
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
     * Plugin deactivation tasks.
     * Currently does nothing.
     *
     * @return void
     */
    public function deactivate_plugin() {
        // e.g., do nothing or flush_rewrite_rules();
    }

    /**
     * Checks for legacy data in the old database table and updates an option accordingly.
     *
     * @return void
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
     * Displays an admin notice if legacy data is found.
     *
     * @return void
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
     * Registers the Custom Post Type for FAQs.
     * Question = post title, Answer = post content.
     *
     * @return void
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
     * Registers the 'faq_category' taxonomy for the 'kiss_faq' post type.
     *
     * @return void
     */
    public function register_faq_category_taxonomy() {
        register_taxonomy( 'faq_category', 'kiss_faq', array(
            'label' => __( 'Categories', 'kiss-faqs' ),
            'rewrite' => array( 'slug' => 'faq-category' ),
            'hierarchical' => true,
        ));
    }

    /**
     * Displays the FAQ ID in the post editor screen.
     *
     * @param WP_Post $post The current post object.
     * @return void
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
     * Helper function to generate the edit link for admins/editors.
     *
     * @param int $post_id The ID of the post to generate an edit link for.
     * @return string HTML for the edit link, or empty string if user cannot edit.
     */
    private function get_faq_edit_link( $post_id ) {
        if ( current_user_can( 'edit_post', $post_id ) ) {
            return sprintf(
                ' <a href="%s" title="%s" style="margin-left: 5px; text-decoration: none;" target="_blank"><i class="fas fa-pencil-alt"></i></a>',
                esc_url( get_edit_post_link( $post_id ) ),
                esc_attr__( 'Edit FAQ', 'kiss-faqs' )
            );
        }
        return '';
    }

    /**
     * Renders all FAQs based on shortcode attributes.
     * Shortcode: [KISSFAQS category="cat_slug" sub-category="sub_cat_slug" exclude="123,143" hidden="true" layout="default|sleuth-ai"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for all FAQs.
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
            $edit_link = $this->get_faq_edit_link( $faq->ID );

            // Determine hidden setting
            $hidden = ('false' !== strtolower( $atts['hidden'] ) );

            if ( $layout === 'sleuth-ai' ) {
                // Sleuth AI Layout
                $output .= '<div class="kiss-faq-wrapper" style="margin-bottom: 1em;border: 1px solid #e5e5e5;">';
                $output .= '<div class="kiss-faq-question sleuth-ai-layout" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 10px;">
                                <span style="font-size: 16px; font-weight: normal;">' . esc_html($question) . $edit_link . '</span>
                                <span class="kiss-faq-toggle" style="font-size: 30px;font-weight:400;">' . ($hidden ? '+' : '−') . '</span>
                            </div>';
                $output .= '<div class="kiss-faq-answer" style="' . ($hidden ? 'display:none;' : 'display:block;') . ' padding: 10px; font-size: 14px;text-align:left;">
                                ' . wp_kses_post($answer) . '
                            </div>';
                $output .= '</div>';
            } else {
                $output .= '<div class="kiss-faq-wrapper" style="margin-bottom: 1em;">';
                $output .= '<div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">
                                <span class="kiss-faq-caret '.($hidden ? 'collapsed' : 'expanded').'" style="margin-right: 5px;">' .'<img src="' . plugins_url( 'assets/images/arrow.svg', __FILE__ ) . '" alt="toggle icon"></span>
                                <span>' . esc_html($question) . $edit_link . '</span>
                            </div>';
                $output .= '<div class="kiss-faq-answer" style="' . ($hidden ? 'display:none;' : 'display:block; margin-top: 5px;') . '">
                                ' . wp_kses_post($answer) . '
                            </div>';
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
                    questionElem.addEventListener('click', function(e){
                        // Prevent click on edit link from toggling the answer
                        if (e.target.closest('a')) {
                            return;
                        }

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
     * Renders a single FAQ based on post ID.
     * Shortcode: [KISSFAQ post="123" hidden="true" layout="default|sleuth-ai"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for a single FAQ.
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
        $edit_link = $this->get_faq_edit_link( $faq_id );


        // Determine hidden setting
        $hidden = ( 'false' === strtolower( $atts['hidden'] ) ) ? false : true;

        $layout = ( 'sleuth-ai' === $atts['layout'] ) ? 'sleuth-ai' : 'default';

        // Output
        ob_start();
        ?>
        <?php if ( $layout === 'sleuth-ai' ) : ?>
            <div class="kiss-faq-wrapper" style="margin-bottom: 1em;border: 1px solid #e5e5e5;">
                <div class="kiss-faq-question sleuth-ai-layout" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 10px;">
                    <span style="font-size: 16px; font-weight: normal;"><?php echo esc_html( $question ); ?><?php echo $edit_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_faq_edit_link ?></span>
                    <span class="kiss-faq-toggle" style="font-size: 30px;font-weight:400;"><?php echo $hidden ? '+' : '−'; ?></span>
                </div>
                <div class="kiss-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> padding: 10px; font-size: 14px;text-align:left;">
                    <?php echo wp_kses_post( $answer ); ?>
                </div>
            </div>
        <?php else : ?>
            <div class="kiss-faq-wrapper" style="margin-bottom: 1em;">
                <div class="kiss-faq-question" style="cursor: pointer; font-weight: bold;">
                    <span class="kiss-faq-caret <?php echo ($hidden ? 'collapsed' : 'expanded');?>" style="margin-right: 5px;"><?php echo '<img src="' . plugins_url( 'assets/images/arrow.svg', __FILE__ ) . '" alt="toggle icon">'; ?></span>
                    <span><?php echo esc_html( $question ); ?><?php echo $edit_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_faq_edit_link ?></span>
                </div>
                <div class="kiss-faq-answer" style="<?php echo $hidden ? 'display:none;' : 'display:block;'; ?> margin-top: 5px;">
                    <?php echo wp_kses_post( $answer ); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php

        // Only add the JS once per page
        static $kiss_faqs_script_added_single = false;
        if ( ! $kiss_faqs_script_added_single ) :
            $kiss_faqs_script_added_single = true;
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
                    questionElem.addEventListener('click', function(e){
                        // Prevent click on edit link from toggling the answer
                        if (e.target.closest('a')) {
                            return;
                        }

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
     * Adds a link to the "All FAQs" page on the plugin action links.
     *
     * @param array $links An array of plugin action links.
     * @return array An array of plugin action links.
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
     * Adds the 'Shortcode' column to the FAQ CPT list table.
     *
     * @param array $columns An array of columns.
     * @return array An array of columns.
     */
    public function add_shortcode_column( $columns ) {
        // We add a custom column at the end
        $columns['kiss_faq_shortcode'] = __( 'Shortcode', 'kiss-faqs' );
        return $columns;
    }

    /**
     * Renders the content for the 'Shortcode' column in the FAQ CPT list table.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the current post.
     * @return void
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
     * Adds the 'Categories' column to the FAQ CPT list table.
     *
     * @param array $columns An array of columns.
     * @return array An array of columns.
     */
    public function add_faq_categories_column( $columns ) {
        $columns['faq_categories'] = __( 'Categories', 'kiss-faqs' );
        return $columns;
    }

    /**
     * Renders the content for the 'Categories' column in the FAQ CPT list table.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the current post.
     * @return void
     */
    public function render_faq_categories_column( $column, $post_id ) {
        if ( 'faq_categories' === $column ) {
            $terms = get_the_terms( $post_id, 'faq_category' );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $category_names = wp_list_pluck( $terms, 'name' );
                echo esc_html( implode( ', ', $category_names ) );
            } else {
                echo '—'; // No categories
            }
        }
    }

    /**
     * Registers the plugin settings page under "Settings" menu.
     *
     * @return void
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
     * Registers plugin settings, specifically the layout style option.
     *
     * @return void
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
    }

    /**
     * Renders the plugin settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'KISS FAQs Settings', 'kiss-faqs' ); ?></h1>
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
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Outputs the collected FAQ schema data in JSON-LD format in the footer.
     *
     * @return void
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
}

// Initialize the plugin
KISSFAQsWithSchema::init();