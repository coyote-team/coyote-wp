<?php

namespace Coyote;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class RequestLogger
{
    protected Logger $logger;
    private int $logLevel;

    public function __construct($name, int $logLevel = Logger::INFO)
    {
        $this->logLevel = $logLevel;
        $this->logger = new Logger("Coyote/{$name}");
        $this->logger->pushHandler(new ErrorLogHandler());
    }

    public function warn(string $message): void
    {
        $this->logger->warning($message);
    }

    public function error(string $message): void
    {
        $this->logger->error($message);
    }

    public function log(string $message): void
    {
        $this->logger->info($message);
    }

    public function debug(string $message): void
    {
        if ($this->logLevel <= Logger::DEBUG) {
            $this->logger->debug($message);
        }
    }
}
