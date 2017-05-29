<?php
/**
 * Coyote Image Descriptions Coyote Api.
 *
 * @since   0.1.0
 * @package Coyote_Image_Descriptions
 */

/**
 * Coyote Image Descriptions Coyote Api.
 *
 * @since 0.1.0
 */
class CID_Coyote_Api {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.1.0
	 *
	 * @var   Coyote_Image_Descriptions
	 */
	protected $plugin = null;

	/**
	 * Coyote API URL.
	 *
	 * @since 0.1.0
	 *
	 * @var   string
	 */
	protected $url = '';

	/**
	 * Default request headers.
	 *
	 * @since 0.1.0
	 *
	 * @var   array
	 */
	protected $headers = array();

	/**
	 * Constructor.
	 *
	 * @since  0.1.0
	 *
	 * @param  Coyote_Image_Descriptions $plugin Main plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();

		$this->url = coyote_get_option( 'url' );
		if ( $this->url ) {
			$this->url = rtrim( $this->url, '/' ) . '/';
		}

		$this->headers = array(
			'Content-Type' => 'application/json',
			'X-User-Email' => coyote_get_option( 'user_email' ),
			'X-User-Token' => coyote_get_option( 'api_key' ),
		);
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {

	}

	/**
	 * Register an image with Coyote
	 *
	 * @since  0.1.0
	 *
	 * @param WP_Post $image An image to register with coyote.
	 * @return string|null
	 */
	public function register( $image ) {
		$image_source = wp_get_attachment_image_src( $image->ID, 'full' );
		$default_group_id = (int) coyote_get_option( 'default_group_id' );
		$website_id = (int) coyote_get_option( 'website_id' );

		$data = array(
			'image' => array(
				'canonical_id' => $image->guid,
				'path' => wp_make_link_relative( $image_source[0] ),
				'website_id' => $website_id ? $website_id : 1,
				'group_id' => $default_group_id ? $default_group_id : 1,
				'page_urls' => array(),
				'priority' => false,
			),
		);

		$response = wp_safe_remote_post( $this->url . 'images.json', array(
			'headers' => $this->headers,
			'body' => wp_json_encode( $data ),
		) );

		if ( ! is_wp_error( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}
	}

	/**
	 * Unregister an image with Coyote
	 *
	 * @since  0.1.0
	 *
	 * @param WP_Post $image     An image to register with coyote.
	 * @param integer $coyote_id The Coyote ID assigned to the image.
	 * @return stdClass|bool
	 */
	public function unregister( WP_Post $image, $coyote_id ) {
		$coyote_image = $this->get_image( $coyote_id );

		if ( $coyote_image && $coyote_image['canonical_id'] === $image->guid ) {
			$response = wp_safe_remote_request( $this->url . 'images/' . $coyote_id . '.json', array(
				'headers' => $this->headers,
				'method' => 'DELETE',
			) );

			if ( ! is_wp_error( $response ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get an image from Coyote
	 *
	 * @since  0.1.0
	 *
	 * @param integer $coyote_id The Coyote ID assigned to the image.
	 * @return array|bool
	 */
	public function get_image( $coyote_id ) {
		$coyote_image = get_transient( 'coyote_image_' . $coyote_id );

		if ( false === $coyote_image ) {
			$response = wp_safe_remote_get( $this->url . 'images/' . $coyote_id . '.json', array(
				'headers' => $this->headers,
			) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$coyote_image = json_decode( wp_remote_retrieve_body( $response ), true );
				set_transient( 'coyote_image_' . $coyote_id, $coyote_image, (MINUTE_IN_SECONDS * 10) );
			}
		}

		return $coyote_image;
	}

	/**
	 * Get Coyote meta
	 *
	 * @since  0.1.0
	 *
	 * @return array
	 */
	public function get_meta() {
		$coyote_meta = get_transient( 'coyote_meta' );

		if ( false === $coyote_meta ) {
			$response = wp_safe_remote_get( $this->url . 'meta.json', array(
				'headers' => $this->headers,
			) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$coyote_meta = json_decode( wp_remote_retrieve_body( $response ), true );
				set_transient( 'coyote_meta', $coyote_meta, DAY_IN_SECONDS );
			}
		}

		$coyote_meta = ( false === $coyote_meta ) ? array() : $coyote_meta;
		return $coyote_meta;
	}

	/**
	 * Get the link to annotate an image on Coyote
	 *
	 * @since  0.1.0
	 *
	 * @param integer $coyote_id The Coyote ID assigned to the image.
	 * @return string
	 */
	public function get_image_endpoint( $coyote_id ) {
		return $this->url . 'images/' . $coyote_id;
	}
}
