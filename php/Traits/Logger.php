<?php

namespace Coyote\Traits;

if (!defined('WPINC')) {
    exit;
}

trait Logger
{
    static int $LOG_DEBUG = 100;
    static int $LOG_INFO = 200;
    static int $LOG_WARNING = 300;
    static int $LOG_ERROR = 400;

    public static function logInfo(string $message, $data = []): void
    {
        self::log($message, $data, self::$LOG_INFO, get_called_class());
    }

    public static function logWarning(string $message, $data = []): void
    {
        self::log($message, $data, self::$LOG_WARNING, get_called_class());
    }

    public static function logDebug(string $message, $data = []): void
    {
        self::log($message, $data, self::$LOG_DEBUG, get_called_class());
    }

    public static function logError(string $message, $data = []): void
    {
        self::log($message, $data, self::$LOG_ERROR, get_called_class());
    }

    private static function log(string $message, array $payload, int $level, string $class): void
    {
        $levels = [
            self::$LOG_DEBUG => 'DEBUG',
            self::$LOG_INFO => 'INFO',
            self::$LOG_WARNING => 'WARNING',
            self::$LOG_ERROR => 'ERROR'
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $payload = print_r($payload, true);
            $message = sprintf("[%s] %s: %s > %s", $levels[$level], $class, $message, $payload);
            error_log($message);
        }
    }
}
