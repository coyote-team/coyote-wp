<?php

namespace Coyote;

class WordPressPlugin
{
    public static function registerApiSuccess(): void
    {
        do_action('coyote_api_client_success');
    }

    public static function registerApiError(): void
    {
        do_action('coyote_api_client_error');
    }
}