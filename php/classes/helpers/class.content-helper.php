<?php

namespace Coyote\Helpers;

class ContentHelper {
    private $content;
    private $images = null;

    const IMAGE_REGEX = '/(<\s*img\s+.*?\/?>)/smi';
    const SRC_REGEX = '/src\s*=\s*("|\')([\w\.]*)\1/smi';
    const ALT_REGEX = '/alt\s*=\s*("|\')(.*)(?!\\\\)\1/smi';

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

    function get_images_with_alt_and_src() {
        $images = $this->get_images();

        if ($images === null) {
            return $images;
        }

        $details = array();

        foreach ($images as $image) {
            array_push($verbose, [
                'element' => $image,
                'src' => self::get_img_src($image),
                'alt' => self::get_img_alt($image)
            ]);
        }

        return $details;
    }

    function get_img_src(string $element) {
        $matches = array();
        $result = preg_match(self::SRC_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }

    function get_img_alt(string $element) {
        $matches = array();
        $result = preg_match(self::ALT_REGEX, $element, $matches);

        if ($result) {
            return $matches[2];
        }

        return null;
    }
}


