<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://coyote.pics
 * @since             1.0.0
 * @package           Coyote
 *
 * @wordpress-plugin
 * Plugin Name:       Coyote Image Annotation
 * Plugin URI:        http://coyote.pics/
 * Description:       A plugin that allows users to annotate their images with Coyote and then display those annotations.
 * Version:           1.0.0
 * Author:            Coyote
 * Author URI:        http://coyote.pics/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       coyote
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-coyote-activator.php
 */
function activate_coyote() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-coyote-activator.php';
	Coyote_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-coyote-deactivator.php
 */
function deactivate_coyote() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-coyote-deactivator.php';
	Coyote_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_coyote' );
register_deactivation_hook( __FILE__, 'deactivate_coyote' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-coyote.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_coyote() {

	$plugin = new Coyote();
	$plugin->run();

}
run_coyote();
