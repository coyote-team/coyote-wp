<?php
/**
 * Coyote Image Descriptions Coyote Filters Tests.
 *
 * @since   0.0.0
 * @package Coyote_Image_Descriptions
 */
class CID_Coyote_Filters_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.0.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'CID_Coyote_Filters' ) );
	}

	/**
	 * Test that we can access our class through our helper function.
	 *
	 * @since  0.0.0
	 */
	function test_class_access() {
		$this->assertInstanceOf( 'CID_Coyote_Filters', cid()->coyote-filters );
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
