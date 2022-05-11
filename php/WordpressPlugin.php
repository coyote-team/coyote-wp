<?php

namespace Coyote;

class WordpressPlugin
{
    public static function registerApiSucces(): void
    {
        do_action('coyote_api_client_success');
    }

    public static function registerApiError(): void
    {
        do_action('coyote_api_client_error');
    }
}