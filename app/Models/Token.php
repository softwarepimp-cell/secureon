<?php
namespace App\Models;

use App\Core\DB;

class Token
{
    private static ?array $columnCache = null;

    private static function hasColumn(string $column): bool
    {
        if (self::$columnCache === null) {
            $rows = DB::conn()->query('SHOW COLUMNS FROM system_tokens')->fetchAll();
            self::$columnCache = [];
            foreach ($rows as $row) {
                self::$columnCache[$row['Field']] = true;
            }
        }
        return isset(self::$columnCache[$column]);
    }

    public static function create($systemId, $tokenHash, $tokenPrefix, $tokenType = 'agent', $label = null)
    {
        $columns = ['system_id', 'token_hash', 'token_prefix'];
        $params = [$systemId, $tokenHash, $tokenPrefix];
        if (self::hasColumn('token_type')) {
            $columns[] = 'token_type';
            $params[] = $tokenType;
        }
        if (self::hasColumn('label')) {
            $columns[] = 'label';
            $params[] = $label;
        }
        $columns[] = 'created_at';
        $sql = 'INSERT INTO system_tokens (' . implode(', ', $columns) . ') VALUES (' .
            implode(', ', array_fill(0, count($params), '?')) . ', NOW())';

        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return DB::conn()->lastInsertId();
    }

    private static function generatePlainToken($tokenType): string
    {
        if ($tokenType === 'badge') {
            return 'SOB_' . bin2hex(random_bytes(20));
        }
        return bin2hex(random_bytes(24));
    }

    public static function createAndReturnPlain($systemId, $tokenType = 'agent', $label = null)
    {
        $token = self::generatePlainToken($tokenType);
        $hash = hash('sha256', $token);
        $prefix = substr($token, 0, 10);
        self::create($systemId, $hash, $prefix, $tokenType, $label);
        return $token;
    }

    public static function findValid($systemId, $tokenHash, $tokenType = 'agent')
    {
        $sql = 'SELECT * FROM system_tokens WHERE system_id = ? AND token_hash = ? AND revoked_at IS NULL';
        $params = [$systemId, $tokenHash];
        if (self::hasColumn('token_type')) {
            $sql .= ' AND token_type = ?';
            $params[] = $tokenType;
        }
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public static function verifyToken($systemId, $plainToken, $tokenType = 'agent')
    {
        if (!$plainToken) {
            return false;
        }
        $hash = hash('sha256', $plainToken);
        return self::findValid($systemId, $hash, $tokenType);
    }

    public static function allBySystem($systemId, $tokenType = null)
    {
        $sql = 'SELECT * FROM system_tokens WHERE system_id = ?';
        $params = [$systemId];
        if ($tokenType !== null && self::hasColumn('token_type')) {
            $sql .= ' AND token_type = ?';
            $params[] = $tokenType;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function latestByType($systemId, $tokenType = 'badge')
    {
        $sql = 'SELECT * FROM system_tokens WHERE system_id = ? AND revoked_at IS NULL';
        $params = [$systemId];
        if (self::hasColumn('token_type')) {
            $sql .= ' AND token_type = ?';
            $params[] = $tokenType;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 1';
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public static function revokeByType($systemId, $tokenType = 'badge')
    {
        $sql = 'UPDATE system_tokens SET revoked_at = NOW() WHERE system_id = ? AND revoked_at IS NULL';
        $params = [$systemId];
        if (self::hasColumn('token_type')) {
            $sql .= ' AND token_type = ?';
            $params[] = $tokenType;
        }
        $stmt = DB::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function touch($id, $ip, $userAgent = null)
    {
        $set = ['last_used_at = NOW()', 'last_used_ip = ?'];
        $params = [$ip];
        if (self::hasColumn('last_used_user_agent')) {
            $set[] = 'last_used_user_agent = ?';
            $params[] = (string)$userAgent;
        }
        $params[] = $id;
        $stmt = DB::conn()->prepare('UPDATE system_tokens SET ' . implode(', ', $set) . ' WHERE id = ?');
        $stmt->execute($params);
    }
}

