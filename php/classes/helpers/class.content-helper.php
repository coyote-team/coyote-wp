<?php

namespace Coyote\Helpers;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

use Coyote\DB;

class ContentHelper {
    private $content;
    private $images = null;

    const IMAGE_REGEX = '#(?:(?:\[caption[^\]]*?\].*?)(<\s*img\s+[^>]*?\/?>)(?:\s*<\/a>)?\s*(?:(.*?)\[\/caption\])|<\s*img\s+[^>]*?\/?>)#smi';
    const ALT_REGEX = '/alt\s*=\s*("|\')(.*?)(?!\\\\)\1/smi';
    const SRC_REGEX = '/src\s*=\s*("|\')([^\1]+?)\1/smi';

    // not just the value, also the attribute
    const ALT_ATTR_REGEX = '/alt\s*=\s*("|\')(.*?)(?!\\\\)\1/smi';

    // find a position to insert the coyote ID
    const IMG_ATTR_POSITION_REGEX = '/<\s*img\s+/smi';

    function __construct(string $content) {
        $this->content = $content;
    }

    /**
     * @return string
     */
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

    /**
     * @return array
     */
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

    /**
     * @return array|null
     */
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
                'alt' => self::get_img_alt($element)
            ]);
        }

        return $this->images = $images;
    }

    /**
     * @param string $element
     * @return mixed|null
     */
    static function get_img_src(string $element) {
        $matches = array();
        $result = preg_match(self::SRC_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    /**
     * @param string $element
     * @return mixed|null
     */
    static function get_img_alt(string $element) {
        $matches = array();
        $result = preg_match(self::ALT_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    /**
     * @param string $element
     * @param string $alt
     * @return string|string[]|null
     */
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

        $this->content = $replaced;

        return $replacement_element;
    }
}


