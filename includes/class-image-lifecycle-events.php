<?php
/**
 * Coyote Image Descriptions Image Lifecycle Events.
 *
 * @since   0.1.0
 * @package Coyote_Image_Descriptions
 */

/**
 * Coyote Image Descriptions Image Lifecycle Events.
 *
 * @since 0.1.0
 */
class CID_Image_Lifecycle_Events {
	/**
	 * Parent plugin class.
	 *
	 * @since 0.1.0
	 *
	 * @var   Coyote_Image_Descriptions
	 */
	protected $plugin = null;

	/**
	 * Coyote API class.
	 *
	 * @since 0.1.0
	 *
	 * @var   CID_Image_Batch_Action
	 */
	protected $api = null;

	/**
	 * Constructor.
	 *
	 * @since  0.1.0
	 *
	 * @param  Coyote_Image_Descriptions $plugin Main plugin object.
	 * @param  CID_Coyote_Api            $api    Object to handle Coyote API calls.
	 */
	public function __construct( $plugin, CID_Coyote_Api $api ) {
		$this->plugin = $plugin;
		$this->api = $api;
		$this->hooks();
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {
		add_action( 'add_attachment', array( $this, 'register_with_coyote' ) );
		add_action( 'delete_attachment', array( $this, 'delete_from_coyote' ) );
	}

	/**
	 * Register an image with the Coyote database when added to WordPress
	 *
	 * @since  0.1.0
	 *
	 * @param integer $attachment_id ID of the attachment.
	 * @return void
	 */
	public function register_with_coyote( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( $attachment ) {
			$coyote_data = $this->api->register( $attachment );
			if ( $coyote_data ) {
				add_post_meta( $attachment->ID, '_coyote_id', $coyote_data['id'], true );
			}
		}
	}

	/**
	 * Delete an image from the Coyote database when deleted from WordPress
	 *
	 * @since  0.1.0
	 *
	 * @param integer $attachment_id ID of the attachment.
	 * @return void
	 */
		public function delete_from_coyote( $attachment_id ) {
		$coyote_id = (int) get_post_meta( $attachment_id, '_coyote_id', true );
		if ( $coyote_id ) {
			$attachment = get_post( $attachment_id );
			$this->api->unregister( $attachment, $coyote_id );
		}
	}
}
