<?php
/**
 * Coyote Image Descriptions Coyote Api Tests.
 *
 * @since   0.0.0
 * @package Coyote_Image_Descriptions
 */
class CID_Coyote_Api_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.0.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'CID_Coyote_Api' ) );
	}

	/**
	 * Test that we can access our class through our helper function.
	 *
	 * @since  0.0.0
	 */
	function test_class_access() {
		$this->assertInstanceOf( 'CID_Coyote_Api', cid()->coyote-api );
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
