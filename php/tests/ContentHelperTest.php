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

}
