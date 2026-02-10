<?php
$env = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
};

$detectBaseUrl = static function () use ($env): string {
    $fromEnv = (string)$env('BASE_URL', '');
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'https://your-domain.com';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'your-domain.com');
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim(dirname($scriptName), '/');
    $path = ($scriptDir === '.' || $scriptDir === '/') ? '' : $scriptDir;

    return $scheme . '://' . $host . $path;
};

return [
    'DB_HOST' => (string)$env('DB_HOST', '127.0.0.1'),
    'DB_NAME' => (string)$env('DB_NAME', 'secureon'),
    'DB_USER' => (string)$env('DB_USER', 'root'),
    'DB_PASS' => (string)$env('DB_PASS', ''),
    'APP_KEY' => (string)$env('APP_KEY', 'change-this-32+chars-secret-key'),
    'BASE_URL' => $detectBaseUrl(),
    'STORAGE_PATH' => (string)$env('STORAGE_PATH', __DIR__ . '/../../storage'),
    'DEV_MODE' => filter_var((string)$env('DEV_MODE', 'true'), FILTER_VALIDATE_BOOLEAN),
    'DEFAULT_CURRENCY' => (string)$env('DEFAULT_CURRENCY', 'USD'),
    'BILLING_GRACE_DAYS' => (int)$env('BILLING_GRACE_DAYS', 0),
    'MAX_BILLING_MONTHS' => (int)$env('MAX_BILLING_MONTHS', 60),
];
