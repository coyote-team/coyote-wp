<?php

declare(strict_types=1);

namespace Coyote;

use Coyote\ContentHelper\Image;
use IvoPetkov\HTML5DOMDocument;

class ContentHelper
{
    private \IvoPetkov\HTML5DOMDocument $dom;
    private const LEFT = 0;
    private const RIGHT = 1;

    public function __construct(string $html)
    {
        $this->dom = new \IvoPetkov\HTML5DOMDocument();
        $this->dom->loadHTML($html);
        /* LoadHTML() doesn't return false on error (It Should),
            so this conditional checks manually if there are any */
        if (count(libxml_get_errors()) > 0) {
            libxml_clear_errors();
            throw new Exception('Malformed HTML');
        }
    }

    /*
        Given a html string, either an entire or partial document, return all image elements
        (these can be DOMElement instances)
    */
    public function getImages()
    {

        $images = $this->dom->getElementsByTagName('img');
        $imageObjects = [];
        foreach ($images as $image) {
            $src = $image->getAttribute('src');
            if (!$src) {
                continue;
            }
            $alt = $image->getAttribute('alt');
            $class = $image->getAttribute('class');
            $content_before = $this->findTextContent($image->previousSibling, '', self::LEFT);
            $content_after = $this->findTextContent($image->nextSibling, '', self::RIGHT);
            $contentImage = new Image($src, $alt, $class, $content_before, $content_after);
            array_push($imageObjects, $contentImage);
        }

        return $imageObjects;
    }

    private function findTextContent(?\DOMNode $node, string $textContent, int $direction): string
    {
        if (is_null($node)) {
            return trim($textContent);
        }

        if ($node->nodeType === XML_ELEMENT_NODE) {
            return trim($textContent);
        }

        $nextNode = $direction === self::LEFT ? $node->previousSibling : $node->nextSibling;

        if ($node->nodeType === XML_TEXT_NODE) {
            $textContent = join(' ', $direction === self::LEFT ?
                [$node->nodeValue, $textContent] : [$textContent, $node->nodeValue]);
        }

        return $this->findTextContent($nextNode, $textContent, $direction);
    }

    /*
        Given a src string, and an alt string
        set the alt text equal to the provided alt text
        for each element with the same src
    */
    private function setImageAlt(string $src, string $alt): void
    {

        $xpath = new \DOMXPATH($this->dom);
        $images = $xpath->evaluate("//img[@src=\"{$src}\"]");

        if (is_null($images) || $images === false) {
            return;
        }

        foreach ($images as $image) {
            $image->removeAttribute('alt');
            $image->setAttribute('alt', $alt);
        }
    }

    /*
        Given a html string, either an entire or partial document,
        and a map of image sources and their alt attributes,
        set the alt text for each image and return the modified html
    */
    public function setImageAlts($map): string
    {

        foreach ($map as $src => $alt) {
            $this->setImageAlt($src, $alt);
        }

        $html = $this->dom->saveHTML();

        return $html;
    }
}
