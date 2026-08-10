# MG Media Management

Contributors: Marc Gratch  
Requires at least: 4.3  
Tested up to: 6.3  
Stable tag: 1.3.1  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Leverages local media when available, otherwise falls back to a specified production server. **This plugin works with Multisite / Subdirectory installs.**

## Contribution
This plugin, MG Media Management, is inspired by and based upon the original work of Bill Erickson and his plugin, BE Media from Production. Bill's innovative approach to managing media across different environments provided the foundation upon which this plugin was developed. We express our gratitude to Bill Erickson and CultivateWP for their original contributions, which have significantly influenced the enhancements made in this version. Their original work can be explored further at [CultivateWP's website](http://cultivatewp.com).

## Description

When setting up a staging or local development environment, full synchronization of media files is often not necessary, but missing images can disrupt site testing.

This plugin allows you to use media from a production server when local media files are missing. You can define the production URL using a `wp-config.php` constant `MG_MEDIA_SYNC_URL` or through a filter `mg_media_management_url`.

If a local file exists, it will be used in preference to the remote file.

### Features

- Falls back to production server for missing media files
- Supports Multisite / Subdirectory installations
- Handles theme and plugin assets (fonts, images, etc.) in addition to uploads
- Supports basic authentication for password-protected staging environments
- Works with non-standard WordPress directory structures (e.g., Bedrock)

## Installation

To install the plugin, add the following constant to your `wp-config.php` file with your production server's URL:

```php
define('MG_MEDIA_SYNC_URL', 'https://example.com');
```

Alternatively, you can use the filter in your theme's `functions.php` file, a core functionality plugin, or a mu-plugin:

```php
add_filter('mg_media_management_url', function() {
    return 'https://example.com';
});
```

### Basic Authentication

If your staging or development environment uses basic authentication, you can include the credentials in the URL:

```php
define('MG_MEDIA_SYNC_URL', 'https://username:password@example.com');
```

The plugin will preserve these credentials when rewriting URLs, allowing media to load from password-protected environments.

## Installation via WP-CLI and constants

You can also install and configure the plugin using WP-CLI:

```bash
wp plugin install --activate https://github.com/mgratch/mg-media-management/releases/latest/download/mg-media-management.zip
wp config set MG_MEDIA_SYNC_URL https://example.com --type=constant
```

## Installing via Composer

To install MG Media Management using Composer, you need to add the repository to your `composer.json` file and then require the plugin. Here's how you can set it up:

1. **Add the Repository**: First, you need to add the GitHub repository as a package source in your project's `composer.json`.

```json
"repositories": [
{
"type": "vcs",
"url": "https://github.com/mgratch/mg-media-management"
}
]
```

2. **Require the Plugin**: After adding the repository, you can require the plugin by running the following command:

```bash
composer require mgratch/mg-media-management:dev-master
```

This command tells Composer to install the latest version from the `master` branch. You can also specify any tag or commit hash if you want to lock the plugin to a specific release.

3. **Update or Install**: If you are setting up a new project, you can run `composer install` to install all dependencies. If you are adding the plugin to an existing project, run `composer update` to update your project dependencies and include the new plugin.

This setup will install the plugin directly into your WordPress `wp-content/plugins` directory, assuming your WordPress setup is configured to manage plugins and themes with Composer.

### Considerations
- **Branches and Tags**: If you prefer to lock down to a specific version of your plugin, you can tag releases in your GitHub repository. Users can then specify a version tag instead of `dev-master` when requiring the plugin.
- **Composer Installers**: This setup assumes that the WordPress project uses `composer/installers` to manage the installation path of WordPress plugins. Ensure that this is set up in the main project to direct Composer to place the plugin in the correct directory.

## Integration with WP Migrate

[WP Migrate](https://deliciousbrains.com/wp-migrate-db-pro/) is a useful tool for syncing databases between environments. The media files functionality of WP Migrate allows you to transfer media along with the database.

For instance, during a site redesign, you might choose to retain all media on your development server and only push new media uploads along with the database.

Set up a "push" profile to push your local database to the development server. Ensure "Media Files" is checked and select "Compare, then upload".

Set up a "pull" profile to pull the development database locally. Do not include media in your pull. Missing media will be handled by MG Media Management.

## Changelog

### 1.3.1
- Fix: 1.3.0 could blank a page. The attribute pattern used a lazy `.*?` with the `s` flag, which exceeded PCRE's backtrack limit on larger documents. `preg_replace_callback()` returns null on that failure, and because the rewrite runs inside an output buffer, the null was emitted as an empty page.
- The pattern now uses a negated character class and no longer spans newlines, and both rewrite passes fall back to the untouched markup if `preg_*` fails.

### 1.3.0
- Fix: hooks are registered on `plugins_loaded` instead of `muplugins_loaded`. The old hook has already fired by the time a plugin in `wp-content/plugins/` loads, so nothing was ever registered unless an mu-plugin included the file manually.
- `src`, `srcset`, `data-src` and `data-srcset` attributes are now rewritten, not just CSS `url()` references. Page builders write media URLs straight into markup and never touch the attachment API.
- Attribute rewriting is limited to same-host URLs under the uploads directory that end in a media extension, so scripts and stylesheets served from uploads are left alone.

### 1.2.0
- Added support for basic authentication in production URLs
- Added support for non-uploads paths (theme/plugin assets like fonts and images)
- Improved compatibility with non-standard WordPress directory structures (e.g., Bedrock)

### 1.1.0
- Added output buffering to handle background-image URLs in inline styles
- Added style loader tag filtering for enqueued stylesheets

### 1.0.0
- Initial release
