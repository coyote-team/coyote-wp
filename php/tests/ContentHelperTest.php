<?php

include './php/classes/helpers/class.content-helper.php';

use PHPUnit\Framework\TestCase;
use Coyote\Helpers\ContentHelper;

class ContentHelperTest extends TestCase {
    public function testNoImageExtraction() {
        $helper = new ContentHelper('<yimg src="foo.bar" alt="" />');
        $this->assertSame($helper->get_images(), []);
    }

    public function testSingleImageExtraction() {
        $helper = new ContentHelper('<img src="foo.bar" alt="" />');
        $this->assertSame($helper->get_images(), ['<img src="foo.bar" alt="" />']);
    }

    public function testMultipleImageExtraction() {
        $helper = new ContentHelper('<img src="foo.bar" alt="" /> <p>Hello!</p> <img src="xuux.bar" alt="" /> <p>Hi!</p>');
        $this->assertSame($helper->get_images(), ['<img src="foo.bar" alt="" />', '<img src="xuux.bar" alt="" />']);
    }

    public function testWhiteSpacedImageExtraction() {
        $helper = new ContentHelper('<img      src="foo.bar" /> <p>Hello!</p> <img src="xuux.bar"        alt=""     > <p>Hi!</p>');
        $this->assertSame($helper->get_images(), ['<img      src="foo.bar" />', '<img src="xuux.bar"        alt=""     >']);
    }

    public function testMultiLineImageExtraction() {
        $helper = new ContentHelper('<img
    src="foo.bar"
    
    
/>');
        $images = $helper->get_images();
        $this->assertSame($images, ['<img
    src="foo.bar"
    
    
/>']);
    }

    public function testImgSrcExtraction() {
        $src = ContentHelper::get_img_src('<img src="foo.bar" alt="hello" />');
        $this->assertSame($src, "foo.bar");
    }

    public function testImgSrcExtraction2() {
        $src = ContentHelper::get_img_src('<img src="https://i.imgur.com/9O2yaA8.jpeg" alt="Dit is alt tekst."/>');
        $this->assertSame($src, "https://i.imgur.com/9O2yaA8.jpeg");
    }

    public function testBadImgSrcExtraction() {
        $src = ContentHelper::get_img_src('<img  alt="hello" />');
        $this->assertSame($src, null);
    }

    public function testEmojiImgSrcExtraction() {
        $src = ContentHelper::get_img_src('<img src="foo.🚒" alt="hello" />');
        $this->assertSame($src, null);
    }

    public function testImgAltExtraction() {
        $src = ContentHelper::get_img_alt('<img src="foo.bar" alt="hello" />');
        $this->assertSame($src, "hello");
    }

    public function testBadImgAltExtraction() {
        $src = ContentHelper::get_img_alt('<img src="foo.bar" />');
        $this->assertSame($src, null);
    }

    public function testComplexImgAltExtraction() {
        $src = ContentHelper::get_img_alt('<img src="foo.bar" ALT = "Hello.
This is complex alt.
It has \"escaped\" characters.    "      >');

        $this->assertSame($src, 'Hello.
This is complex alt.
It has \"escaped\" characters.    ');
    }

    public function testImgAltReplace() {
        $element = '<img src="foo.bar" alt="hello" />';
        $expected = '<img src="foo.bar" alt="world" />';

        $helper = new ContentHelper($element);
        $helper->replace_img_alt($element, "world"); 

        $this->assertSame($helper->get_content(), $expected);

    }

    public function testGetMissingCoyoteId() {
        $element = '<img src="foo.bar" alt="hello" />';
        $expected = null;

        $actual = ContentHelper::get_coyote_id($element); 

        $this->assertSame($actual, $expected);
    }

    public function testGetExistingCoyoteId1() {
        $element = '<img src="foo.bar" data-coyote-id = "123"    alt="hello" />';
        $expected = '123';

        $actual = ContentHelper::get_coyote_id($element); 

        $this->assertSame($actual, $expected);
    }

    public function testGetExistingCoyoteId2() {
        $element = '<img src="foo.bar" alt="hello" data-coyote-id="123"/>';
        $expected = '123';

        $actual = ContentHelper::get_coyote_id($element); 

        $this->assertSame($actual, $expected);
    }

    public function testSetCoyoteId1() {
        $element = '<img src="foo.bar" alt="hello" />';
        $expected = '<img data-coyote-id="123" src="foo.bar" alt="hello" />';

        $helper = new ContentHelper($element);
        $helper->set_coyote_id($element, "123"); 

        $this->assertSame($helper->get_content(), $expected);
    }

    public function testSetCoyoteId2() {
        $element = '<img      src="foo.bar" alt="hello" />';
        $expected = '<img data-coyote-id="123" src="foo.bar" alt="hello" />';

        $helper = new ContentHelper($element);
        $helper->set_coyote_id($element, "123"); 

        $this->assertSame($helper->get_content(), $expected);
    }

    public function testOverwriteCoyoteId1() {
        $element = '<img src="foo.bar" alt="hello" data-coyote-id="foo"/>';
        $expected = '<img src="foo.bar" alt="hello" data-coyote-id="123"/>';

        $helper = new ContentHelper($element);
        $helper->set_coyote_id($element, "123"); 

        $this->assertSame($helper->get_content(), $expected);
    }

    public function testOverwriteCoyoteId2() {
        $element = '<img data-coyote-id   =   "foo"     src="foo.bar" alt="hello"/>';
        $expected = '<img data-coyote-id="123"     src="foo.bar" alt="hello"/>';

        $helper = new ContentHelper($element);
        $helper->set_coyote_id($element, "123"); 

        $this->assertSame($helper->get_content(), $expected);
    }


}
