<?php
/**
 * Coyote Image Descriptions Image Lifecycle Events Tests.
 *
 * @since   0.0.0
 * @package Coyote_Image_Descriptions
 */
class CID_Image_Lifecycle_Events_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.0.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'CID_Image_Lifecycle_Events' ) );
	}

	/**
	 * Test that we can access our class through our helper function.
	 *
	 * @since  0.0.0
	 */
	function test_class_access() {
		$this->assertInstanceOf( 'CID_Image_Lifecycle_Events', cid()->image-lifecycle-events );
	}

	/**
	 * Replace this with some actual testing code.
	 *
	 * @since  0.0.0
	 */
	function test_sample() {
		$this->assertTrue( true );
	}
}
