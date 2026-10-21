<?php
require_once __DIR__ . '/config/runtime.php';

class JWT
{
    private static function signingKey(): string
    {
        $key = sip_env('JWT_SECRET', false);
        // Every environment supplies its own random secret; there is no fallback.
        if ($key === false || strlen($key) < 32 || trim($key) !== $key
            || $key === 'dev-only-change-JWT_SECRET-on-production'
            || count(array_unique(str_split($key))) < 8) {
            throw new RuntimeException('JWT signing is not configured.');
        }
        return $key;
    }

    public static function accountVersion(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, self::signingKey());
    }

    public static function matchesAccount(array $payload, string $passwordHash): bool
    {
        try {
            return is_string($payload['ver'] ?? null) && hash_equals(self::accountVersion($passwordHash), $payload['ver']);
        } catch (RuntimeException $e) { return false; }
    }

    public static function assertConfigured(): void
    {
        self::signingKey();
    }

    private static function encodePart(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decodePart(string $value)
    {
        // Preserve existing base64 tokens signed with a valid configured key.
        if ($value === '' || !preg_match('~^[A-Za-z0-9+/_-]+={0,2}$~D', $value)) {
            return false;
        }
        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    public static function encode($payload)
    {
        $key = self::signingKey();
        if (!is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp'])) {
            throw new InvalidArgumentException('JWT payload requires an integer expiration.');
        }
        $header = self::encodePart(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::encodePart(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = self::encodePart(hash_hmac('sha256', "$header.$body", $key, true));
        return "$header.$body.$signature";
    }

    public static function decode($token)
    {
        try {
            $key = self::signingKey();
        } catch (RuntimeException $e) {
            return false;
        }
        if (!is_string($token) || strlen($token) > 16384) {
            return false;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        [$h, $b, $s] = $parts;
        $headerBytes = self::decodePart($h);
        $bodyBytes = self::decodePart($b);
        $signature = self::decodePart($s);
        if ($headerBytes === false || $bodyBytes === false || $signature === false
            || !hash_equals(hash_hmac('sha256', "$h.$b", $key, true), $signature)) {
            return false;
        }
        $header = json_decode($headerBytes, true);
        $payload = json_decode($bodyBytes, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256'
            || !is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp'])
            || $payload['exp'] <= time()) {
            return false;
        }
        if (isset($payload['nbf']) && (!is_int($payload['nbf']) || $payload['nbf'] > time())) {
            return false;
        }
        return $payload;
    }
}
