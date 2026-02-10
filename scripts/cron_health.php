<?php
require __DIR__ . '/../app/Core/config.php';
require __DIR__ . '/../app/Core/Autoload.php';

use App\Core\DB;
use App\Models\AuditLog;
use App\Core\Helpers;
use App\Models\Subscription;

$pdo = DB::conn();
Subscription::expireDueAll();
$systems = $pdo->query('SELECT s.*, p.min_backup_interval_minutes FROM systems s JOIN subscriptions sub ON s.user_id = sub.user_id JOIN plans p ON sub.plan_id = p.id WHERE sub.status = "active" AND (sub.ends_at IS NULL OR sub.ends_at > NOW())')->fetchAll();

// Storage usage alerts (90%+)
$subs = $pdo->query('SELECT s.user_id, p.storage_quota_mb FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.status = "active" AND (s.ends_at IS NULL OR s.ends_at > NOW())')->fetchAll();
foreach ($subs as $sub) {
    $userId = (int)$sub['user_id'];
    $quotaBytes = (int)$sub['storage_quota_mb'] * 1024 * 1024;
    if ($quotaBytes <= 0) {
        continue;
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(size_bytes),0) as total FROM backups WHERE user_id = ? AND status = "COMPLETED"');
    $stmt->execute([$userId]);
    $used = (int)$stmt->fetch()['total'];
    if ($used >= ($quotaBytes * 0.9)) {
        $message = '[' . date('Y-m-d H:i:s') . '] Alert: Storage usage 90%+ for user ' . $userId . "\n";
        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents($logPath, $message, FILE_APPEND);
        AuditLog::log('storage_alert', $userId, null, ['used_bytes' => $used, 'quota_bytes' => $quotaBytes]);
    }
}

foreach ($systems as $system) {
    $interval = (int)$system['min_backup_interval_minutes'];
    $threshold = $interval * 2;
    $stmt = $pdo->prepare('SELECT MAX(created_at) as last_backup FROM backups WHERE system_id = ? AND status = "COMPLETED"');
    $stmt->execute([$system['id']]);
    $last = $stmt->fetch()['last_backup'] ?? null;

    $status = 'Healthy';
    if ($last) {
        $diffMinutes = (time() - strtotime($last)) / 60;
        if ($diffMinutes > $threshold) {
            $status = 'Failed';
        } elseif ($diffMinutes > $interval) {
            $status = 'Warning';
        }
    } else {
        $status = 'Warning';
    }

    $pdo->prepare('UPDATE systems SET status = ? WHERE id = ?')->execute([$status, $system['id']]);
    if ($status !== 'Healthy') {
        $message = '[' . date('Y-m-d H:i:s') . '] Alert: System ' . $system['name'] . ' status ' . $status . "\n";
        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents($logPath, $message, FILE_APPEND);
        AuditLog::log('system_alert', $system['user_id'], $system['id'], ['status' => $status]);
    }

    // Trigger health warning
    if (!empty($system['last_trigger_status']) && $system['last_trigger_status'] !== 'success') {
        $message = '[' . date('Y-m-d H:i:s') . '] Trigger warning: System ' . $system['name'] . ' trigger status ' . $system['last_trigger_status'] . "\n";
        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents($logPath, $message, FILE_APPEND);
        AuditLog::log('trigger_alert', $system['user_id'], $system['id'], ['status' => $system['last_trigger_status']]);
    }
}

echo "Health check complete\n";

