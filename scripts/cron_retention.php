<?php
require __DIR__ . '/../app/Core/config.php';
require __DIR__ . '/../app/Core/Autoload.php';

use App\Core\DB;
use App\Models\AuditLog;
use App\Models\Subscription;

$pdo = DB::conn();
Subscription::expireDueAll();

$subs = $pdo->query('SELECT s.user_id, p.storage_quota_mb, p.retention_days FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.status = "active" AND (s.ends_at IS NULL OR s.ends_at > NOW())')->fetchAll();
foreach ($subs as $sub) {
    $userId = $sub['user_id'];
    $retentionDays = (int)$sub['retention_days'];
    $quotaBytes = (int)$sub['storage_quota_mb'] * 1024 * 1024;

    // Delete backups older than retention
    $stmt = $pdo->prepare('SELECT * FROM backups WHERE user_id = ? AND status = "COMPLETED" AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $stmt->execute([$userId, $retentionDays]);
    $old = $stmt->fetchAll();
    foreach ($old as $b) {
        if ($b['storage_path'] && file_exists($b['storage_path'])) {
            unlink($b['storage_path']);
        }
        $pdo->prepare('DELETE FROM backups WHERE id = ?')->execute([$b['id']]);
        AuditLog::log('retention_delete', $userId, $b['system_id'], ['backup_id' => $b['id']]);
    }

    // Enforce quota
    $stmt = $pdo->prepare('SELECT * FROM backups WHERE user_id = ? AND status = "COMPLETED" ORDER BY created_at ASC');
    $stmt->execute([$userId]);
    $backups = $stmt->fetchAll();
    $total = 0;
    foreach ($backups as $b) { $total += (int)$b['size_bytes']; }
    while ($total > $quotaBytes && !empty($backups)) {
        $b = array_shift($backups);
        $total -= (int)$b['size_bytes'];
        if ($b['storage_path'] && file_exists($b['storage_path'])) {
            unlink($b['storage_path']);
        }
        $pdo->prepare('DELETE FROM backups WHERE id = ?')->execute([$b['id']]);
        AuditLog::log('quota_delete', $userId, $b['system_id'], ['backup_id' => $b['id']]);
    }
}

echo "Retention cleanup complete\n";

