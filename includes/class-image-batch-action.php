<?php
/**
 * Coyote Image Descriptions Image Batch Action.
 *
 * @since   0.1.0
 * @package Coyote_Image_Descriptions
 */

/**
 * Coyote Image Descriptions Image Batch Action.
 *
 * @since 0.1.0
 */
class CID_Image_Batch_Action {
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
		add_filter( 'bulk_actions-upload', array( $this, 'custom_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'register_with_coyote_handler' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );

		add_filter( 'manage_media_columns', array( $this, 'coyote_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'coyote_column_value' ), 10, 2 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'register_coyote_column_sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'make_coyote_column_sortable' ), 1 );

		add_filter( 'media_row_actions', array( $this, 'add_image_actions_link' ), 10, 3 );
	}

	/**
	 * Add a custom action link to the media item in the Media Library
	 *
	 * @since  0.1.0
	 *
	 * @param array   $actions  An array of action links for each attachment.
	 * @param WP_Post $post     WP_Post object for the current attachment.
	 * @param bool    $detached Whether the list table contains media not attached.
	 * @return array
	 */
	public function add_image_actions_link( $actions, $post, $detached ) {
		$coyote_id = get_post_meta( $post->ID, '_coyote_id', true );

		if ( $coyote_id ) {
			$actions['coyote_annotate'] = sprintf(
				'<a target="_blank" href="%s" aria-label="%s" rel="permalink">%s</a>',
				$this->api->get_image_endpoint( $coyote_id ),
				esc_attr( __( 'Annotate', 'coyote-image-descriptions' ) ),
				__( 'Annotate', 'coyote-image-descriptions' )
			);
		}

		return $actions;
	}

	/**
	 * Add column name to media library
	 *
	 * @since  0.1.0
	 *
	 * @param array $columns Array of column names to filter.
	 * @return array
	 */
	public function coyote_column( $columns ) {
		$columns['has_coyote_id'] = 'Has Coyote ID';
		return $columns;
	}

	/**
	 * Print the item's Coyote ID if it exists
	 *
	 * @since  0.1.0
	 *
	 * @param string $column_name Name of the current column.
	 * @param int    $post_id ID of the current post.
	 * @return void
	 */
	public function coyote_column_value( $column_name, $post_id ) {
		if ( 'has_coyote_id' === $column_name ) {
			$coyote_id = get_post_meta( $post_id, '_coyote_id', true );
			if ( $coyote_id ) {
				esc_html_e( 'Yes', 'coyote-image-descriptions' );
			}
		}
	}


	/**
	 * Register the Coyote ID column as sortable
	 *
	 * @since  0.1.0
	 *
	 * @param array $columns Array of column names to filter.
	 * @return array
	 */
	public function register_coyote_column_sortable( $columns ) {
		$columns['has_coyote_id'] = 'has_coyote_id';
		return $columns;
	}

	/**
	 * Make the Coyote ID column sortable
	 *
	 * @since  0.1.0
	 *
	 * @param WP_Query $query The current query.
	 * @return void
	 */
	public function make_coyote_column_sortable( $query ) {
		if ( $query->is_main_query() && $query->get( 'orderby' ) === 'has_coyote_id' ) {
			$query->set( 'meta_query', array(
				'relation' => 'OR',
				array(
				  'key' => '_coyote_id',
				  'compare' => 'NOT EXISTS',
				),
				array(
				  'key' => '_coyote_id',
				  'compare' => 'EXISTS',
				),
			) );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Define custom bulk actions
	 *
	 * @since  0.1.0
	 *
	 * @param array $bulk_actions Array of bulk actions to filter.
	 * @return array
	 */
	public function custom_bulk_actions( $bulk_actions ) {
		$bulk_actions['register_with_coyote'] = __( 'Register With Coyote', 'coyote-image-descriptions' );
		return $bulk_actions;
	}

	/**
	 * Handle registering images with coyote
	 *
	 * @since  0.1.0
	 *
	 * @param string $redirect_to The redirect URL.
	 * @param string $action The action being taken.
	 * @param array  $image_ids The items to take the action on.
	 *
	 * @return string
	 */
	public function register_with_coyote_handler( $redirect_to, $action, $image_ids ) {
		if ( 'register_with_coyote' !== $action ) {
			return $redirect_to;
		}

		$count = 0;

		$coyote_images = get_posts( array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'meta_key' => '_coyote_id',
			'meta_compare' => 'EXISTS',
		) );

		$images_to_insert = get_posts( array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'post__in' => $image_ids,
			'ignore_sticky_posts' => true,
		) );

		foreach ( $images_to_insert as $image ) {
			if ( ! in_array( $image, $coyote_images, true ) ) {
				$coyote_data = $this->api->register( $image );
				if ( $coyote_data ) {
					add_post_meta( $image->ID, '_coyote_id', $coyote_data['id'], true );
					$count++;
				}
			}
		}

		$redirect_to = add_query_arg( 'coyote_images_registered', $count, $redirect_to );

		return $redirect_to;
	}

	/**
	 * Set the admin notice.
	 *
	 * @since  0.1.0
	 *
	 * @return void
	 */
	public function admin_notice() {
		 if ( ! empty( $_GET['coyote_images_registered'] ) ) {
			$count = intval( $_GET['coyote_images_registered'] );

			echo '<div id="message" class="updated fade">';
			/* translators: number of images successfully registered with Coyote */
			printf( _n( // WPCS: XSS ok.
				'Successfully registered %d image with Coyote.',
				'Successfully registered %d images with Coyote.',
				$count,
				'coyote-image-descriptions'
			), $count );
			echo '</div>';
		}
	}
}
