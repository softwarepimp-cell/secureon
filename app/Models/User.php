<?php
namespace App\Models;

use App\Core\DB;
use App\Core\Helpers;

class User
{
    private static $hasTimezoneColumn = null;

    public static function find($id)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findByEmail($email)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function create($name, $email, $passwordHash)
    {
        if (self::hasTimezoneColumn()) {
            $stmt = DB::conn()->prepare('INSERT INTO users (name, email, password_hash, role, status, timezone, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $email, $passwordHash, 'user', 'active', Helpers::appTimezone()]);
        } else {
            $stmt = DB::conn()->prepare('INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$name, $email, $passwordHash, 'user', 'active']);
        }
        return DB::conn()->lastInsertId();
    }

    public static function updateProfile($id, $name, $email)
    {
        $stmt = DB::conn()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        return $stmt->execute([$name, $email, $id]);
    }

    public static function updatePassword($id, $hash)
    {
        $stmt = DB::conn()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $id]);
    }

    public static function allWithStats()
    {
        $sql = 'SELECT u.*, p.id as plan_id, p.name as plan_name,
            s.allowed_systems, s.ends_at as subscription_ends_at, s.status as subscription_status,
            (SELECT COUNT(*) FROM systems s WHERE s.user_id = u.id) as systems_count,
            (SELECT COALESCE(SUM(size_bytes),0) FROM backups b WHERE b.user_id = u.id AND b.status = "COMPLETED") as storage_used,
            (SELECT MAX(created_at) FROM backups b WHERE b.user_id = u.id AND b.status = "COMPLETED") as last_backup
            FROM users u
            LEFT JOIN subscriptions s ON s.user_id = u.id AND s.status = "active" AND (s.ends_at IS NULL OR s.ends_at > NOW())
            LEFT JOIN plans p ON s.plan_id = p.id
            ORDER BY u.created_at DESC';
        $stmt = DB::conn()->query($sql);
        return $stmt->fetchAll();
    }

    public static function setStatus($id, $status, $reason = null)
    {
        $stmt = DB::conn()->prepare('UPDATE users SET status = ?, suspended_at = ?, suspension_reason = ? WHERE id = ?');
        $suspendedAt = $status === 'suspended' ? date('Y-m-d H:i:s') : null;
        $reason = $status === 'suspended' ? $reason : null;
        return $stmt->execute([$status, $suspendedAt, $reason, $id]);
    }

    public static function setRole($id, $role)
    {
        $stmt = DB::conn()->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }

    public static function updateTimezone($id, $timezone): bool
    {
        if (!self::hasTimezoneColumn()) {
            return false;
        }
        $stmt = DB::conn()->prepare('UPDATE users SET timezone = ? WHERE id = ?');
        return $stmt->execute([$timezone, $id]);
    }

    public static function hasTimezoneColumn(): bool
    {
        if (self::$hasTimezoneColumn !== null) {
            return self::$hasTimezoneColumn;
        }

        try {
            $stmt = DB::conn()->query("SHOW COLUMNS FROM users LIKE 'timezone'");
            self::$hasTimezoneColumn = (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            self::$hasTimezoneColumn = false;
        }

        return self::$hasTimezoneColumn;
    }
}

