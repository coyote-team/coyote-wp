<?php
/**
 * Plugin Name: Coyote Image Descriptions
 * Plugin URI:  https://coyote.pics/
 * Description: The open-source Coyote software was developed by the Museum of Contemporary Art Chicago to support a distributed workflow for describing images in our web CMS and publishing those descriptions to our public website.
 * Version:     0.1.1
 * Author:      The Andy Warhol Museum
 * Author URI:  http://www.warhol.org/
 * Donate link: https://coyote.pics/
 * License:     GPLv2
 * Text Domain: coyote-image-descriptions
 * Domain Path: /languages
 *
 * @link    https://coyote.pics/
 *
 * @package Coyote_Image_Descriptions
 * @version 0.1.1
 *
 * Built using generator-plugin-wp (https://github.com/WebDevStudios/generator-plugin-wp)
 */

/**
 * Copyright (c) 2017 The Andy Warhol Museum (email : information@warhol.org)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


// Use composer autoload with PHP 5.2 compatibility.
require 'vendor/autoload_52.php';

/**
 * Main initiation class.
 *
 * @since  0.1.0
 */
final class Coyote_Image_Descriptions {

	/**
	 * Current version.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	const VERSION = '0.1.1';

	/**
	 * URL of plugin directory.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Detailed activation error messages.
	 *
	 * @var    array
	 * @since  0.1.0
	 */
	protected $activation_errors = array();

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    Coyote_Image_Descriptions
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of CID_Plugin_Options
	 *
	 * @since0.1.0
	 * @var CID_Plugin_Options
	 */
	protected $plugin_options;

	/**
	 * Instance of CID_Image_Batch_Action
	 *
	 * @since0.1.0
	 * @var CID_Image_Batch_Action
	 */
	protected $image_batch_action;

	/**
	 * Instance of CID_Coyote_Api
	 *
	 * @since0.1.0
	 * @var CID_Coyote_Api
	 */
	protected $coyote_api;

	/**
	 * Instance of CID_Image_Lifecycle_Events
	 *
	 * @since0.1.0
	 * @var CID_Image_Lifecycle_Events
	 */
	protected $image_lifecycle_events;

	/**
	 * Instance of CID_Coyote_Filters
	 *
	 * @since0.1.0
	 * @var CID_Coyote_Filters
	 */
	protected $coyote_filters;

	/**
	 * Instance of CID_Shortcodes
	 *
	 * @since0.1.0
	 * @var CID_Shortcodes
	 */
	protected $shortcodes;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since   0.1.0
	 * @return  Coyote_Image_Descriptions A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin.
	 *
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  0.1.0
	 */
	public function plugin_classes() {
		$this->plugin_options = new CID_Plugin_Options( $this );
		$this->coyote_api = new CID_Coyote_Api( $this );
		$this->image_batch_action = new CID_Image_Batch_Action( $this, $this->coyote_api );
		$this->image_lifecycle_events = new CID_Image_Lifecycle_Events( $this, $this->coyote_api );
		$this->coyote_filters = new CID_Coyote_Filters( $this );
		$this->shortcodes = new CID_Shortcodes( $this );
	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters.
	 * Priority needs to be
	 * < 10 for CPT_Core,
	 * < 5 for Taxonomy_Core,
	 * and 0 for Widgets because widgets_init runs at init priority 1.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Activate the plugin.
	 *
	 * @since  0.1.0
	 */
	public function _activate() {
		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Make sure any rewrite functionality has been loaded.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 * Uninstall routines should be in uninstall.php.
	 *
	 * @since  0.1.0
	 */
	public function _deactivate() {
		// Add deactivation cleanup functionality here.
	}

	/**
	 * Init hooks
	 *
	 * @since  0.1.0
	 */
	public function init() {

		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load translated strings for plugin.
		load_plugin_textdomain( 'coyote-image-descriptions', false, dirname( $this->basename ) . '/languages/' );

		// Initialize plugin classes.
		$this->plugin_classes();
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 *
	 * @since  0.1.0
	 *
	 * @return boolean True if requirements met, false if not.
	 */
	public function check_requirements() {

		// Bail early if plugin meets requirements.
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', array( $this, 'requirements_not_met_notice' ) );

		// Deactivate our plugin.
		add_action( 'admin_init', array( $this, 'deactivate_me' ) );

		// Didn't meet the requirements.
		return false;
	}

	/**
	 * Deactivates this plugin, hook this function on admin_init.
	 *
	 * @since  0.1.0
	 */
	public function deactivate_me() {

		// We do a check for deactivate_plugins before calling it, to protect
		// any developers from accidentally calling it too early and breaking things.
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->basename );
		}
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @since  0.1.0
	 *
	 * @return boolean True if requirements are met.
	 */
	public function meets_requirements() {

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		return true;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @since  0.1.0
	 */
	public function requirements_not_met_notice() {

		// Compile default message.
		$default_message = sprintf( __( 'Coyote Image Descriptions is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'coyote-image-descriptions' ), admin_url( 'plugins.php' ) );

		// Default details to null.
		$details = null;

		// Add details if any exist.
		if ( $this->activation_errors && is_array( $this->activation_errors ) ) {
			$details = '<small>' . implode( '</small><br /><small>', $this->activation_errors ) . '</small>';
		}

		// Output errors.
		?>
		<div id="message" class="error">
			<p><?php echo wp_kses_post( $default_message ); ?></p>
			<?php echo wp_kses_post( $details ); ?>
		</div>
		<?php
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 *
	 * @param  string $field Field to get.
	 * @throws Exception     Throws an exception if the field is invalid.
	 * @return mixed         Value of the field.
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
			case 'plugin_options':
			case 'image_batch_action':
			case 'coyote_api':
			case 'image_lifecycle_events':
			case 'coyote_filters':
			case 'shortcodes':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}

	/**
	 * Get an image description based on the context.
	 *
	 * @since  0.1.0
	 *
	 * @param integer|WP_Post $attachment An image attachment or ID.
	 * @param string          $context    The Coyote context.
	 * @return string
	 */
	public function get_image_description_from_context( $attachment, $context ) {
		if ( is_numeric( $attachment ) ) {
			$attachment = get_post( $attachment );
		}

		$metum_id = 1;
		foreach ( $this->coyote_api->get_meta() as $coyote_metum ) {
			if ( $context === $coyote_metum['title'] ) {
				$metum_id = $coyote_metum['id'];
				break;
			}
		}

		$coyote_id = get_post_meta( $attachment->ID, '_coyote_id', true );
		if ( $coyote_id ) {
			$coyote_image = $this->coyote_api->get_image( $coyote_id );
			if ( $coyote_image && $coyote_image['canonical_id'] === $attachment->guid ) {
				foreach ( $coyote_image['descriptions'] as $description ) {
					if ( $description['metum_id'] === $metum_id ) {
						return $description['text'];
					}
				}
			}
		}

		return '';
	}
}

/**
 * Grab the Coyote_Image_Descriptions object and return it.
 * Wrapper for Coyote_Image_Descriptions::get_instance().
 *
 * @since  0.1.0
 * @return Coyote_Image_Descriptions  Singleton instance of plugin class.
 */
function coyote_image_descriptions() {
	return Coyote_Image_Descriptions::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( coyote_image_descriptions(), 'hooks' ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( coyote_image_descriptions(), '_activate' ) );
register_deactivation_hook( __FILE__, array( coyote_image_descriptions(), '_deactivate' ) );

if ( ! function_exists( 'coyote_get_description' ) ) {
	/**
	 * Get an image description based on the context.
	 *
	 * @since  0.1.0
	 *
	 * @param integer|WP_Post $attachment An image attachment or ID.
	 * @param string          $context    The Coyote context.
	 * @return string
	 */
	function coyote_get_description( $attachment, $context ) {
		$plugin = coyote_image_descriptions();
		return $plugin->get_image_description_from_context( $attachment, $context );
	}
}

if ( ! function_exists( 'coyote_get_image' ) ) {
	/**
	 * Get an image with the Coyote description based on the context.
	 *
	 * @since  0.1.0
	 *
	 * @param integer|WP_Post $attachment An image attachment or ID.
	 * @param string          $context    The Coyote context.
	 * @param string          $size       The image size.
	 * @return string
	 */
	function coyote_get_image( $attachment, $context = '', $size = 'medium' ) {
		$image_id = is_numeric( $attachment ) ? $attachment : $attachment->ID;
		$tag = wp_get_attachment_image( $image_id, $size );

		return apply_filters( 'coyote_image_tag', $tag, $context );
	}
}
