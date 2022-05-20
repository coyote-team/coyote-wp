<?php

namespace Coyote\Traits;

use Coyote\WordPressPlugin;
use Monolog\Handler\StreamHandler;

trait Logger
{
    public static function logInfo(string $message, $data = []): void
    {
        self::log($message, $data, \Monolog\Logger::INFO, get_called_class());
    }

    public static function logWarning(string $message, $data = []): void
    {
        self::log($message, $data, \Monolog\Logger::WARNING, get_called_class());
    }

    public static function logDebug(string $message, $data = []): void
    {
        self::log($message, $data, \Monolog\Logger::DEBUG, get_called_class());
    }

    public static function logError(string $message, $data = []): void
    {
        self::log($message, $data, \Monolog\Logger::ERROR, get_called_class());
    }

    private static function log(string $message, array $payload, int $level, string $class): void
    {
        self::logger()->log($level, $message, array_merge($payload, ['class' => $class]));
    }

    private static function logger(): \Monolog\Logger
    {
        $logger = new \Monolog\Logger(WordPressPlugin::PLUGIN_NAME);
        $handler = new StreamHandler(WordPressPlugin::LOG_PATH, \Monolog\Logger::DEBUG);
        $logger->pushHandler($handler);
        return $logger;
    }
}
