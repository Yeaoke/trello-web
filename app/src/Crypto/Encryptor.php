<?php

declare(strict_types=1);

namespace App\Crypto;

final class Encryptor {
    public const MARKER = 'enc:';
    public const CIPHER = 'aes-256-gcm';
    public const VERSION = 'v1';

    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private function __construct(private readonly string $key) {
    }

    public static function create(string $base64Key): self {
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('APP_KEY должен быть base64 строкой из 32 байт, для генерации используй: make keygen');
        }

        return new self($key);
    }

    public static function fromEnv(): self {
        $key = getenv('APP_KEY');
        if ($key === false || $key === '') {
            throw new \RuntimeException('Нету APP_KEY, сгенерируйте при помощи: make keygen');
        }

        return self::create($key);
    }

    public static function generateKey(): string {
        return base64_encode(random_bytes(32));
    }

    public function encrypt(string $plaintext): string {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new \RuntimeException('Не удалось зашифровать');
        }

        return self::MARKER . self::VERSION . ':' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string {
        if (str_starts_with($payload, self::MARKER)) {
            $payload = substr($payload, strlen(self::MARKER));
        }

        if (!str_starts_with($payload, self::VERSION . ':')) {
            throw new \RuntimeException('Неизвестная версия шифра');
        }

        $raw = base64_decode(substr($payload, strlen(self::VERSION) + 1), true);
        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Битый шифр');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Не удалось расшифровать: тег не тот');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool {
        return str_starts_with($value, self::MARKER);
    }

    public function decryptIfEncrypted(string $value): string {
        return $this->isEncrypted($value) ? $this->decrypt($value) : $value;
    }
}
