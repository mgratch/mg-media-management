<?php
/**
 * Plugin Name: MG Media Management
 * Plugin URI: https://github.com/mgratch/mg-media-management/
 * Description: Leverages local media when available, otherwise falls back to a specified production server.
 * Author: Marc Gratch
 * Author URI: https://marcgratch.com
 * Version: 1.3.1
 * Text Domain: mg-media-management
 * Domain Path: /languages
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * Original Author: Bill Erickson
 * Original Author URI: http://cultivatewp.com
 * Original Plugin URI: https://cultivatewp.com/our-plugins/be-media-from-production/
 *
 * @package MG_Media_Management
 * @author Marc Gratch
 * @since 1.0.0
 * @license GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Class for Media Management
 *
 * @since 1.0.0
 */
class MG_Media_Management {
	/**
	 * Production URL
	 *
	 * @var string
	 */
	public string $production_url = '';

	/**
	 * Initializes WordPress hooks.
	 */
	public function init(): void {
		add_filter( 'wp_get_attachment_image_src', array( $this, 'modify_image_src' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 99 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'modify_image_js' ), 10 );
		add_filter( 'wp_content_img_tag', array( $this, 'modify_image_tag' ), 10 );
		add_filter( 'the_content', array( $this, 'modify_content_images' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'update_image_url' ) );
		add_filter( 'the_post', array( $this, 'update_post_content' ) );
		add_filter( 'attachment_url_to_postid', array( $this, 'resolve_attachment_from_production' ), 10, 2 );
		add_filter( 'style_loader_tag', array( $this, 'modify_style_loader_tag' ), 20, 4 );
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 0 );
		add_action( 'shutdown', array( $this, 'end_output_buffer' ), PHP_INT_MAX );
	}

	/**
	 * Modify the main image source URL.
	 *
	 * @param mixed $image The image source URL.
	 *
	 * @return array
	 */
	public function modify_image_src( $image ) {
		if ( ! is_array( $image ) ) {
			$image_str = $image;
			$image     = array();
			$image[]   = $image_str;
		}

		if ( isset( $image[0] ) ) {
			$image[0] = $this->get_remote_or_local_url( $image[0] );
		}
		return $image;
	}

	/**
	 * Modify image attributes.
	 *
	 * @param array $attr The image attributes.
	 * @return array
	 */
	public function modify_image_attributes( array $attr ): array {
		if ( isset( $attr['srcset'] ) ) {
			$srcset         = array_map(
				function ( $url ) {
					return $this->get_remote_or_local_url( $url );
				},
				explode( ' ', $attr['srcset'] )
			);
			$attr['srcset'] = implode( ' ', $srcset );
		}
		return $attr;
	}

	/**
	 * Modify image for JavaScript use, primarily in media library.
	 *
	 * @param array $response The image response data.
	 *
	 * @return array
	 */
	public function modify_image_js( array $response ): array {
		if ( isset( $response['url'] ) ) {
			$response['url'] = $this->get_remote_or_local_url( $response['url'] );
		}
		foreach ( $response['sizes'] as &$size ) {
			$size['url'] = $this->get_remote_or_local_url( $size['url'] );
		}
		return $response;
	}

	/**
	 * Modify image tags in content.
	 *
	 * @param string $filtered_image The filtered image tag.
	 *
	 * @return string
	 */
	public function modify_image_tag( string $filtered_image ): string {
		return $this->replace_urls_in_content( $filtered_image );
	}

	/**
	 * Modify images in post content.
	 *
	 * @param string $content The post content.
	 *
	 * @return string
	 */
	public function modify_content_images( string $content ): string {
		return $this->replace_urls_in_content( $content );
	}

	/**
	 * Replace URLs within the provided content using specific rules based on the site configuration.
	 *
	 * This method uses regex to find all URLs in the provided content string and conditionally modifies them
	 * based on the site's multisite configuration before possibly replacing them with either remote or local URLs.
	 *
	 * @param string $content The content in which URLs need to be replaced.
	 * @return string The content with URLs replaced.
	 */
	protected function replace_urls_in_content( string $content ): string {
		$upload_locations = wp_upload_dir();
		$base_url         = $this->get_base_url( $upload_locations['baseurl'] );

		// Replace URLs found in the content.
		return preg_replace_callback(
			'/https?\:\/\/[^\" ]+/i',
			function ( $matches ) use ( $base_url ) {
				$url = $this->prepare_url_for_replacement( $matches[0] );
				return str_contains( $url, $base_url ) ? $this->get_remote_or_local_url( $url ) : $url;
			},
			$content
		);
	}

	/**
	 * Get the base URL for the site, adjusted for multisite environments if necessary.
	 *
	 * If the site is a multisite and not a subdomain install, the base URL will be adjusted
	 * to reflect the appropriate directory for the current blog if it's not the main site.
	 *
	 * @param string $base_url The initial base URL derived from the WordPress uploads directory.
	 * @return string The potentially adjusted base URL.
	 */
	private function get_base_url( string $base_url ): string {
		if ( is_multisite() && ! is_subdomain_install() && get_current_blog_id() > 1 ) {
			return str_replace( trailingslashit( network_home_url() ), trailingslashit( home_url() ), $base_url );
		}
		return $base_url;
	}

	/**
	 * Prepare a URL for replacement by adjusting it as necessary based on multisite configuration.
	 *
	 * This method adjusts the URL if the current setup is a multisite and not a subdomain installation,
	 * and if the URL does not already include the path expected for the current blog.
	 *
	 * @param string $url The URL to be prepared for replacement.
	 *
	 * @return string The possibly adjusted URL.
	 */
	private function prepare_url_for_replacement( string $url ): string {
		if ( is_multisite() && ! is_subdomain_install() ) {
			$blog_details = get_blog_details( get_current_blog_id() );
			if ( isset( $blog_details->path ) && ! str_contains( $url, $blog_details->path ) && ( untrailingslashit( $url ) !== untrailingslashit( home_url() ) ) ) {
				return str_replace( trailingslashit( network_home_url() ), trailingslashit( home_url() ), $url );
			}
		}
		return $url;
	}

	/**
	 * Update the URL of the image based on its existence on the local server.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string
	 */
	public function update_image_url( string $url ): string {
		return $this->local_image_exists( $url ) ? $url : $this->replace_url_with_production( $url );
	}

	/**
	 * Updates post content.
	 *
	 * @param object $post The post object.
	 *
	 * @return object
	 */
	public function update_post_content( object $post ): object {
		$post->post_content = $this->modify_content_images( $post->post_content );
		return $post;
	}

	/**
	 * Checks if a local image exists.
	 *
	 * @param string $url The image URL.
	 *
	 * @return bool
	 */
	protected function local_image_exists( string $url ): bool {
		$local_filename = $this->local_filename( $url );
		return file_exists( $local_filename );
	}

	/**
	 * Converts a URL to a local filename.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string
	 */
	protected function local_filename( string $url ): string {
		$upload_locations = wp_upload_dir();
		if ( is_multisite() && ! is_subdomain_install() ) {
			$url = str_replace( trailingslashit( home_url() ), trailingslashit( network_home_url() ), $url );
		}

		$local_path = str_replace( $upload_locations['baseurl'], $upload_locations['basedir'], $url );

		// If the local path isn't set and it's a multisite, try removing the sites/<id> from the baseurl and basedir.
		if ( $local_path === $url && is_multisite() && ! is_subdomain_install() ) {
			$baseurl    = str_replace( '/sites/' . get_current_blog_id(), '', $upload_locations['baseurl'] );
			$basedir    = str_replace( '/sites/' . get_current_blog_id(), '', $upload_locations['basedir'] );
			$local_path = str_replace( $baseurl, $basedir, $url );
		}

		// If still a URL (non-uploads path like themes/plugins), convert content URL to filesystem path.
		if ( filter_var( $local_path, FILTER_VALIDATE_URL ) ) {
			$local_path = str_replace(
				content_url(),
				WP_CONTENT_DIR,
				$url
			);
		}

		return $local_path;
	}

	/**
	 * Replaces a URL with the production URL if the local file does not exist.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string
	 */
	protected function replace_url_with_production( string $url ): string {
		$production_url = $this->get_production_url();
		return empty( $production_url ) ? $url : str_replace( trailingslashit( network_home_url() ), trailingslashit( $production_url ), $url );
	}

	/**
	 * Replaces a URL with the local URL, removing the production host and path.
	 *
	 * @param string $url The image URL to be replaced.
	 *
	 * @return string
	 */
	protected function replace_url_with_local( string $url ): string {
		$local_url       = get_site_url();
		$local_url_parts = wp_parse_url( $local_url );
		$local_host      = $local_url_parts['host'] ?? '';
		$local_path      = $local_url_parts['path'] ?? '';
		$remote_host     = wp_parse_url( $this->get_production_url(), PHP_URL_HOST );
		$remove_path_url = str_replace( $local_path, '', $url );
		return str_replace( $remote_host, $local_host, $remove_path_url );
	}

	/**
	 * Retrieves the production URL, checking the constant and applying a filter.
	 *
	 * @return string
	 */
	public function get_production_url(): string {
		return defined( 'MG_MEDIA_SYNC_URL' ) && MG_MEDIA_SYNC_URL ? MG_MEDIA_SYNC_URL : apply_filters( 'mg_media_management_url', $this->production_url );
	}

	/**
	 * Get remote or local URL depending on the existence of the file.
	 *
	 * @param string $url The image URL.
	 *
	 * @return string
	 */
	protected function get_remote_or_local_url( string $url ): string {
		return $this->local_image_exists( $url ) ? $url : $this->replace_url_with_production( $url );
	}

	/**
	 * Attempt to resolve an attachment ID from a production URL.
	 *
	 * @param int|null $post_id The post ID to resolve.
	 * @param null     $url The URL to resolve.
	 *
	 * @return int|false
	 */
	public function resolve_attachment_from_production( int $post_id = null, $url = null ): false|int {
		remove_filter( 'attachment_url_to_postid', array( $this, 'resolve_attachment_from_production' ) );

		if ( $post_id ) {
			return $post_id;
		}

		$local_url = $this->replace_url_with_local( $url );

		$post_id = attachment_url_to_postid( $local_url );

		add_filter( 'attachment_url_to_postid', array( $this, 'resolve_attachment_from_production' ), 10, 2 );

		return $post_id;
	}

	/**
	 * Escape a URL while preserving basic auth credentials.
	 *
	 * @param string $url The URL to escape.
	 *
	 * @return string
	 */
	protected function esc_url_preserve_auth( string $url ): string {
		$parts = wp_parse_url( $url );

		// If no user, just use regular escaping.
		if ( empty( $parts['user'] ) ) {
			return esc_url_raw( $url );
		}

		// Build auth string.
		$auth = rawurlencode( $parts['user'] );
		if ( ! empty( $parts['pass'] ) ) {
			$auth .= ':' . rawurlencode( $parts['pass'] );
		}

		// Remove auth from URL, escape it, then add auth back.
		$url_without_auth = str_replace( $parts['user'] . ( ! empty( $parts['pass'] ) ? ':' . $parts['pass'] : '' ) . '@', '', $url );
		$escaped          = esc_url_raw( $url_without_auth );

		// Add auth back after the scheme.
		return preg_replace( '/^(https?:\/\/)/', '$1' . $auth . '@', $escaped );
	}

	/**
	 * Modify enqueued styles to replace URLs in inline stylesheets that may contain background images.
	 *
	 * @param string $tag    The `<link>` or `<style>` tag for the enqueued style.
	 *
	 * @return string
	 */
	public function modify_style_loader_tag( string $tag ): string {
		// Early return if not a style with inline content.
		if ( strpos( $tag, '<style' ) === false && strpos( $tag, '<link' ) !== false ) {
			return $tag;
		}

		// Replace URLs inside the tag contents (background images, etc.).
		return preg_replace_callback(
			'/url\((["\']?)(https?:\/\/[^"\')]+)(["\']?)\)/i',
			function ( $matches ) {
				$original_url = $matches[2];
				$replaced_url = $this->get_remote_or_local_url( $original_url );
				return 'url(' . $matches[1] . $this->esc_url_preserve_auth( $replaced_url ) . $matches[3] . ')';
			},
			$tag
		);
	}

	/**
	 * Start output buffering to capture and replace background-image URLs in CSS.
	 */
	public function start_output_buffer(): void {
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			ob_start( array( $this, 'replace_background_image_urls' ) );
		}
	}

	/**
	 * Flush the output buffer on shutdown.
	 */
	public function end_output_buffer(): void {
		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	/**
	 * Replace background-image URLs in output buffer.
	 *
	 * @param string $html The full page output buffer.
	 * @return string Modified output with updated URLs.
	 */
	public function replace_background_image_urls( string $html ): string {
		// CSS background-image url() references.
		$replaced = preg_replace_callback(
			'/url\((["\']?)(https?:\/\/[^"\')]+)(["\']?)\)/i',
			function ( $matches ) {
				$url         = $matches[2];
				$updated_url = $this->get_remote_or_local_url( $url );
				return 'url(' . $matches[1] . $this->esc_url_preserve_auth( $updated_url ) . $matches[3] . ')';
			},
			$html
		);

		// preg_* returns null on failure (backtrack/recursion limits). This runs
		// inside an output buffer, so returning that null would blank the page.
		// Degrade to the untouched markup instead.
		if ( null !== $replaced ) {
			$html = $replaced;
		}

		// Markup that writes media URLs straight into attributes instead of going
		// through the attachment API. Page builders do this constantly, so the
		// wp_get_attachment_* filters never see those URLs.
		$replaced = preg_replace_callback(
			'/\b(srcset|data-srcset|src|data-src)=(["\'])([^"\']*)\2/i',
			function ( $matches ) {
				$attr  = $matches[1];
				$quote = $matches[2];
				$parts = ( false !== stripos( $attr, 'srcset' ) ) ? explode( ',', $matches[3] ) : array( $matches[3] );

				foreach ( $parts as $i => $part ) {
					$candidate = trim( $part );
					if ( '' === $candidate ) {
						continue;
					}

					// srcset candidates are "<url> <descriptor>".
					$bits       = preg_split( '/\s+/', $candidate, 2 );
					$url        = $bits[0];
					$descriptor = isset( $bits[1] ) ? ' ' . $bits[1] : '';

					if ( ! $this->is_local_media_url( $url ) ) {
						continue;
					}

					$parts[ $i ] = $this->esc_url_preserve_auth( $this->get_remote_or_local_url( $url ) ) . $descriptor;
				}

				return $attr . '=' . $quote . implode( ', ', $parts ) . $quote;
			},
			$html
		);

		if ( null !== $replaced ) {
			$html = $replaced;
		}

		return $html;
	}

	/**
	 * Whether a URL points at a media file inside this site's uploads directory.
	 *
	 * Deliberately narrow: same host as the site, path under the uploads base,
	 * and a media file extension. That keeps script/stylesheet attributes out of
	 * the rewrite even when they are served from uploads, as bundlers often do.
	 *
	 * @param string $url The URL to test.
	 *
	 * @return bool
	 */
	protected function is_local_media_url( string $url ): bool {
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		$baseurl = $uploads['baseurl'] ?? '';
		if ( '' === $baseurl ) {
			return false;
		}

		$base_path = (string) wp_parse_url( $baseurl, PHP_URL_PATH );
		$url_path  = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $base_path || 0 !== strpos( $url_path, $base_path ) ) {
			return false;
		}

		if ( (string) wp_parse_url( $url, PHP_URL_HOST ) !== (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return false;
		}

		return (bool) preg_match( '/\.(?:png|jpe?g|gif|webp|avif|svg|bmp|ico|tiff?)$/i', $url_path );
	}
}

add_action(
	'plugins_loaded',
	function () {
		( new MG_Media_Management() )->init();
	}
);
