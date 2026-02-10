<?php
namespace App\Core;

use PDO;
use PDOException;

class DB
{
    private static $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $config = require __DIR__ . '/config.php';
        $dsn = 'mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'] . ';charset=utf8mb4';
        try {
            self::$pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $dbTimezoneOffset = (string)($config['DB_TIMEZONE_OFFSET'] ?? '+02:00');
            if (preg_match('/^[+-](?:0\d|1\d|2[0-3]):[0-5]\d$/', $dbTimezoneOffset) === 1) {
                $tzStmt = self::$pdo->prepare('SET time_zone = ?');
                $tzStmt->execute([$dbTimezoneOffset]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo 'Database connection error.';
            exit;
        }
        return self::$pdo;
    }
}

