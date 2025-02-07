# Hypercart FAQs with Schema

A WordPress plugin to manage and display FAQs with **Google’s FAQ Schema** for better SEO and rich search results. The plugin registers a custom FAQ post type, uses the standard WordPress Editor for the answer, and provides a handy shortcode for adding collapsible FAQs to posts/pages.

## Table of Contents

1. [Overview](#overview)  
2. [Schema for SEO](#schema-for-seo)  
3. [Installation](#installation)  
4. [Usage](#usage)  
5. [License](#license)  
6. [No Warranty Disclaimer](#no-warranty-disclaimer)  
7. [Version History](#version-history)

---

## Overview

- **Plugin Name:** KISS FAQs with Schema  
- **Version:** 1.03  
- **Author:** KISS Plugins
- **URI:** KISSPlugins.com
- **License:** GPL v2  
- **Requires at least:** 5.0  
- **Tested up to:** 6.x  
- **Requires PHP:** 7.2+  

This plugin registers a custom post type **hypercart_faq**, where the **post title** is the FAQ question, and the **post content** is the FAQ answer. Each FAQ can be displayed using the `[HTPFAQ post="ID" hidden="true"]` shortcode. Answers are hidden by default unless you set `hidden="false"`.  

When you **edit** or **create** a FAQ, the plugin shows you the **post ID** just below the title, making it easy to know which ID to place in the shortcode.

### Key Features

- Collapsible FAQs that **toggle** when clicked (Safari-friendly).  
- **Post title** as the Question and **Editor content** as the Answer.  
- **FAQ ID** displayed in the edit screen.  
- Automatically inserts **JSON-LD (FAQ Schema)** for each FAQ instance, helping Google identify your FAQ content for potential rich results.  
- If an older version of the plugin had a custom DB table, the plugin checks for leftover records and alerts you in WP Admin.  

---

## Schema for SEO

Hypercart FAQs with Schema automatically includes [FAQPage schema](https://developers.google.com/search/docs/appearance/structured-data/faqpage) in **JSON-LD format** on any page or post that uses the `[HTPFAQ]` shortcode. This allows search engines (notably Google) to parse and display your FAQs in rich results, potentially giving you more visibility.

- **Implementation**: When you embed `[HTPFAQ post="ID"]`, the plugin outputs JSON-LD markup describing the question and accepted answer.  
- **FAQPage**: Each FAQ is treated as a `mainEntity` in the `FAQPage` schema.  
- **Validation**: You can validate and test your FAQ markup with Google’s **Rich Results Test** or **Structured Data Testing Tool**.  

These structured data enhancements can improve your **SEO** by presenting users with a rich FAQ section in SERPs (Search Engine Result Pages).

---

## Installation

1. **Download or Copy** the `hypercart-faqs.php` file into a folder named `hypercart-faqs`, and place it in `wp-content/plugins/`.  
2. **Activate** via **Plugins → Installed Plugins** in your WordPress Admin.  
3. Optionally, **check** the “FAQs” menu in your Admin to begin adding FAQs.

> **Important**: Always **test** new or updated plugins on a **development/staging environment** before deploying to your production site.

---

## Usage

1. **Create/Edit FAQs**:  
   - In **WordPress Admin**, go to **FAQs** (the custom post type).  
   - Add or edit a FAQ. The **post title** is the FAQ question, and the **post content** (WordPress Editor) is the FAQ answer.  
   - After saving, the plugin displays the **FAQ ID** below the title.

2. **Insert FAQs**:  
   - Use the shortcode `[HTPFAQ post="XYZ" hidden="true"]` in your post, page, or widget, where **XYZ** is the ID you noted.  
   - `hidden="true"` collapses the answer initially, `hidden="false"` shows it expanded.

3. **Check Structured Data**:  
   - Visit the post/page where you placed the shortcode.  
   - Use **Google Rich Results Test** to confirm your FAQ schema is detected.

4. **All FAQs Link**:  
   - On the **Plugins** page, the “All FAQs” link for this plugin goes directly to your FAQ listing screen in WP Admin.

---

## License

This plugin is distributed under the terms of the [GNU General Public License v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).  
You are free to **use**, **modify**, and **share** it under the same license conditions.

---

## No Warranty Disclaimer

**This plugin is provided “as is,” without any warranty of any kind.**  
There is no guarantee of its suitability, reliability, or security for any particular purpose. **Use at your own risk.** By installing and using the plugin, you agree that the author(s) and contributors are **not liable** for any damages or losses that may arise from its use.

> **Recommendation**: Always back up your site and **test** new plugins in a staging environment before rolling them out on your live site.

---

## Version History

- **1.03**:  
  - Safari-friendly FAQ toggle.  
  - Display FAQ ID in editor.  
  - Legacy DB check & admin notice.  
  - “Settings” link now points to **All FAQs**.  

- **1.02**:  
  - Introduced legacy DB check & admin notice.  

- **1.01**:  
  - Switched question → post title, answer → post editor.  

- **1.00**:  
  - Initial release with custom post type for FAQs and shortcodes for Q/A.

---

Enjoy the **Hypercart FAQs with Schema** plugin! If you have questions, feel free to reach out via [Your Website](https://example.com).

**Follow Us on Blue Sky:**
https://bsky.app/profile/kissplugins.bsky.social

© Copyright Hypercart D.B.A. Neochrome, Inc.

