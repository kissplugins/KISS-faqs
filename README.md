# Hypercart FAQs with Schema - Frequently Asked Questions

*Yes, this is an FAQ about an FAQ plugin. How meta!*

---

## 🎯 General Questions

### Q: What is Hypercart FAQs with Schema?
**A:** It's a WordPress plugin that manages and displays FAQs with Google's FAQ Schema for better SEO and rich search results. The plugin registers a custom FAQ post type, uses the standard WordPress Editor for answers, and provides a handy shortcode for adding collapsible FAQs to posts/pages.

### Q: Why should I use an FAQ plugin with Schema support?
**A:** FAQ Schema (structured data) helps search engines understand your content better and can lead to rich snippets in search results. This means your FAQs might appear directly in Google search results, increasing visibility and click-through rates. Plus, organized FAQs improve user experience on your site.

### Q: What are the plugin's basic specifications?
**A:** 
- **Plugin Name:** KISS FAQs with Schema  
- **Version:** 1.04.7  
- **Author:** KISS Plugins
- **Website:** KISSPlugins.com
- **License:** GPL v2  
- **Requires WordPress:** 5.0 or higher  
- **Tested up to:** WordPress 6.x  
- **Requires PHP:** 7.2+

---

## 📥 Installation & Setup

### Q: How do I install the Hypercart FAQs plugin?
**A:** 
1. Download or copy the `kiss-faqs.php` file into a folder named `kiss-faqs`
2. Place it in your `wp-content/plugins/` directory
3. Navigate to **Plugins → Installed Plugins** in your WordPress Admin
4. Click **Activate** on the KISS FAQs plugin

### Q: Should I test this plugin before using it on my live site?
**A:** Absolutely! Always test new or updated plugins on a development/staging environment before deploying to your production site. This is a best practice for any WordPress plugin installation.

### Q: Where do I find the FAQs after installation?
**A:** Once activated, you'll see a new "FAQs" menu item in your WordPress Admin dashboard. This is where you'll create and manage all your FAQ content.

---

## ✍️ Creating & Managing FAQs

### Q: How do I create a new FAQ?
**A:** 
1. Go to **FAQs** in your WordPress Admin
2. Click **Add New**
3. Enter your question as the **post title**
4. Enter your answer in the **post content** area (using the WordPress Editor)
5. Save or publish your FAQ
6. Note the **FAQ ID** that appears below the title - you'll need this for the shortcode

### Q: Can I use the WordPress block editor for FAQ answers?
**A:** Yes! The plugin uses the standard WordPress Editor for FAQ answers, so you can use all the formatting options, blocks, images, and media that you normally would in any WordPress post or page.

### Q: How do I organize my FAQs into categories?
**A:** The plugin supports WordPress taxonomies (categories) for FAQs. You can create categories and assign FAQs to them, then display all FAQs from a specific category using the category shortcode parameter.

---

## 🔧 Using Shortcodes

### Q: How do I display a single FAQ on my page?
**A:** Use the shortcode `[KISSFAQ post="XYZ" hidden="true"]` where:
- **XYZ** is the FAQ ID (shown when editing the FAQ)
- **hidden="true"** starts with the answer collapsed (recommended)
- **hidden="false"** starts with the answer expanded

### Q: How do I display all FAQs from a specific category?
**A:** Use the shortcode `[KISSFAQS category="cat_slug" exclude="123,456" hidden="true"]` where:
- **cat_slug** is your category slug
- **exclude** is optional - comma-separated post IDs to exclude
- **hidden** controls whether answers start collapsed or expanded

### Q: Can I exclude certain FAQs when displaying a category?
**A:** Yes! Use the `exclude` parameter in the shortcode with comma-separated FAQ IDs. For example: `[KISSFAQS category="general" exclude="827755,827756" hidden="true"]`

### Q: Do the FAQs work on mobile and all browsers?
**A:** Yes! The plugin includes Safari-friendly toggle functionality and is designed to work across all modern browsers and devices.

---

## 🔍 SEO & Schema

### Q: What is FAQ Schema and why does it matter?
**A:** FAQ Schema is structured data in JSON-LD format that helps search engines understand your FAQ content. It can lead to:
- Rich snippets in search results
- FAQ sections appearing directly in Google search
- Better visibility and higher click-through rates
- Improved SEO performance

### Q: How does the plugin implement FAQ Schema?
**A:** The plugin automatically adds FAQPage schema markup whenever you use the `[KISSFAQ]` shortcode. Each FAQ is treated as a `mainEntity` with the question and accepted answer properly formatted for search engines.

### Q: How can I verify my FAQ Schema is working?
**A:** 
1. Visit a page where you've added FAQ shortcodes
2. Use Google's **Rich Results Test** tool
3. Enter your page URL
4. Check that the tool detects your FAQ structured data
5. Review any warnings or suggestions

### Q: Will my FAQs definitely appear as rich snippets in Google?
**A:** While proper schema markup increases the likelihood, Google ultimately decides whether to display rich snippets based on many factors including content quality, relevance, and search intent. The plugin gives you the best technical foundation for rich results.

### Q: Can I control which FAQs appear in XML sitemaps?
**A:** Yes! Version 1.04.7+ includes comprehensive sitemap control features:
- **Individual Control:** Each FAQ has a "Sitemap Settings" metabox with "Publish to Sitemap" dropdown (Yes/No)
- **Global Control:** FAQ Settings page has "Publish All FAQ Posts to Sitemap" option
- **Smart Precedence:** Global setting overrides individual settings when disabled

### Q: Why would I want to exclude FAQs from sitemaps?
**A:** Excluding FAQs from sitemaps helps prevent SEO cannibalization. If you have many similar FAQs or test content, they might compete with your main pages in search results. Sitemap exclusion gives you granular control over which content search engines prioritize.

### Q: What happens when I exclude an FAQ from the sitemap?
**A:** Two things happen automatically:
1. **Sitemap Exclusion:** The FAQ won't appear in your XML sitemap files
2. **Noindex Tag:** The plugin adds `<meta name="robots" content="noindex, nofollow" />` to the FAQ page header, reinforcing that search engines shouldn't index it

### Q: How do the global and individual sitemap settings work together?
**A:** The global setting takes precedence:
- **Global ON + Individual YES:** FAQ appears in sitemap ✅
- **Global ON + Individual NO:** FAQ excluded from sitemap ❌
- **Global OFF:** ALL FAQs excluded regardless of individual settings ❌

---

## ⚙️ Settings & Configuration

### Q: Where do I find the FAQ settings page?
**A:** Go to **FAQs → Settings** in your WordPress admin. The settings page includes layout options, sitemap controls, self-tests, and cleanup tools.

### Q: What layout options are available?
**A:** The plugin offers two layout styles:
- **Default Layout:** Traditional FAQ style with arrow icons
- **Sleuth AI Layout:** Modern style with + / - toggle buttons and clean borders

### Q: What are the self-tests and why should I run them?
**A:** The settings page includes four automated tests to validate functionality:
1. **Metabox Registration Test:** Ensures sitemap controls are properly registered
2. **Global Setting Override Test:** Validates global sitemap setting behavior
3. **Sitemap Exclusion Test:** Confirms sitemap filtering is working
4. **Noindex Tag Generation Test:** Verifies noindex meta tags are added correctly

These tests help catch issues after plugin updates or configuration changes.

### Q: What is the test FAQ cleanup feature?
**A:** The cleanup tool automatically detects FAQ posts that appear to be test content (containing words like "test," "sample," "demo," etc.) and allows you to safely delete them with one click. This helps maintain a clean production environment.

### Q: How do I access the plugin documentation?
**A:** Click the **"Read Me"** link in:
- The plugin listings page (Plugins → Installed Plugins)
- The FAQ admin menu (FAQs → Read Me)

The built-in viewer renders the README.md file with proper formatting.

---

## 🛠️ Troubleshooting & Admin

### Q: Where is the FAQ ID displayed?
**A:** When you create or edit an FAQ, the plugin displays the FAQ ID directly below the title field in the editor. This makes it easy to know which ID to use in your shortcodes.

### Q: I see a notice about legacy database records. What should I do?
**A:** If you had an older version of the plugin that used a custom database table, the plugin will detect leftover records and alert you in WP Admin. You can safely ignore this if you're not experiencing issues, or contact support for help cleaning up old data.

### Q: Where can I find all my FAQs quickly?
**A:** On the **Plugins** page, click the "All FAQs" link under this plugin. It takes you directly to your FAQ listing screen in WP Admin.

### Q: Can I see which categories my FAQs belong to in the admin?
**A:** Yes! Version 1.04.5 added a category column in the admin listing, making it easy to see how your FAQs are organized.

### Q: Is there an edit option on the front end?
**A:** Yes, if you're logged in as an administrator, version 1.04.5+ shows an edit icon on the front end for quick access to FAQ editing.

### Q: I don't see the sitemap settings metabox. What's wrong?
**A:** The sitemap settings metabox only appears when editing FAQ posts (post type: `kiss_faq`). If you're editing a regular post or page, you won't see it. Also, run the self-tests in FAQ Settings to diagnose any registration issues.

### Q: My FAQ still appears in the sitemap even though I set it to "No." Why?
**A:** Check these possibilities:
1. **Global Setting:** If "Publish All FAQ Posts to Sitemap" is unchecked in FAQ Settings, it overrides individual settings
2. **Caching:** Your sitemap might be cached. Try visiting `/wp-sitemap-posts-kiss_faq-1.xml` directly
3. **SEO Plugin:** Some SEO plugins override WordPress's native sitemap functionality

### Q: How can I verify the noindex tag is working?
**A:**
1. Visit an FAQ post that's excluded from the sitemap
2. View the page source (right-click → View Source)
3. Look for `<meta name="robots" content="noindex, nofollow" />` in the `<head>` section
4. Use the self-tests in FAQ Settings for automated verification

### Q: Can I bulk change sitemap settings for multiple FAQs?
**A:** Currently, you need to edit each FAQ individually to change its sitemap setting. However, you can use the global setting to exclude all FAQs at once, or use the cleanup tool to remove test posts in bulk.

---

## 📋 Version History

### Q: What's new in the latest version (1.04.7+)?
**A:** Major new features for SEO control and site management:
- **Sitemap Control:** Individual and global settings to exclude FAQs from XML sitemaps
- **Noindex Reinforcement:** Automatic noindex meta tags for excluded FAQ posts
- **Self-Tests:** Four automated tests to validate plugin functionality
- **Test Cleanup:** Automatic detection and removal of test FAQ posts
- **Documentation Viewer:** Built-in markdown reader for README.md
- **Enhanced Admin:** Settings moved to FAQ menu, version display, improved links
- **Bug Fixes:** Fixed layout rendering syntax and added docblocks

### Q: What were the major improvements in version 1.03?
**A:** 
- Safari-friendly FAQ toggle functionality
- FAQ ID display in the editor
- Legacy database check with admin notices
- "Settings" link now points to "All FAQs" for easier navigation

### Q: When did the plugin switch to using post titles and content?
**A:** Version 1.01 made this change, using the post title for questions and the post editor for answers, making it more intuitive and WordPress-native.

---

## 💡 Best Practices & Tips

### Q: When should I exclude FAQs from sitemaps?
**A:** Consider excluding FAQs in these situations:
- **Test Content:** Any FAQs created for testing purposes
- **Duplicate Information:** FAQs that repeat content from your main pages
- **Internal Use:** FAQs meant only for logged-in users or staff
- **Outdated Content:** FAQs that are no longer relevant but you want to keep for reference

### Q: What's the recommended workflow for managing FAQ SEO?
**A:**
1. **Start with Global ON:** Keep "Publish All FAQ Posts to Sitemap" enabled by default
2. **Review Individual FAQs:** Set specific FAQs to "No" as needed
3. **Run Self-Tests:** Regularly check the self-tests in FAQ Settings
4. **Monitor Sitemaps:** Periodically check `/wp-sitemap-posts-kiss_faq-1.xml`
5. **Clean Up Tests:** Use the cleanup tool to remove test content

### Q: How can I prevent SEO cannibalization with my FAQs?
**A:**
- **Unique Content:** Ensure each FAQ provides unique value
- **Strategic Exclusion:** Exclude FAQs that compete with important pages
- **Category Organization:** Use categories to group related FAQs
- **Regular Audits:** Review your FAQ sitemap monthly

### Q: Should I use the global setting or individual settings?
**A:**
- **Individual Settings:** Best for most sites - gives you granular control
- **Global Disable:** Use when you want FAQs for user experience only, not SEO
- **Testing Phase:** Temporarily disable global setting while developing content

---

## ⚖️ Legal & Support

### Q: What license is this plugin released under?
**A:** The plugin is distributed under the GNU General Public License v2. You're free to use, modify, and share it under the same license conditions.

### Q: Is there any warranty for this plugin?
**A:** **No.** This plugin is provided "as is," without any warranty of any kind. There's no guarantee of its suitability, reliability, or security for any particular purpose. Use at your own risk. Always back up your site and test in a staging environment first!

### Q: How can I get support or follow updates?
**A:** 
- Visit [KISSPlugins.com](https://kissplugins.com)
- Follow on Blue Sky: [kissplugins.bsky.social](https://bsky.app/profile/kissplugins.bsky.social)

### Q: Who owns the copyright?
**A:** © Copyright Hypercart D.B.A. Neochrome, Inc.

---

## 🤔 Meta Questions

### Q: Why is this README written as an FAQ?
**A:** Because it's documentation for an FAQ plugin! We thought it would be fitting (and fun) to present the information in the same format the plugin helps you create. Plus, it demonstrates that FAQs are an excellent way to organize and present information clearly.

### Q: Can I use this README format as an example for my own FAQs?
**A:** Absolutely! This README demonstrates good FAQ practices: clear questions, comprehensive answers, logical organization, and helpful formatting. Feel free to use it as inspiration for your own FAQ content.

---

*Happy FAQ-ing! 🎉*