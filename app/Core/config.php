<?php
$loadDotEnv = static function (string $filePath): void {
    static $loaded = [];

    if (isset($loaded[$filePath])) {
        return;
    }
    $loaded[$filePath] = true;

    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (strncmp($line, 'export ', 7) === 0) {
            $line = ltrim(substr($line, 7));
        }

        $delimiterPos = strpos($line, '=');
        if ($delimiterPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $delimiterPos));
        if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        $rawValue = trim(substr($line, $delimiterPos + 1));
        $value = '';

        if ($rawValue !== '') {
            $quote = $rawValue[0];
            if ($quote === '"' || $quote === "'") {
                $endPos = strrpos($rawValue, $quote);
                if ($endPos !== false && $endPos > 0) {
                    $value = substr($rawValue, 1, $endPos - 1);
                    if ($quote === '"') {
                        $value = stripcslashes($value);
                    }
                } else {
                    $value = substr($rawValue, 1);
                }
            } else {
                $buffer = '';
                $length = strlen($rawValue);
                for ($i = 0; $i < $length; $i++) {
                    if ($rawValue[$i] === '#' && $i > 0 && ctype_space($rawValue[$i - 1])) {
                        break;
                    }
                    $buffer .= $rawValue[$i];
                }
                $value = trim($buffer);
            }
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }
};

$projectRoot = dirname(__DIR__, 2);
$loadDotEnv($projectRoot . '/.env');

$envValue = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
};

$appTimezone = (string)$envValue('APP_TIMEZONE', 'Africa/Harare');
if (!in_array($appTimezone, timezone_identifiers_list(), true)) {
    $appTimezone = 'Africa/Harare';
}
date_default_timezone_set($appTimezone);

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

$resolveStoragePath = static function (string $path) use ($projectRoot): string {
    $path = trim($path);
    if ($path === '') {
        return $projectRoot . '/storage';
    }

    $isAbsolute = $path[0] === '/'
        || $path[0] === '\\'
        || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;

    if ($isAbsolute) {
        return $path;
    }

    return $projectRoot . '/' . ltrim(str_replace('\\', '/', $path), '/');
};

return [
    'DB_HOST' => (string)$env('DB_HOST', '127.0.0.1'),
    'DB_NAME' => (string)$env('DB_NAME', 'secureon'),
    'DB_USER' => (string)$env('DB_USER', 'root'),
    'DB_PASS' => (string)$env('DB_PASS', ''),
    'APP_KEY' => (string)$env('APP_KEY', 'change-this-32+chars-secret-key'),
    'APP_TIMEZONE' => $appTimezone,
    'DB_TIMEZONE_OFFSET' => (string)$env('DB_TIMEZONE_OFFSET', '+02:00'),
    'BASE_URL' => $detectBaseUrl(),
    'STORAGE_PATH' => $resolveStoragePath((string)$env('STORAGE_PATH', 'storage')),
    'DEV_MODE' => filter_var((string)$env('DEV_MODE', 'true'), FILTER_VALIDATE_BOOLEAN),
    'DEFAULT_CURRENCY' => (string)$env('DEFAULT_CURRENCY', 'USD'),
    'BILLING_GRACE_DAYS' => (int)$env('BILLING_GRACE_DAYS', 0),
    'MAX_BILLING_MONTHS' => (int)$env('MAX_BILLING_MONTHS', 60),
];
