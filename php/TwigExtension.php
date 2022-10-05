<?php

namespace Coyote;

use Twig\TwigFunction;

class TwigExtension
{

    /*
     * Filters to add to a Twig environment
     */
    public static function getFilters($twig)
    {
        return $twig;
    }

    /*
     * Functions to add to a Twig environment
     */
    public static function getFunctions($twig)
    {
        $twig->addFunction(new TwigFunction('settings_fields', function ($slug): void {
            settings_fields($slug);
        }));
        $twig->addFunction(new TwigFunction('do_settings_sections', function ($slug): void {
            do_settings_sections($slug);
        }));
        $twig->addFunction(new TwigFunction('submit_button', function (): void {
            submit_button();
        }));
        $twig->addFunction(new TwigFunction('submit_button_text', function ($text): void {
            submit_button($text);
        }));
        $twig->addFunction(new TwigFunction('__', function ($text): string {
            return __($text, WordPressPlugin::I18N_NS);
        }));

        return $twig;
    }
}
