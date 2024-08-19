<?php
/**
 * Plugin Name: MG Media Management
 * Plugin URI: https://github.com/mgratch/mg-media-management/
 * Description: Leverages local media when available, otherwise falls back to a specified production server.
 * Author: Marc Gratch
 * Author URI: https://marcgratch.com
 * Version: 1.0.0
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
	}

	/**
	 * Modify the main image source URL.
	 *
	 * @param array $image The image source URL.
	 *
	 * @return array
	 */
	public function modify_image_src( array $image ): array {
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
			if ( isset( $blog_details->path ) && ! str_contains( $url, $blog_details->path ) ) {
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
		return str_replace( $upload_locations['baseurl'], $upload_locations['basedir'], $url );
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
}

add_action(
	'muplugins_loaded',
	function () {
		( new MG_Media_Management() )->init();
	}
);
