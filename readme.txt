=== MG Media Management ===
Contributors: MarcGratch
Tags: image, images, media, staging, local, development, multisite
Requires at least: 4.3
Tested up to: 6.3
Stable tag: 1.0.0

For developers - Leverages local media when available, otherwise falls back to a specified production server.  This plugin works with Multisite / Subdirectory installs.

== Description ==

When setting up a staging or local development environment, full synchronization of media files is often not necessary, but missing images can disrupt site testing.

This plugin allows you to use media from a production server when local media files are missing. Define the production URL using a `wp-config.php` constant `MG_MEDIA_SYNC_URL` or through a filter `mg_media_management_url`.

In all cases, if a local file exists, it will be used in preference to the remote file.

== Installation ==

To install the plugin, add the following constant to your `wp-config.php` file with your production server's URL:

`
define('MG_MEDIA_SYNC_URL', 'https://example.com');
`

Alternatively, you can use the filter in your theme's `functions.php` file, a core functionality plugin, or a mu-plugin:

`
add_filter('mg_media_management_url', function() {
    return 'https://example.com';
});
`

**Installation via WP-CLI and constants**

`
wp plugin install --activate https://github.com/mgratch/mg-media-management/releases/latest/download/mg-media-management.zip
wp config set MG_MEDIA_SYNC_URL https://example.com --type=constant
`

**Integration with WP Migrate**

[WP Migrate](https://deliciousbrains.com/wp-migrate-db-pro/) is a useful tool for syncing databases between environments. The media files functionality of WP Migrate allows you to transfer media along with the database.

For instance, during a site redesign, you might choose to retain all media on your development server and only push new media uploads along with the database.

Set up a "push" profile to push your local database to the development server. Ensure "Media Files" is checked and select "Compare, then upload".

Set up a "pull" profile to pull the development database locally. Do not include media in your pull. Missing media will be handled by MG Media Management.
