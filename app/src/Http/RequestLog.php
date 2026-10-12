<?php

declare(strict_types=1);

namespace App\Http;

final class RequestLog {
    private static bool $registered = false;

    public static function register(\Monolog\Logger $logger): void {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $start = hrtime(true);

        ob_start();

        register_shutdown_function(static function () use ($logger, $start): void {
            $body = (string) ob_get_clean();

            $status = http_response_code();
            if (!is_int($status)) {
                $status = 200;
            }

            $durationMs = (hrtime(true) - $start) / 1_000_000;

            try {
                $logger->info('http.request', [
                    'method'      => $_SERVER['REQUEST_METHOD'] ?? '',
                    'uri'         => $_SERVER['REQUEST_URI'] ?? '',
                    'status'      => $status,
                    'duration_ms' => round($durationMs, 2),
                    'body'        => $body,
                ]);

                $err = error_get_last();
                if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    $logger->error('http.fatal', [
                        'error' => $err['message'],
                        'file'  => $err['file'],
                        'line'  => $err['line'],
                        'body'  => $body,
                    ]);
                }
            } catch (\Throwable $e) {
                error_log($e->getMessage());
            }

            echo $body;
        });
    }
}
