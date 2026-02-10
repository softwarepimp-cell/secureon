<?php
namespace App\Models;

use App\Core\DB;

class AuditLog
{
    public static function log($action, $userId = null, $systemId = null, $meta = [])
    {
        $stmt = DB::conn()->prepare('INSERT INTO audit_logs (user_id, system_id, action, ip, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'cli';
        $stmt->execute([$userId, $systemId, $action, $ip, $ua, json_encode($meta)]);
    }
}

