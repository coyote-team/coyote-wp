<?php

namespace Coyote\Helpers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\Logger;
use Coyote\DB;

class ContentHelper {
    private $content;
    private $images = null;

    public $content_is_modified = false;

    const IMAGE_REGEX = '#(?:(?:\[caption[^\]]*?\].*?)(<\s*img\s+[^>]*?\/?>)(?:\s*<\/a>)?\s*(?:(.*?)\[\/caption\])|<\s*img\s+[^>]*?\/?>)#smi';
    const ALT_REGEX = '/alt\s*=\s*("|\')(.*?)(?!\\\\)\1/smi';
    const SRC_REGEX = '/src\s*=\s*("|\')([^\1]+?)\1/smi';
    const COYOTE_ID_REGEX = '/\s+data-coyote-id\s*=\s*("|\')([0-9]+)\1\s*/smi';

    // not just the value, also the attribute
    const ALT_ATTR_REGEX = '/alt\s*=\s*("|\')(.*?)(?!\\\\)\1/smi';
    const COYOTE_ID_ATTR_REGEX = '/\s+data-coyote-id\s*=\s*("|\')([0-9]+)\1/smi';

    // find a position to insert the coyote ID
    const IMG_ATTR_POSITION_REGEX = '/<\s*img\s+/smi';

    function __construct(string $content) {
        $this->content = $content;
    }

    function replace_image_alts() {
        $matches = array();
        preg_match_all(self::IMAGE_REGEX, $this->content, $matches);

        $match_count = count($matches[0]);

        for ($i = 0; $i < $match_count; $i++) {
            if (isset($matches[2][$i]) && strlen($matches[2][$i])) {
                // this one has a caption
                $element = $matches[1][$i];
            } else {
                $element = $matches[0][$i];
            }

            $src = self::get_img_src($element);

            $alt = DB::get_coyote_alt_by_hash(sha1(($src)));

            if ($alt === null) {
                continue;
            }

            $this->replace_img_alt($element, $alt);
        }

        return $this->content;
    }

    function get_src_and_coyote_id() {
        $matches = array();
        preg_match_all(self::IMAGE_REGEX, $this->content, $matches);

        $match_count = count($matches[0]);
        $images = [];

        for ($i = 0; $i < $match_count; $i++) {
            if (isset($matches[2][$i]) && strlen($matches[2][$i])) {
                // this one has a caption
                $element = $matches[1][$i];
            } else {
                $element = $matches[0][$i];
            }

            $src = self::get_img_src($element);
            $coyote_id = DB::get_coyote_id_by_hash(sha1($src));

            if ($coyote_id === null) {
                continue;
            }

            $images[$src] = $coyote_id;
        }

        return $images;
    }

    function get_images() {
        if ($this->images !== null) {
            return $this->images;
        }

        $matches = array();
        preg_match_all(self::IMAGE_REGEX, $this->content, $matches);

        $match_count = count($matches[0]);
        $images = [];

        for ($i = 0; $i < $match_count; $i++) {
            if (isset($matches[2][$i]) && strlen($matches[2][$i])) {
                // this one has a caption
                $element = $matches[1][$i];
                $caption = $matches[2][$i];
            } else {
                $element = $matches[0][$i];
                $caption = null;
            }

            array_push($images, [
                'element' => $element,
                'caption' => $caption,
                'src' => self::get_img_src($element),
                'alt' => self::get_img_alt($element),
                'coyote_id' => self::get_coyote_id($element)
            ]);
        }

        return $this->images = $images;
    }

    static function get_img_src(string $element) {
        $matches = array();
        $result = preg_match(self::SRC_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    static function get_img_alt(string $element) {
        $matches = array();
        $result = preg_match(self::ALT_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    public function replace_img_alt(string $element, string $alt) {
        $replacement_alt = 'alt="' . htmlspecialchars($alt) . '"';

        if (self::get_img_alt($element) === null) {
            // no alt on this element to begin with? Enforce it
            $replacement_attr = "<img {$replacement_alt} ";
            $replacement_element = preg_replace(self::IMG_ATTR_POSITION_REGEX, $replacement_attr, $element);
        } else {
            $replacement_element = preg_replace(self::ALT_ATTR_REGEX, $replacement_alt, $element);
        }

        $replaced = str_replace($element, $replacement_element, $this->content);

        if (strcmp($element,$replacement_element) != 0) {
           $this->content_is_modified = true;
        }

        $this->content = $replaced;

        return $replacement_element;
    }

    public static function get_coyote_id(string $element) {
        $matches = array();
        $result = preg_match(self::COYOTE_ID_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    public function set_coyote_id(string $element, string $coyote_id) {
        $replacement_element = null;

        if (self::get_coyote_id($element) === null) {
            $replacement_attr = "<img data-coyote-id=\"{$coyote_id}\" ";
            $replacement_element = preg_replace(self::IMG_ATTR_POSITION_REGEX, $replacement_attr, $element);
        } else {
            $replacement_attr = " data-coyote-id=\"{$coyote_id}\"";
            $replacement_element = preg_replace(self::COYOTE_ID_ATTR_REGEX, $replacement_attr, $element);
        }

        $replaced = str_replace($element, $replacement_element, $this->content);

        if (strcmp($element, $replacement_element) != 0) {
           $this->content_is_modified = true;
        }

        $this->content = $replaced;

        return $replacement_element;
    }

    public function remove_coyote_id(string $element, string $coyote_id) {
        $replacement_element = preg_replace(self::COYOTE_ID_REGEX, " ", $element);
        $content = str_replace($element, $replacement_element, $this->content);
        $this->content = $content;

        return $replacement_element;
    }

    public function find_resource_image($coyote_id) {
        $images = $this->get_images();
        $filtered = array_filter($images, function($item) use($coyote_id) {
            return $images['coyote_id'] === $coyote_id;
        });

        // there should only be one image found with this ID
        return count($filtered) === 1 ? array_shift($filtered) : null;
    }

    public function restore_resource($coyote_id, $alt) {
        if ($image = $this->find_resource_image($coyote_id)) {
            // first the alt, then the coyote ID so we can ensure no double replacements
            $replaced = $this->replace_img_alt($image['element'], $alt);
            $replaced = $this->remove_coyote_id($replaced, $coyote_id);
        }
    }

    public function set_coyote_id_and_alt(string $element, string $coyote_id, string $alt) {
        $element = $this->set_coyote_id($element, $coyote_id);
        $element = $this->replace_img_alt($element, $alt);
        return $element;
    }

    public function get_content() {
        return $this->content;
    }
}


