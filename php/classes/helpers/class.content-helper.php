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
    const CLASS_REGEX = '/class\s*=\s*("|\')([^\1]+?)\1/smi';
    const IMG_ATTACHMENT_REGEX = '/wp-image-(\d+)/smi';

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
    function replace_image_alts($get_attachment_alt) {
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

            $alt = null;

            $class = self::get_img_class($element);

            if ($attachment_id = self::get_class_attachment_id($class)) {
                $alt = $get_attachment_alt($attachment_id);
            } else {
                $src = self::get_img_src($element);
                $hash = sha1(htmlspecialchars_decode($src));
                $alt = DB::get_coyote_alt_by_hash($hash);
            }

            if ($alt === null) {
                continue;
            }

            $this->replace_img_alt($element, $alt);
        }

        return $this->content;
    }

    static function get_class_attachment_id($class) {
        if (!is_string($class)) {
            return null;
        }

        $matches = [];

        if (preg_match(self::IMG_ATTACHMENT_REGEX, $class, $matches)) {
            return $matches[1];
        }
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

            $class = self::get_img_class($element);

            if ($attachment_id = self::get_class_attachment_id($class)) {
                $src = wp_get_attachment_url($attachment_id);
                $hash = sha1(htmlspecialchars_decode($src));
                $coyote_id = DB::get_coyote_id_by_hash($hash);

                if ($coyote_id !== null) {
                    $images[$attachment_id] = $coyote_id;
                }
            } else {
                $src = self::get_img_src($element);
                $hash = sha1(htmlspecialchars_decode($src));
                $coyote_id = DB::get_coyote_id_by_hash($hash);

                if ($coyote_id !== null) {
                    $images[$src] = $coyote_id;
                }
            }
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

            // skip images that are wordpress attachments
            // those get processed by media / attachment handlers
            $class = self::get_img_class($element);

            if ($attachment_id = self::get_class_attachment_id($class)) {
                continue;
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


    static function get_img_attr(string $regex, string $element) {
        $matches = array();
        $result = preg_match($regex, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    /**
     * @param string $element
     * @return mixed|null
     */
    static function get_img_class(string $element) {
        return self::get_img_attr(self::CLASS_REGEX, $element);
    }

    /**
     * @param string $element
     * @return mixed|null
     */
    static function get_img_src(string $element) {
        return self::get_img_attr(self::SRC_REGEX, $element);
    }

    /**
     * @param string $element
     * @return mixed|null
     */
    static function get_img_alt(string $element) {
        return self::get_img_attr(self::ALT_REGEX, $element);
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


