<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

class Logger {

    public static function log($log) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        }
    }

}

