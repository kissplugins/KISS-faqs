<?php
/**
 * Test file for KISS FAQs sitemap functionality
 * This file can be used to test the new sitemap control features
 * 
 * To test:
 * 1. Create some FAQ posts
 * 2. Set some to "No" for sitemap inclusion
 * 3. Check the sitemap at /wp-sitemap-posts-kiss_faq-1.xml
 * 4. Visit individual FAQ posts to check for noindex tags
 */

// This is a test/documentation file - not meant to be executed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Test scenarios for the new sitemap functionality:
 * 
 * 1. INDIVIDUAL POST CONTROL:
 *    - Create a new FAQ post
 *    - In the "Sitemap Settings" metabox, set "Publish to Sitemap" to "No"
 *    - Save the post
 *    - Check that the post doesn't appear in /wp-sitemap-posts-kiss_faq-1.xml
 *    - Visit the post directly and check HTML source for <meta name="robots" content="noindex, nofollow" />
 * 
 * 2. GLOBAL SETTING OVERRIDE:
 *    - Go to Settings > KISS FAQs
 *    - Uncheck "Publish All FAQ Posts to Sitemap"
 *    - Save settings
 *    - Check that NO FAQ posts appear in /wp-sitemap-posts-kiss_faq-1.xml
 *    - Visit any FAQ post and check for noindex tag
 *    - Individual post settings should be disabled/overridden
 * 
 * 3. MIXED SETTINGS:
 *    - Enable global sitemap inclusion
 *    - Set some posts to "Yes" and others to "No" for sitemap inclusion
 *    - Only posts set to "Yes" should appear in sitemap
 *    - Posts set to "No" should have noindex tags
 * 
 * 4. DEFAULT BEHAVIOR:
 *    - New posts should default to "Yes" (included in sitemap)
 *    - Existing posts without the meta should default to "Yes"
 */

/**
 * Quick validation functions (for debugging):
 */

function kiss_faq_test_sitemap_exclusion() {
    // Get all FAQ posts
    $faqs = get_posts( array(
        'post_type' => 'kiss_faq',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ) );

    echo "<h3>FAQ Sitemap Status Test</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Post ID</th><th>Title</th><th>Individual Setting</th><th>Should be in Sitemap?</th></tr>";

    $global_setting = get_option( 'kiss_faqs_global_sitemap_inclusion', 'yes' );
    
    foreach ( $faqs as $faq ) {
        $individual_setting = get_post_meta( $faq->ID, '_kiss_faq_include_in_sitemap', true );
        if ( empty( $individual_setting ) ) {
            $individual_setting = 'yes'; // default
        }
        
        $should_be_in_sitemap = ( 'yes' === $global_setting && 'yes' === $individual_setting ) ? 'YES' : 'NO';
        
        echo "<tr>";
        echo "<td>{$faq->ID}</td>";
        echo "<td>" . esc_html( $faq->post_title ) . "</td>";
        echo "<td>{$individual_setting}</td>";
        echo "<td>{$should_be_in_sitemap}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<p><strong>Global Setting:</strong> {$global_setting}</p>";
}

/**
 * Test the sitemap query args filter
 */
function kiss_faq_test_sitemap_query_args() {
    $plugin = KISSFAQsWithSchema::init();
    
    // Simulate the sitemap query args
    $args = array(
        'post_type' => 'kiss_faq',
        'post_status' => 'publish',
        'posts_per_page' => 2000,
    );
    
    $filtered_args = $plugin->exclude_faqs_from_sitemap( $args, 'kiss_faq' );
    
    echo "<h3>Sitemap Query Args Test</h3>";
    echo "<p><strong>Original args:</strong></p>";
    echo "<pre>" . print_r( $args, true ) . "</pre>";
    echo "<p><strong>Filtered args:</strong></p>";
    echo "<pre>" . print_r( $filtered_args, true ) . "</pre>";
}

// Uncomment these lines to run tests (add to a page or post):
// kiss_faq_test_sitemap_exclusion();
// kiss_faq_test_sitemap_query_args();
