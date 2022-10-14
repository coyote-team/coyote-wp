<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once('vendor/autoload.php');

use Coyote\ContentHelper;

class TestContentHelper extends TestCase
{

    public function testCreateContentHelper(){
        $helper = new ContentHelper("<img src='foo.jpg'>");
        $this->assertInstanceOf(ContentHelper::class, $helper);
    }

    public function testWellFormedHTML(){
        $wellHTML = "<!DOCTYPE html>\n<html><head><title>Test</title></head><body><img src=\"foo.jpg\"></body></html>";
        $helper = new ContentHelper($wellHTML);
        $map = [];
        $html = $helper->setImageAlts($map);
        $this->assertEquals($wellHTML, $html);
    }

    public function testMalformedHTML(){
        $malHTML = "<img src=\"foo.jpg\"";
        $helper = new ContentHelper($malHTML);
        $map = [];
        $html = $helper->setImageAlts($map);
        $this->assertStringContainsString("<img src=\"foo.jpg\">", $html);
    }

    public function testLoadSectionInContentHelper(){
        $helper = new ContentHelper("<footer></footer>");
        $this->assertInstanceOf(ContentHelper::class, $helper);
    }

    public function testGetImages(){
        $helper = new ContentHelper("<img src='foo.jpg'>");
        $images = $helper->getImages();
        $this->assertCount(1, $images);
    }

    public function testIgnoreNoSrcImage(){
        $helper = new ContentHelper("<img>");
        $images = $helper->getImages();
        $this->assertCount(0, $images);
    }

    public function testGetImagesWithSrc(){
        $helper = new ContentHelper("<img src='foo.jpg'>");
        $images = $helper->getImages();
        $src = $images[0]->src;
        $this->assertEquals($src, 'foo.jpg');
    }

    public function testNoImagesAtAll(){
        $helper = new ContentHelper("<html></html>");
        $images = $helper->getImages();
        $this->assertCount(0, $images);
    }

    public function testReturnsImageInstanceArray(){
        $helper = new ContentHelper("<img src='foo.jpg'>");
        $images = $helper->getImages();
        $this->assertInstanceOf(ContentHelper\Image::class, array_pop($images));
    }

    public function testSetAltWithMap(){
        $helper = new ContentHelper("<img src='foo.jpg'><img src='boo.jpg'><img src='notthere.jpg'>");
        $map = ['foo.jpg' => 'one', 'boo.jpg' => 'two', 'fail.jpg' => 'shouldnt work'];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        
        $images = $newHelper->getImages();
        
        $alt1 = $images[0]->alt;
        $alt2 = $images[1]->alt;
        $alt3 = $images[2]->alt;

        $this->assertEquals($alt1, 'one');
        $this->assertEquals($alt2, 'two');
        $this->assertEquals($alt3, '');
    }

    public function testSetAltWithEmptyMap(){
        $helper = new ContentHelper("<img src='foo.jpg'><img src='boo.jpg' alt='two'><img src='notthere.jpg'>");
        $map = [];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        
        $images = $newHelper->getImages();
        
        $alt1 = $images[0]->alt;
        $alt2 = $images[1]->alt;
        $alt3 = $images[2]->alt;

        $this->assertEquals($alt1, '');
        $this->assertEquals($alt2, 'two');
        $this->assertEquals($alt3, '');
    }

    public function testNoSrcInHTMLMatchingMapSrc(){
        $helper = new ContentHelper("<img src='foo.jpg'>");
        $map = ['fo.jpg' => 'one', 'bo.jpg' => 'two', 'fail.jpg' => 'shouldnt work'];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        
        $images = $newHelper->getImages();
        
        $this->assertCount(1, $images);
    }

    public function testChangeAltFromCurrentWithMap(){
        $helper = new ContentHelper("<img src='foo.jpg' alt='three'><img src='boo.jpg' alt='four'><img src='notthere.jpg' alt='five'>");
        $map = ['foo.jpg' => 'one', 'boo.jpg' => 'two', 'fail.jpg' => 'shouldnt work'];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        
        $images = $newHelper->getImages();
        
        $alt1 = $images[0]->alt;
        $alt2 = $images[1]->alt;
        $alt3 = $images[2]->alt;

        $this->assertEquals($alt1, 'one');
        $this->assertEquals($alt2, 'two');
        $this->assertEquals($alt3, 'five');
    }

    public function testChangeElementsAltWithSameSrc(){
        $helper = new ContentHelper("<img src='foo.jpg' alt='three'><img src='foo.jpg' alt='four'><img src='notthere.jpg' alt='five'>");
        $map = ['foo.jpg' => 'one', 'boo.jpg' => 'two', 'fail.jpg' => 'shouldnt work'];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        
        $images = $newHelper->getImages();
        
        $alt1 = $images[0]->alt;
        $alt2 = $images[1]->alt;
        $alt3 = $images[2]->alt;

        $this->assertEquals($alt1, 'one');
        $this->assertEquals($alt2, 'one');
        $this->assertEquals($alt3, 'five');
    }

    public function testChangeElementsAltWithNoImageElements(){
        $helper = new ContentHelper("<html></html>");
        $map = ['foo.jpg' => 'one', 'boo.jpg' => 'two', 'fail.jpg' => 'shouldnt work'];
        
        $newText = $helper->setImageAlts($map);
        $newHelper = new ContentHelper($newText);
        $images = $newHelper->getImages();

        $this->assertCount(0, $images);
    }

    /**
     * @dataProvider beforeAfterData
     */
    public function testParseBeforeAfterContent($expectedResult, $input){
        $helper = new ContentHelper($input);
        $images = $helper->getImages();

        $beforeAfterArr = [$images[0]->content_before, $images[0]->content_after];

        $this->assertSame($expectedResult,$beforeAfterArr);
    }

    private function beforeAfterData(): array
    {
        return [
            [
                ['',''],
                '<div><img src="test"></div>',
            ],
            [
                ['before',''],
                '<div>before<img src="test"></div>',
            ],
            [
                ['','after'],
                '<div><img src="test">after</div>',
            ],
            [
                ['before','after'],
                '<div>before<img src="test">after</div>'
            ],
            [
                ['This is extra text before',''],
                '<div>This is extra text<!-- Comment -->before<img src="test"></div>',
            ],
            [
                ['[caption]', 'This is a test caption[/caption]'],
                '<div>[caption]<img src="test">This is a test caption[/caption]</div>',
            ],
        ];
    }

}