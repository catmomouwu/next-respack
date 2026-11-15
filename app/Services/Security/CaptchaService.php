<?php

namespace App\Services\Security;

class CaptchaService
{
    public const SESSION_KEY = 'guest_captcha';

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 5;

    private const TTL_SECONDS = 600;

    public function issue(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        session([self::SESSION_KEY => [
            'code' => $code,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]]);

        return $code;
    }

    public function verify(?string $input): bool
    {
        $payload = session()->pull(self::SESSION_KEY);

        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        $expected = (string) ($payload['code'] ?? '');
        $actual = strtoupper(trim((string) $input));

        return $expected !== '' && hash_equals($expected, $actual);
    }
}
