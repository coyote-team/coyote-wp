<?php
/**
 * Coyote_Image_Descriptions.
 *
 * @since   0.0.0
 * @package Coyote_Image_Descriptions
 */
class Coyote_Image_Descriptions_Test extends WP_UnitTestCase {

	/**
	 * Test if our class exists.
	 *
	 * @since  0.0.0
	 */
	function test_class_exists() {
		$this->assertTrue( class_exists( 'Coyote_Image_Descriptions') );
	}

	/**
	 * Test that our main helper function is an instance of our class.
	 *
	 * @since  0.0.0
	 */
	function test_get_instance() {
		$this->assertInstanceOf(  'Coyote_Image_Descriptions', cid() );
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
