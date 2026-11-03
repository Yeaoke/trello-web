<?php

declare(strict_types=1);

namespace App;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\APCng;

/**
 * Единая точка доступа к метрикам приложения.
 *
 * Метрики включаются только вместе со стеком мониторинга (APP_METRICS_ENABLED=1
 * задаётся в compose.monitoring.yaml). В базовом окружении все методы — no-op,
 * поэтому код приложения (например, index.php) можно инструментировать, не думая,
 * запущен ли сейчас Prometheus.
 */
final class Metrics
{
    private static ?CollectorRegistry $registry = null;

    public static function enabled(): bool
    {
        return getenv('APP_METRICS_ENABLED') === '1'
            && extension_loaded('apcu')
            && apcu_enabled();
    }

    public static function registry(): CollectorRegistry
    {
        // APCng — хранилище в общей памяти APCu, общей для всех воркеров php-fpm
        // (зачем оно вообще нужно — см. комментарий в docker/php/Dockerfile).
        // Точность 6 знаков: суммы гистограмм хранятся как целые числа, и при
        // точности по умолчанию (3, т.е. миллисекунды) быстрые запросы округлялись бы в ноль.
        // Стандартную метрику php_info отключаем — она бы перезаписывалась на каждый запрос
        return self::$registry ??= new CollectorRegistry(new APCng(decimalPrecision: 6), false);
    }

    /**
     * Бизнес-метрика: сколько записей создали пользователи.
     * Технические метрики (RPS, ошибки, latency) говорят, жив ли сервис; бизнес-метрики —
     * делает ли он то, ради чего существует. «Ошибок нет, но записи перестали создаваться» —
     * поломка, которую видно только так.
     */
    public static function noteCreated(): void
    {
        self::safely(static function (): void {
            self::registry()
                ->getOrRegisterCounter('app', 'notes_created_total', 'Successfully persisted notes since the PHP-FPM restart; not current table size')
                ->inc();
        });
    }

    /** Оба исхода экспортируются с нуля, даже если попыток записи ещё не было. */
    public static function initializeNoteWrites(): void
    {
        self::safely(static function (): void {
            self::registry()->getOrRegisterCounter('app', 'notes_created_total', 'Successfully persisted notes since the PHP-FPM restart; not current table size')->incBy(0);
            $counter = self::registry()->getOrRegisterCounter(
                'app', 'note_write_operations_total',
                'Completed validated note persistence attempts; success or error; excludes HTTP 422', ['outcome']
            );
            foreach (['success', 'error'] as $outcome) {
                $counter->incBy(0, [$outcome]);
            }
        });
    }

    /** Один результат на попытку INSERT; без текста заметок и исключений в метках. */
    public static function noteWriteCompleted(string $outcome, float $seconds): void
    {
        self::safely(static function () use ($outcome, $seconds): void {
            self::initializeNoteWrites();
            self::registry()->getOrRegisterCounter(
                'app', 'note_write_operations_total',
                'Completed validated note persistence attempts; success or error; excludes HTTP 422', ['outcome']
            )->inc([$outcome]);
            self::registry()->getOrRegisterHistogram(
                'app', 'note_write_duration_seconds',
                'Validated note persistence duration including lazy DB connection, ORM and INSERT, in seconds', ['outcome'],
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2, 5]
            )->observe($seconds, [$outcome]);
        });
    }

    /**
     * Метрики не должны ломать приложение: если хранилище недоступно или переполнено,
     * запрос пользователя всё равно должен отработать — теряем только точку на графике.
     */
    public static function safely(callable $fn): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $fn();
        } catch (\Throwable $e) {
            error_log('metrics: ' . $e->getMessage());
        }
    }
}
