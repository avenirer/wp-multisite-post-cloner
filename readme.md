# Multisite Post Cloner

**Contributors:** amurin, avenirer
**Tags:** multisite, clone, posts, pages, network, beaver builder  
**Requires at least:** 5.0  
**Tested up to:** 6.6.1  
**Requires PHP:** 7.2  
**Stable tag:** 1.0.0  
**License:** GPLv2 or later  
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Multisite Post Cloner allows you to clone posts and pages across sites in your WordPress multisite network, including Beaver Builder pages.

## Description

Multisite Post Cloner is a simple yet powerful plugin that enables network administrators to clone posts and pages from one site to another within a WordPress multisite network—including advanced layouts created with Beaver Builder. The plugin provides an intuitive interface for selecting content, including Beaver Builder pages, and seamlessly copying it to any site in the network.

### Features:
* Clone posts and pages (including Beaver Builder pages) to any site in your multisite network.
* Select which post types should have the cloning functionality, including custom post types and Beaver Builder layouts.
* Keeps the original post intact on the source site.
* Simple settings page for easy configuration.
* Maintains Beaver Builder page layouts, modules, and styling during cloning.

This plugin is perfect for multisite networks where content, including complex Beaver Builder designs, needs to be shared or duplicated across different sites without manually copying and pasting.

## Installation

1. Upload the `multisite-post-cloner` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > Multisite Post Cloner Settings to configure which post types (including Beaver Builder pages) can be cloned.
4. Use the bulk actions in the post/page list to clone content to another site in your network.

## Frequently Asked Questions

### Does this plugin support custom post types?

Yes, the plugin allows you to select which custom post types can be cloned from the settings page. This includes pages built with Beaver Builder.

### Can I clone Beaver Builder pages?

Yes, Multisite Post Cloner fully supports cloning Beaver Builder pages. All modules, layouts, and styling created with Beaver Builder will be preserved on the target site.

### Will the original post be deleted after cloning?

No, the original post remains intact on the source site. The plugin only clones the post to the target site.

### Can I clone content to multiple sites at once?

Currently, the plugin allows cloning to one target site at a time. You can repeat the process to clone the same content to additional sites.

## Changelog

### 1.0.0
* Initial release.
* Added support for cloning Beaver Builder pages.

## Upgrade Notice

### 1.0.0
* Initial release with Beaver Builder page cloning support.

## Support

For support, please contact me directly at amurin3d@gmail.com.

## License

This plugin is licensed under the GPLv2 or later. You can use, modify, and distribute it under the same license.
