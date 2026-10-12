<?php

declare(strict_types=1);

namespace App\Config;

use App\Crypto\Encryptor;

final class Config {
    private static ?self $instance = null;

    private function __construct(
        private readonly string $env,
        private readonly array $sections,
        private readonly array $data,
    ) {
    }

    public static function load(?string $env = null): self {
        $env ??= (getenv('APP_ENV') ?: 'local');

        $encryptor = null;
        $sections = [];

        foreach (glob(__DIR__ . '/../../config/*.php') ?: [] as $file) {
            $section = basename($file, '.php');
            $data = require $file;
            if (!is_array($data)) {
                continue;
            }

            $sections[$section] = array_replace_recursive($data['default'] ?? [], $data[$env] ?? []);
        }

        foreach ($sections as $section => $values) {
            $sections[$section] = self::decryptValues($values, $section, $env, $encryptor);
        }

        $data = [];
        foreach ($sections as $section => $values) {
            foreach (self::flatten($values, $section) as $key => $value) {
                $data[$key] = $value;
            }
        }

        return self::$instance = new self($env, $sections, $data);
    }

    public static function instance(): self {
        return self::$instance ??= self::load();
    }

    public static function get(string $key, mixed $default = null): mixed {
        $config = self::instance();

        if (array_key_exists($key, $config->data)) {
            return $config->data[$key];
        }

        $value = $config->sections;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array {
        return $this->data;
    }

    public function env(): string {
        return $this->env;
    }

    private static function decryptValues(array $values, string $prefix, string $env, ?Encryptor &$encryptor): array {
        foreach ($values as $key => $value) {
            $dotKey = $prefix . '.' . $key;

            if (is_array($value)) {
                $values[$key] = self::decryptValues($value, $dotKey, $env, $encryptor);
                continue;
            }

            if (!is_string($value) || !str_starts_with($value, Encryptor::MARKER)) {
                continue;
            }

            try {
                $encryptor ??= Encryptor::fromEnv();
                $values[$key] = $encryptor->decrypt($value);
            } catch (\Throwable $e) {
                if ($env === 'prod') {
                    throw $e;
                }

                \App\Logger::get()->warning('config.decrypt_failed', [
                    'key'   => $dotKey,
                    'error' => $e->getMessage(),
                ]);
                $values[$key] = null;
            }
        }

        return $values;
    }

    private static function flatten(array $values, string $prefix): array {
        $flat = [];
        foreach ($values as $key => $value) {
            $dotKey = $prefix . '.' . $key;
            if (is_array($value) && $value !== []) {
                $flat += self::flatten($value, $dotKey);
                continue;
            }
            $flat[$dotKey] = $value;
        }

        return $flat;
    }
}
