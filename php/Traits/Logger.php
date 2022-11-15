<?php

namespace Coyote\Traits;

use Coyote\WordPressPlugin;
use PAC_Vendor\Monolog\Logger as MonologLogger;
use PAC_Vendor\Monolog\Handler\StreamHandler;

if (!defined('WPINC')) {
    exit;
}

trait Logger
{
    public static function logInfo(string $message, $data = []): void
    {
        self::log($message, $data, MonologLogger::INFO, get_called_class());
    }

    public static function logWarning(string $message, $data = []): void
    {
        self::log($message, $data, MonologLogger::WARNING, get_called_class());
    }

    public static function logDebug(string $message, $data = []): void
    {
        self::log($message, $data, MonologLogger::DEBUG, get_called_class());
    }

    public static function logError(string $message, $data = []): void
    {
        self::log($message, $data, MonologLogger::ERROR, get_called_class());
    }

    private static function log(string $message, array $payload, int $level, string $class): void
    {
        self::logger()->log($level, $message, array_merge($payload, ['class' => $class]));
    }

    private static function logger(): MonologLogger
    {
        $logger = new MonologLogger(WordPressPlugin::PLUGIN_NAME);
        $handler = new StreamHandler(WordPressPlugin::LOG_PATH, \Monolog\Logger::DEBUG);
        $logger->pushHandler($handler);
        return $logger;
    }
}
