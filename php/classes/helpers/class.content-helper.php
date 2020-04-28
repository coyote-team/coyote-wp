<?php

namespace Coyote\Helpers;

class ContentHelper {
    private $content;
    private $images = null;

    public $content_is_modified = false;

    const IMAGE_REGEX = '/(<\s*img\s+.*?\/?>)/smi';
    const ALT_REGEX = '/alt\s*=\s*("|\')(.*)(?!\\\\)\1/smi';
    const SRC_REGEX = '/src\s*=\s*("|\')(.*)(?!\\\\)\1/smi';
    const COYOTE_ID_REGEX = '/\s+data-coyote-id\s*=\s*("|\')(.*?)(?!\\\\)\1\s*/smi';

    // not just the value, also the attribute
    const ALT_ATTR_REGEX = '/alt\s*=\s*("|\')(.*)(?!\\\\)\1/smi';
    const COYOTE_ID_ATTR_REGEX = '/\s+data-coyote-id\s*=\s*("|\')(.*?)(?!\\\\)\1/smi';

    // find a position to insert the coyote ID
    const IMG_ATTR_POSITION_REGEX = '/<\s*img\s+/smi';

    function __construct(string $content) {
        $this->content = $content;
    }

    function get_images() {
        if ($this->images !== null) {
            return $this->images;
        }

        $matches = array();
        preg_match_all(self::IMAGE_REGEX, $this->content, $matches);

        $this->images = $matches[0];

        return $this->images;
    }

    function get_images_with_attributes() {
        $images = $this->get_images();

        if ($images === null) {
            return $images;
        }

        $details = array();

        foreach ($images as $image) {
            array_push($details, [
                'element' => $image,
                'src' => self::get_img_src($image),
                'alt' => self::get_img_alt($image),
                'data-coyote-id' => self::get_coyote_id($image)
            ]);
        }

        return $details;
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

    public function set_coyote_id_and_alt(string $element, string $coyote_id, string $alt) {
        $element = $this->set_coyote_id($element, $coyote_id);
        $element = $this->replace_img_alt($element, $alt);
        return $element;
    }

    public function get_content() {
        return $this->content;
    }
}


