<?php

namespace Coyote;

use Coyote\WordPressPlugin;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension
{

    /*
     * Filters to add to a Twig environment
     */
    public static function getFilters($twig)
    {
        $twig->addFilter(new TwigFilter('translate', function ($text) {
            return __($text, WordPressPlugin::I18N_NS);
        }));

        return $twig;
    }

    /*
     * Functions to add to a Twig environment
     */
    public static function getFunctions($twig) {
        $twig->addFunction(new TwigFunction('settings_fields', function ($slug) {
            return settings_fields($slug);
        }));
        $twig->addFunction(new TwigFunction('do_settings_sections', function ($slug) {
            return do_settings_sections($slug);
        }));
        $twig->addFunction(new TwigFunction('submit_button', function () {
            return submit_button();
        }));
        $twig->addFunction(new TwigFunction('submit_button_text', function ($text) {
            return submit_button($text);
        }));

        return $twig;
    }
}
