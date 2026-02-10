<?php
require __DIR__ . '/../app/Core/config.php';
require __DIR__ . '/../app/Core/Autoload.php';

use App\Core\DB;
use App\Core\Billing;
use App\Models\System;
use App\Models\AuditLog;

$pdo = DB::conn();

// Cleanup tmp bundles older than 10 minutes
$tmpDir = (require __DIR__ . '/../app/Core/config.php')['STORAGE_PATH'] . '/tmp';
if (is_dir($tmpDir)) {
    $files = glob($tmpDir . '/*.zip') ?: [];
    foreach ($files as $f) {
        if (filemtime($f) < time() - 600) {
            @unlink($f);
        }
    }
}

$sql = 'SELECT sys.*, u.status as user_status
        FROM systems sys
        JOIN users u ON u.id = sys.user_id
        WHERE sys.trigger_url IS NOT NULL';

$systems = $pdo->query($sql)->fetchAll();

foreach ($systems as $system) {
    if ($system['user_status'] !== 'active') {
        continue;
    }

    $billing = Billing::ensureActive($system['user_id']);
    if (!$billing['ok']) {
        AuditLog::log('DISPATCH_SKIPPED_BILLING', $system['user_id'], $system['id'], ['reason' => $billing['code']]);
        continue;
    }

    $interval = (int)($system['expected_interval_minutes'] ?? $billing['entitlements']['min_backup_interval_minutes'] ?? 60);

    // Avoid overlap: if a RUNNING backup exists in last 30 minutes, skip
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM backups WHERE system_id = ? AND status = "RUNNING" AND started_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
    $stmt->execute([$system['id']]);
    $running = (int)$stmt->fetch()['cnt'];
    if ($running > 0) {
        continue;
    }

    // Check last completed backup time
    $stmt = $pdo->prepare('SELECT MAX(completed_at) as last_completed FROM backups WHERE system_id = ? AND status = "COMPLETED"');
    $stmt->execute([$system['id']]);
    $lastCompleted = $stmt->fetch()['last_completed'] ?? null;

    $lastTime = $lastCompleted ?: $system['last_trigger_at'] ?: $system['created_at'];
    if ($lastTime) {
        $elapsed = (time() - strtotime($lastTime)) / 60;
        if ($elapsed < $interval) {
            continue;
        }
    }

    // Build trigger request
    $ts = time();
    $nonce = bin2hex(random_bytes(16));
    $payload = $system['id'] . '.' . $ts . '.' . $nonce;
    $signature = hash_hmac('sha256', $payload, $system['secret']);

    $headers = [
        'Content-Type: application/json',
        'X-Secureon-System: ' . $system['id'],
        'X-Secureon-Timestamp: ' . $ts,
        'X-Secureon-Nonce: ' . $nonce,
        'X-Secureon-Signature: ' . $signature,
    ];

    $body = json_encode([
        'system_id' => (int)$system['id'],
        'timestamp' => $ts,
        'nonce' => $nonce,
        'mode' => 'scheduled',
        'requested_by' => 'cron_dispatch',
    ]);

    $start = microtime(true);
    $ch = curl_init($system['trigger_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $latency = (int)((microtime(true) - $start) * 1000);

    $status = 'failed';
    $message = $err ?: 'No response';
    if ($httpCode >= 200 && $httpCode < 300) {
        $status = 'success';
        $message = 'Trigger accepted';
    } elseif ($httpCode === 401 || $httpCode === 403) {
        $status = 'unauthorized';
        $message = 'Unauthorized';
    } elseif ($httpCode === 429) {
        $status = 'rate_limited';
        $message = 'Rate limited';
    } elseif ($httpCode === 0) {
        $status = 'timeout';
        $message = 'Timeout';
    }

    System::updateTriggerResult($system['id'], $status, $httpCode, $latency, $message, $nonce);
    AuditLog::log('TRIGGER_DISPATCHED', $system['user_id'], $system['id'], ['mode' => 'scheduled']);
    AuditLog::log('TRIGGER_RESULT', $system['user_id'], $system['id'], ['status' => $status, 'http' => $httpCode, 'latency_ms' => $latency]);
}

echo "Dispatch complete\n";
