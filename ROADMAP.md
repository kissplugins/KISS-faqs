```markdown
# KISS FAQs Security & Performance Roadmap

## Overview
This roadmap outlines critical security vulnerabilities and performance improvements for the KISS FAQs plugin, organized into three phases based on priority and impact.

---

## Phase 1: Critical Security Fixes (Immediate)
**Timeline: Implement immediately before any production use**

### 1.1 Fix XSS Vulnerabilities in JavaScript Context
- **Issue**: Improper escaping of URLs in JavaScript context could lead to XSS attacks
- **Location**: Inline JavaScript generation in `render_faq_shortcode()` and `render_all_faqs_shortcode()`
- **Current Code**:
  ```php
  var arrowImg = "<?php echo esc_url( plugins_url( 'assets/images/arrow.svg', __FILE__ ) ); ?>";
  ```
- **Fix Required**:
  ```php
  var arrowImg = <?php echo wp_json_encode( plugins_url( 'assets/images/arrow.svg', __FILE__ ) ); ?>;
  ```

### 1.2 Add Missing Capability Checks
- **Issue**: Administrative functions lack proper permission verification
- **Location**: `cleanup_test_faq_posts()` method
- **Fix Required**: Add capability check at the beginning of the method:
  ```php
  if ( ! current_user_can( 'delete_posts' ) ) {
      wp_die( __( 'You do not have sufficient permissions to perform this action.', 'kiss-faqs' ) );
  }
  ```

### 1.3 Implement Query Limits
- **Issue**: Unbounded database queries can cause memory exhaustion
- **Location**: `render_all_faqs_shortcode()` method
- **Current Code**:
  ```php
  'posts_per_page' => -1,  // Gets ALL posts
  ```
- **Fix Required**:
  ```php
  'posts_per_page' => apply_filters( 'kiss_faq_query_limit', 100 ),
  ```
- **Additional**: Add pagination support for large FAQ collections

### 1.4 Validate Shortcode Attributes
- **Issue**: Insufficient validation of user input in shortcodes
- **Location**: All shortcode handlers
- **Fix Required**:
  - Limit the number of excluded IDs (max 50)
  - Validate category slugs against allowed characters
  - Sanitize all input attributes properly

---

## Phase 2: Performance Optimization
**Timeline: Complete within 2-4 weeks**

### 2.1 Implement Database Query Caching
- **Issue**: Repeated database queries for same FAQ data
- **Solution**: Implement transient caching
  ```php
  $cache_key = 'kiss_faqs_' . md5( serialize( $args ) );
  $faqs = get_transient( $cache_key );
  if ( false === $faqs ) {
      $faqs = get_posts( $args );
      set_transient( $cache_key, $faqs, HOUR_IN_SECONDS );
  }
  ```

### 2.2 Optimize Asset Loading
- **Issue**: JavaScript repeated inline for each shortcode instance
- **Solution**:
  - Move JavaScript to external file
  - Enqueue once per page
  - Pass dynamic data via `wp_localize_script()`

### 2.3 Improve Markdown Parser Efficiency
- **Issue**: Multiple regex passes over content
- **Solution**:
  - Combine related regex operations
  - Consider using WordPress's built-in Parsedown library
  - Add caching for parsed README content

### 2.4 Optimize Schema Generation
- **Issue**: Schema data built for every FAQ even if not needed
- **Solution**:
  - Only generate schema when FAQs are actually displayed
  - Combine multiple FAQ schemas into single FAQPage output

---

## Phase 3: Security Hardening & Best Practices
**Timeline: Complete within 4-6 weeks**

### 3.1 Enhance SQL Security
- **Issue**: Direct table name concatenation could be vulnerable if made dynamic
- **Location**: `activate_plugin()` method
- **Solution**:
  - Use prepared statements consistently
  - Validate table name format
  - Consider removing legacy table support entirely

### 3.2 Add Rate Limiting
- **Issue**: No protection against abuse of resource-intensive operations
- **Solution**:
  - Implement rate limiting for:
    - Cleanup operations
    - Bulk FAQ queries
    - Admin AJAX requests
  - Use WordPress transients for tracking

### 3.3 Improve Input Sanitization
- **Comprehensive Review**:
  - Audit all user inputs
  - Implement strict type checking
  - Add input length limits
  - Validate against whitelist where possible

### 3.4 Add Security Headers
- **Enhancement**: Add security headers for admin pages
  ```php
  header( 'X-Content-Type-Options: nosniff' );
  header( 'X-Frame-Options: SAMEORIGIN' );
  ```

### 3.5 Implement Logging
- **Enhancement**: Add security event logging
  - Log failed capability checks
  - Log unusual query patterns
  - Track cleanup operations

---

## Testing Requirements

### Phase 1 Testing
- [ ] Test XSS fixes with various malicious inputs
- [ ] Verify capability checks prevent unauthorized access
- [ ] Test query limits with large datasets
- [ ] Validate shortcode attribute sanitization

### Phase 2 Testing
- [ ] Benchmark query performance improvements
- [ ] Verify cache invalidation works correctly
- [ ] Test asset loading on pages with multiple shortcodes
- [ ] Compare markdown parsing performance

### Phase 3 Testing
- [ ] Security audit with automated tools
- [ ] Penetration testing of admin functions
- [ ] Load testing with rate limiting
- [ ] Code review by security expert

---

## Version Planning

- **Version 1.05.0**: Phase 1 completion (Critical Security Release)
- **Version 1.06.0**: Phase 2 completion (Performance Update)
- **Version 1.07.0**: Phase 3 completion (Security Hardening)

---

## Notes

- Always test changes on a staging environment first
- Maintain backward compatibility where possible
- Document all breaking changes in CHANGELOG
- Consider adding automated security scanning to CI/CD pipeline
- Regular security audits recommended every 6 months

---

## Emergency Contacts

- Report security vulnerabilities to: [Create security@domain email]
- Plugin support forum: [WordPress.org support URL]
- GitHub issues: https://github.com/kissplugins/KISS-faqs/issues
```

This roadmap provides a clear, actionable plan for addressing the security and performance issues in priority order, with Phase 1 containing the most critical fixes that should be implemented immediately.