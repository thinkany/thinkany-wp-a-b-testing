# ThinkAny WP A/B Testing

Simple A/B testing for WordPress pages and posts with ACF integration.

## Description

ThinkAny WP A/B Testing is a lightweight plugin that allows you to easily set up A/B tests for your WordPress pages and posts. 

The plugin integrates with Advanced Custom Fields (ACF) to provide a simple interface for enabling A/B testing on any page or post. You can select which page or post should be used as the "B" variant, and the plugin will automatically handle the rest.

## Features

* Easy setup with ACF integration
* 50/50 split testing by default (configurable in 10% increments)
* Works with both pages and posts
* Dashboard widget for quick stats overview
* Detailed statistics page
* Custom columns in post/page lists to show A/B testing status
* Optimized for performance with transient-based caching
* Session persistence option to maintain consistent user experience

## Performance Optimized

This plugin is designed with performance in mind. Instead of writing to the database on every page view, it uses WordPress transients to cache view data and only writes to the database in batches (after 100 views) or when viewing statistics. This significantly reduces database load and improves site performance, especially on high-traffic sites.

## Session Persistence

The plugin includes an optional session persistence feature that ensures visitors consistently see the same variant throughout their browsing session. When enabled, a cookie is set to remember which variant (A or B) was shown to the visitor, ensuring a consistent user experience across multiple page views. You can also configure how long the cookie should last.

## Requirements

* WordPress 6.8 or higher
* PHP 8.0 or higher
* Advanced Custom Fields (ACF) plugin

## Installation

1. Upload the plugin files to the `/wp-content/plugins/thinkany-wp-a-b-testing` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure that Advanced Custom Fields (ACF) is installed and activated.
4. Use the Settings->A/B Testing screen to configure the plugin.
5. Enable A/B testing on individual pages or posts using the ACF fields in the editor.

## Frequently Asked Questions

### How do I set up an A/B test?

1. Go to the page or post you want to test
2. In the ACF fields section, enable A/B testing
3. Select another page or post to use as the "B" variant
4. Save the page
5. View the page to see the A/B test in action

### How does the plugin determine which variant to show?

The plugin uses a probability-based approach that respects your configured split ratio. For example, with a 70/30 split ratio, there's a 70% chance any visitor will see variant A and a 30% chance they'll see variant B. This statistical approach ensures more accurate testing results compared to simple alternating methods.

If session persistence is enabled, visitors will consistently see the same variant throughout their browsing session, based on a cookie stored in their browser.

### Can I change the split ratio?

Yes, you can adjust the split ratio in the plugin settings using 10% increments. The slider allows you to set any ratio from 10/90 to 90/10 (A/B). This gives you precise control over how much traffic each variant receives. For example, if you're testing a significant change and want to limit exposure, you could set a 90/10 split to show the new variant to only 10% of visitors.

### Will this affect my SEO?

The plugin uses server-side redirects to show variant B, so search engines will only see variant A. This means your SEO should not be negatively affected by using this plugin.

### Will this plugin slow down my site?

No, the plugin is designed with performance in mind. It uses a transient-based caching system that minimizes database writes by batching them together. View data is only written to the database after accumulating 100 views (by default) or when viewing statistics, which significantly reduces database load and improves performance.

### How does session persistence work?

When session persistence is enabled, the plugin sets a cookie in the visitor's browser that remembers which variant (A or B) they were shown. This ensures they see the same variant consistently throughout their browsing session. You can configure how long this cookie should last in the plugin settings.

### Can I A/B test more than two variants?

Currently, the plugin only supports testing two variants (A and B). Support for multiple variants may be added in a future update.

## Screenshots

1. A/B Testing settings page
2. ACF fields for enabling A/B testing on a page or post
3. Statistics dashboard showing A/B test results

## Changelog

### 1.0.0
* Initial release

## License

ThinkAny WP A/B Testing is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
```

## Credits

Developed by [ThinkAny](https://thinkany.com)
