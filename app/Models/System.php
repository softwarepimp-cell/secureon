<?php
namespace App\Models;

use App\Core\DB;

class System
{
    public static function allByUser($userId)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM systems WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM systems WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($userId, $name, $environment, $timezone, $expectedIntervalMinutes)
    {
        $secret = bin2hex(random_bytes(16));
        $triggerPath = 'trigger-' . bin2hex(random_bytes(6));
        $stmt = DB::conn()->prepare('INSERT INTO systems (user_id, name, environment, status, timezone, secret, trigger_path, expected_interval_minutes, created_at) VALUES (?, ?, ?, "Healthy", ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $name, $environment, $timezone, $secret, $triggerPath, $expectedIntervalMinutes]);
        return DB::conn()->lastInsertId();
    }

    public static function updateStatus($id, $status)
    {
        $stmt = DB::conn()->prepare('UPDATE systems SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function updateTriggerConfig($id, $triggerUrl, $allowlist)
    {
        $stmt = DB::conn()->prepare('UPDATE systems SET trigger_url = ?, agent_ip_allowlist = ? WHERE id = ?');
        $stmt->execute([$triggerUrl, $allowlist, $id]);
    }

    public static function updateTriggerResult($id, $status, $httpCode, $latencyMs, $message, $nonce)
    {
        $stmt = DB::conn()->prepare('UPDATE systems SET last_trigger_at = NOW(), last_trigger_status = ?, last_trigger_http_code = ?, last_trigger_latency_ms = ?, last_trigger_message = ?, last_trigger_nonce = ? WHERE id = ?');
        $stmt->execute([$status, $httpCode, $latencyMs, $message, $nonce, $id]);
    }

    public static function touchAgentSeen($id, $ip)
    {
        $stmt = DB::conn()->prepare('UPDATE systems SET agent_last_seen_at = NOW(), agent_last_ip = ? WHERE id = ?');
        $stmt->execute([$ip, $id]);
    }
}

