# MG Media Management

Contributors: Marc Gratch  
Requires at least: 4.3  
Tested up to: 6.3  
Stable tag: 1.0.0  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Leverages local media when available, otherwise falls back to a specified production server. **This plugin works with Multisite / Subdirectory installs.**

## Contribution
This plugin, MG Media Management, is inspired by and based upon the original work of Bill Erickson and his plugin, BE Media from Production. Bill's innovative approach to managing media across different environments provided the foundation upon which this plugin was developed. We express our gratitude to Bill Erickson and CultivateWP for their original contributions, which have significantly influenced the enhancements made in this version. Their original work can be explored further at [CultivateWP's website](http://cultivatewp.com).

## Description

When setting up a staging or local development environment, full synchronization of media files is often not necessary, but missing images can disrupt site testing.

This plugin allows you to use media from a production server when local media files are missing. You can define the production URL using a `wp-config.php` constant `MG_MEDIA_SYNC_URL` or through a filter `mg_media_management_url`.

If a local file exists, it will be used in preference to the remote file.

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

## Installation via WP-CLI and constants

You can also install and configure the plugin using WP-CLI:

```bash
wp plugin install --activate https://github.com/mgratch/mg-media-management/releases/latest/download/mg-media-management.zip
wp config set MG_MEDIA_SYNC_URL https://example.com --type=constant
```

## Installing via Composer

To install MG Media Management using Composer, you need to add the repository to your `composer.json` file and then require the plugin. Here’s how you can set it up:

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
