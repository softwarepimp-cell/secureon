<?php
namespace App\Models;

use App\Core\DB;

class Backup
{
    public static function create($systemId, $userId, $label, $dbName, $estimatedSize)
    {
        $stmt = DB::conn()->prepare('INSERT INTO backups (system_id, user_id, status, started_at, size_bytes, label, created_at) VALUES (?, ?, "RUNNING", NOW(), ?, ?, NOW())');
        $stmt->execute([$systemId, $userId, $estimatedSize, $label]);
        return DB::conn()->lastInsertId();
    }

    public static function updateStatus($id, $status, $fields = [])
    {
        $set = 'status = ?';
        $params = [$status];
        foreach ($fields as $k => $v) {
            $set .= ", $k = ?";
            $params[] = $v;
        }
        $params[] = $id;
        $stmt = DB::conn()->prepare("UPDATE backups SET $set WHERE id = ?");
        $stmt->execute($params);
    }

    public static function find($id)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function listByUser($userId)
    {
        $stmt = DB::conn()->prepare('SELECT b.*, s.name as system_name FROM backups b JOIN systems s ON b.system_id = s.id WHERE b.user_id = ? ORDER BY b.created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function listBySystem($systemId)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM backups WHERE system_id = ? ORDER BY created_at DESC');
        $stmt->execute([$systemId]);
        return $stmt->fetchAll();
    }

    public static function latestEvent($systemId)
    {
        $stmt = DB::conn()->prepare('SELECT b.id as backup_id, b.status, b.started_at, b.completed_at, e.event_type, e.message, e.bytes_uploaded, e.created_at as event_time FROM backups b LEFT JOIN backup_events e ON b.id = e.backup_id WHERE b.system_id = ? ORDER BY e.created_at DESC, b.created_at DESC LIMIT 1');
        $stmt->execute([$systemId]);
        return $stmt->fetch();
    }

    public static function storageUsedByUser($userId)
    {
        $stmt = DB::conn()->prepare('SELECT COALESCE(SUM(size_bytes),0) as total FROM backups WHERE user_id = ? AND status = "COMPLETED"');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['total'];
    }
}

