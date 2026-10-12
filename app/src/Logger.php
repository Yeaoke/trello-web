<?php

declare(strict_types=1);

namespace App;

final class Logger {

    private static ?\Monolog\Logger $logger = null;

    public static function get(): \Monolog\Logger {
        if (self::$logger instanceof \Monolog\Logger) {
            return self::$logger;
        }

        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logger = new \Monolog\Logger('webl');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Level::Debug));
        $logger->pushHandler(new \Monolog\Handler\RotatingFileHandler($logDir . '/app.log', 7, \Monolog\Level::Debug));

        self::$logger = $logger;

        return self::$logger;
    }
}
