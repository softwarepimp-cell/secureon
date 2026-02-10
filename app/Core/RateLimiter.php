<?php
namespace App\Core;

class RateLimiter
{
    public static function check(string $key, int $limit, int $windowSeconds): bool
    {
        $storage = Helpers::config('STORAGE_PATH') . '/ratelimit';
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }
        $file = $storage . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        $now = time();
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        if (file_exists($file)) {
            $raw = file_get_contents($file);
            $data = json_decode($raw, true) ?: $data;
            if ($now > ($data['reset'] ?? 0)) {
                $data = ['count' => 0, 'reset' => $now + $windowSeconds];
            }
        }
        if ($data['count'] >= $limit) {
            return false;
        }
        $data['count']++;
        file_put_contents($file, json_encode($data));
        return true;
    }
}

