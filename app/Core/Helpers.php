<?php
namespace App\Core;

class Helpers
{
    private static $logoPath = null;

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

