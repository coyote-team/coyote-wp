<?php
/**
 * Coyote Image Descriptions Shortcodes.
 *
 * @since   0.1.0
 * @package Coyote_Image_Descriptions
 */

/**
 * Coyote Image Descriptions Shortcodes.
 *
 * @since 0.1.0
 */
class CID_Shortcodes {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.1.0
	 *
	 * @var   Coyote_Image_Descriptions
	 */
	protected $plugin = null;

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
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {
		add_shortcode( 'coyote', array( $this, 'coyote_shortcode' ) );
	}

	/**
	 * Main coyote shortcode.
	 *
	 * @since  0.1.0
	 *
	 * @param array  $atts    Array of shortcode attributes.
	 * @param string $content Html content.
	 */
	public function coyote_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'metum' => '',
				'size' => 'medium',
			),
			$atts
		);

		if ( is_numeric( $content ) ) {
			$content = wp_get_attachment_image( $content, $atts['size'] );
		}

		$content = apply_filters( 'coyote_image_tag', $content, $atts['metum'] );

		return do_shortcode( $content );
	}
}
