=== MG Media Management ===
Contributors: MarcGratch
Tags: image, images, media, staging, local, development, multisite
Requires at least: 4.3
Tested up to: 6.3
Stable tag: 1.3.1

For developers - Leverages local media when available, otherwise falls back to a specified production server. This plugin works with Multisite / Subdirectory installs.

== Description ==

When setting up a staging or local development environment, full synchronization of media files is often not necessary, but missing images can disrupt site testing.

This plugin allows you to use media from a production server when local media files are missing. Define the production URL using a `wp-config.php` constant `MG_MEDIA_SYNC_URL` or through a filter `mg_media_management_url`.

In all cases, if a local file exists, it will be used in preference to the remote file.

= Features =

* Falls back to production server for missing media files
* Supports Multisite / Subdirectory installations
* Handles theme and plugin assets (fonts, images, etc.) in addition to uploads
* Supports basic authentication for password-protected staging environments
* Works with non-standard WordPress directory structures (e.g., Bedrock)

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

= Basic Authentication =

If your staging or development environment uses basic authentication, you can include the credentials in the URL:

`
define('MG_MEDIA_SYNC_URL', 'https://username:password@example.com');
`

The plugin will preserve these credentials when rewriting URLs, allowing media to load from password-protected environments.

= Installation via WP-CLI and constants =

`
wp plugin install --activate https://github.com/mgratch/mg-media-management/releases/latest/download/mg-media-management.zip
wp config set MG_MEDIA_SYNC_URL https://example.com --type=constant
`

= Integration with WP Migrate =

[WP Migrate](https://deliciousbrains.com/wp-migrate-db-pro/) is a useful tool for syncing databases between environments. The media files functionality of WP Migrate allows you to transfer media along with the database.

For instance, during a site redesign, you might choose to retain all media on your development server and only push new media uploads along with the database.

Set up a "push" profile to push your local database to the development server. Ensure "Media Files" is checked and select "Compare, then upload".

Set up a "pull" profile to pull the development database locally. Do not include media in your pull. Missing media will be handled by MG Media Management.

== Changelog ==

= 1.3.1 =
* Fix: 1.3.0 could blank a page. The attribute pattern used a lazy `.*?` with the `s` flag, which exceeded PCRE's backtrack limit on larger documents. `preg_replace_callback()` returns null on that failure, and because the rewrite runs inside an output buffer, the null was emitted as an empty page.
* The pattern now uses a negated character class and no longer spans newlines, and both rewrite passes fall back to the untouched markup if `preg_*` fails, so a regex failure can never blank output again.

= 1.3.0 =
* Fix: hooks are registered on `plugins_loaded` instead of `muplugins_loaded`. The old hook has already fired by the time a plugin in `wp-content/plugins/` loads, so nothing was ever registered unless an mu-plugin included the file manually.
* `src`, `srcset`, `data-src` and `data-srcset` attributes are now rewritten, not just CSS `url()` references. Page builders write media URLs straight into markup and never touch the attachment API, so those images were left pointing at the local site.
* Attribute rewriting is limited to same-host URLs under the uploads directory that end in a media extension, so scripts and stylesheets served from uploads (SiteGround Optimizer, for one) are left alone.

= 1.2.0 =
* Added support for basic authentication in production URLs
* Added support for non-uploads paths (theme/plugin assets like fonts and images)
* Improved compatibility with non-standard WordPress directory structures (e.g., Bedrock)

= 1.1.0 =
* Added output buffering to handle background-image URLs in inline styles
* Added style loader tag filtering for enqueued stylesheets

= 1.0.0 =
* Initial release
