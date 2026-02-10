<?php
namespace App\Core;

class Helpers
{
    private static $logoPath = null;
    private static $resolvedUserTimezone = null;

    public static function config($key, $default = null)
    {
        $config = require __DIR__ . '/config.php';
        return $config[$key] ?? $default;
    }

    public static function baseUrl($path = '')
    {
        $base = rtrim(self::config('BASE_URL'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function redirect($path)
    {
        header('Location: ' . self::baseUrl($path));
        exit;
    }

    public static function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function csrfField()
    {
        return '<input type="hidden" name="_csrf" value="' . CSRF::token() . '">';
    }

    public static function logoUrl()
    {
        if (self::$logoPath !== null) {
            return self::assetUrl(self::$logoPath);
        }

        $candidates = [
            'assests/logo.png',
            'assets/logo.png',
        ];
        foreach ($candidates as $candidate) {
            $full = __DIR__ . '/../../public/' . $candidate;
            if (file_exists($full)) {
                self::$logoPath = $candidate;
                return self::assetUrl($candidate);
            }
        }

        self::$logoPath = 'assests/logo.png';
        return self::assetUrl(self::$logoPath);
    }

    public static function assetUrl($path = '')
    {
        $base = rtrim(self::config('BASE_URL'), '/');
        $basePath = rtrim((string)(parse_url($base, PHP_URL_PATH) ?? ''), '/');
        $needsPublicPrefix = !str_ends_with($basePath, '/public');
        $prefix = $needsPublicPrefix ? '/public' : '';
        return $base . $prefix . '/' . ltrim($path, '/');
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }

    public static function appTimezone(): string
    {
        $timezone = (string)self::config('APP_TIMEZONE', 'Africa/Harare');
        if (!self::isValidTimezone($timezone)) {
            return 'Africa/Harare';
        }
        return $timezone;
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    public static function commonTimezones(): array
    {
        return [
            'Africa/Harare' => 'Africa/Harare (UTC+02:00)',
            'UTC' => 'UTC (UTC+00:00)',
            'Africa/Johannesburg' => 'Africa/Johannesburg (UTC+02:00)',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'Europe/Berlin' => 'Europe/Berlin',
            'America/New_York' => 'America/New_York',
            'America/Chicago' => 'America/Chicago',
            'America/Denver' => 'America/Denver',
            'America/Los_Angeles' => 'America/Los_Angeles',
            'America/Toronto' => 'America/Toronto',
            'America/Sao_Paulo' => 'America/Sao_Paulo',
            'Asia/Dubai' => 'Asia/Dubai',
            'Asia/Kolkata' => 'Asia/Kolkata',
            'Asia/Singapore' => 'Asia/Singapore',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Australia/Sydney' => 'Australia/Sydney',
            'Pacific/Auckland' => 'Pacific/Auckland',
        ];
    }

    public static function userTimezone(): string
    {
        if (self::$resolvedUserTimezone !== null) {
            return self::$resolvedUserTimezone;
        }

        $default = self::appTimezone();
        $sessionTimezone = $_SESSION['user_timezone'] ?? null;
        if (is_string($sessionTimezone) && self::isValidTimezone($sessionTimezone)) {
            self::$resolvedUserTimezone = $sessionTimezone;
            return self::$resolvedUserTimezone;
        }

        $cookieTimezone = $_COOKIE['secureon_tz'] ?? null;
        if (is_string($cookieTimezone) && self::isValidTimezone($cookieTimezone)) {
            $_SESSION['user_timezone'] = $cookieTimezone;
            self::$resolvedUserTimezone = $cookieTimezone;
            return self::$resolvedUserTimezone;
        }

        if (Auth::check()) {
            $user = Auth::user();
            $userTimezone = (string)($user['timezone'] ?? '');
            if ($userTimezone !== '' && self::isValidTimezone($userTimezone)) {
                $_SESSION['user_timezone'] = $userTimezone;
                self::$resolvedUserTimezone = $userTimezone;
                return self::$resolvedUserTimezone;
            }
        }

        self::$resolvedUserTimezone = $default;
        return self::$resolvedUserTimezone;
    }

    public static function setUserTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if (!self::isValidTimezone($timezone)) {
            $timezone = self::appTimezone();
        }

        $_SESSION['user_timezone'] = $timezone;
        self::$resolvedUserTimezone = $timezone;

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
            setcookie('secureon_tz', $timezone, [
                'expires' => time() + (365 * 24 * 60 * 60),
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $timezone;
    }

    public static function formatDateTime($value, string $format = 'Y-m-d H:i:s', string $fallback = 'N/A', ?string $sourceTimezone = null): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $fromTimezone = $sourceTimezone ?: self::appTimezone();
        if (!self::isValidTimezone($fromTimezone)) {
            $fromTimezone = self::appTimezone();
        }

        try {
            $sourceTz = new \DateTimeZone($fromTimezone);
            $targetTz = new \DateTimeZone(self::userTimezone());

            if ($value instanceof \DateTimeInterface) {
                $dt = \DateTimeImmutable::createFromInterface($value);
            } elseif (is_numeric($value)) {
                $dt = (new \DateTimeImmutable('@' . (int)$value))->setTimezone($sourceTz);
            } else {
                $raw = trim((string)$value);
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $sourceTz);
                if ($dt === false) {
                    $dt = new \DateTimeImmutable($raw, $sourceTz);
                }
            }

            return $dt->setTimezone($targetTz)->format($format);
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public static function formatDateTimeInput($value = null, ?string $sourceTimezone = null): string
    {
        if ($value === null || $value === '') {
            $value = self::now();
        }
        return self::formatDateTime($value, 'Y-m-d\TH:i', '', $sourceTimezone);
    }

    public static function formatBytes($bytes)
    {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

