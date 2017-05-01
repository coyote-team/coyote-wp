<?php
/**
 * Coyote Image Descriptions Plugin Options.
 *
 * @since   0.1.0
 * @package Coyote_Image_Descriptions
 */

/**
 * Coyote Image Descriptions Plugin Options class.
 *
 * @since 0.1.0
 */
class CID_Plugin_Options {
	/**
	 * Parent plugin class.
	 *
	 * @var    Coyote_Image_Descriptions
	 * @since  0.1.0
	 */
	protected $plugin = null;

	/**
	 * Option key, and option page slug.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $key = 'coyote';

	/**
	 * Options page metabox ID.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $metabox_id = 'coyote_metabox';

	/**
	 * Options Page title.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $title = '';

	/**
	 * Options Page hook.
	 *
	 * @var string
	 */
	protected $options_page = '';

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

		// Set our title.
		$this->title = esc_attr__( 'Coyote Image Descriptions Plugin Options', 'coyote-image-descriptions' );
	}

	/**
	 * Initiate our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {

		// Hook in our actions to the admin.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );

		add_action( 'cmb2_admin_init', array( $this, 'add_options_page_metabox' ) );

	}

	/**
	 * Register our setting to WP.
	 *
	 * @since  0.1.0
	 */
	public function admin_init() {
		register_setting( $this->key, $this->key );
	}

	/**
	 * Add menu options page.
	 *
	 * @since  0.1.0
	 */
	public function add_options_page() {
		$this->options_page = add_submenu_page(
			'options-general.php',
			esc_attr__( 'Coyote Settings', 'coyote-image-descriptions' ),
			esc_attr__( 'Coyote', 'coyote-image-descriptions' ),
			'manage_options',
			$this->key,
			array( $this, 'admin_page_display' )
		);

		// Include CMB CSS in the head to avoid FOUC.
		add_action( "admin_print_styles-{$this->options_page}", array( 'CMB2_hookup', 'enqueue_cmb_css' ) );
	}

	/**
	 * Admin page markup. Mostly handled by CMB2.
	 *
	 * @since  0.1.0
	 */
	public function admin_page_display() {
		?>
		<div class="wrap cmb2-options-page <?php echo esc_attr( $this->key ); ?>">
			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
			<?php cmb2_metabox_form( $this->metabox_id, $this->key ); ?>
		</div>
		<?php
	}

	/**
	 * Add custom fields to the options page.
	 *
	 * @since  0.1.0
	 */
	public function add_options_page_metabox() {

		// Add our CMB2 metabox.
		$cmb = new_cmb2_box( array(
			'id'         => $this->metabox_id,
			'hookup'     => false,
			'cmb_styles' => false,
			'show_on'    => array(
				'key'   => 'options-page',
				'value' => array( $this->key ),
			),
		) );

		$cmb->add_field( array(
			'name'    => __( 'User Email', 'coyote-image-descriptions' ),
			'id'      => 'user_email',
			'type'    => 'text',
		) );

		$cmb->add_field( array(
			'name'    => __( 'API Key', 'coyote-image-descriptions' ),
			'id'      => 'api_key',
			'type'    => 'text',
		) );

		$cmb->add_field( array(
			'name'    => __( 'Coyote Instance URL', 'coyote-image-descriptions' ),
			'id'      => 'url',
			'type'    => 'text',
		) );

		$cmb->add_field( array(
			'name'    => __( 'Default Group ID', 'coyote-image-descriptions' ),
			'id'      => 'default_group_id',
			'default' => '1',
			'type'    => 'text',
		) );

		$cmb->add_field( array(
			'name'    => __( 'Website ID', 'coyote-image-descriptions' ),
			'id'      => 'website_id',
			'type'    => 'text',
			'default' => '1',
			'description' => __( 'If your Coyote instance serves more than one website, define the website ID here.', 'coyote-image-descriptions' ),
		) );

	}
}

/**
 * Wrapper function around cmb2_get_option
 *
 * @since  0.1.0
 * @param  string $key     Options array key.
 * @param  mixed  $default Optional default value.
 * @return mixed           Option value
 */
function coyote_get_option( $key = '', $default = null ) {
	if ( function_exists( 'cmb2_get_option' ) ) {
		// Use cmb2_get_option as it passes through some key filters.
		return cmb2_get_option( 'coyote', $key, $default );
	}
	// Fallback to get_option if CMB2 is not loaded yet.
	$opts = get_option( 'coyote', $key, $default );
	$val = $default;
	if ( 'all' === $key ) {
		$val = $opts;
	} elseif ( is_array( $opts ) && array_key_exists( $key, $opts ) && false !== $opts[ $key ] ) {
		$val = $opts[ $key ];
	}
	return $val;
}
