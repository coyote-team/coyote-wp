<?php

namespace Coyote;

// Exit if accessed directly.
if (!defined( 'ABSPATH')) {
    exit;
}

class Logger {

    public static function log($log) {
        if (!WP_DEBUG) {
            return;
        }

        if (is_array($log) || is_object($log)) {
            error_log('[Coyote] ' . print_r($log, true));
        } else {
            error_log("[Coyote] {$log}");
        }
    }

}

