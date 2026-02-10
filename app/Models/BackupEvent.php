<?php
namespace App\Models;

use App\Core\DB;

class BackupEvent
{
    public static function log($backupId, $type, $message, $bytes = null)
    {
        $stmt = DB::conn()->prepare('INSERT INTO backup_events (backup_id, event_type, message, bytes_uploaded, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$backupId, $type, $message, $bytes]);
    }

    public static function listByBackup($backupId)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM backup_events WHERE backup_id = ? ORDER BY created_at DESC');
        $stmt->execute([$backupId]);
        return $stmt->fetchAll();
    }
}

