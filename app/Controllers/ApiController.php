<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Helpers;
use App\Core\Billing;
use App\Core\RateLimiter;
use App\Models\System;
use App\Models\Backup;
use App\Models\Token;
use App\Models\AuditLog;
use App\Core\CSRF;
use App\Core\DB;

class ApiController extends Controller
{
    private function sendTrigger($system, $mode, $requestedBy)
    {
        $billing = Billing::ensureActive($system['user_id']);
        if (!$billing['ok']) {
            return ['ok' => false, 'message' => $billing['message'], 'code' => $billing['code'], 'http_code' => 402];
        }

        if (empty($system['trigger_url'])) {
            return ['ok' => false, 'message' => 'Trigger URL not set'];
        }

        $devMode = (bool)Helpers::config('DEV_MODE', false);
        $interval = (int)($system['expected_interval_minutes'] ?? 60);
        if (!empty($system['last_trigger_at']) && $mode !== 'test' && !$devMode) {
            $elapsed = (time() - strtotime($system['last_trigger_at'])) / 60;
            if ($elapsed < $interval) {
                System::updateTriggerResult($system['id'], 'rate_limited', 429, 0, 'Rate limited', $system['last_trigger_nonce']);
                return ['ok' => false, 'message' => 'Rate limited', 'http_code' => 429];
            }
        }

        $ts = time();
        $nonce = bin2hex(random_bytes(16));
        $payload = $system['id'] . '.' . $ts . '.' . $nonce;
        $secret = $system['secret'];
        $signature = hash_hmac('sha256', $payload, $secret);

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
            'mode' => $mode,
            'requested_by' => $requestedBy,
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
        AuditLog::log('TRIGGER_RESULT', Auth::user()['id'], $system['id'], [
            'mode' => $mode,
            'status' => $status,
            'http' => $httpCode,
            'latency_ms' => $latency,
        ]);

        return ['ok' => $status === 'success', 'message' => $message, 'http_code' => $httpCode, 'latency_ms' => $latency];
    }
    public function metrics()
    {
        $user = Auth::user();
        $systems = System::allByUser($user['id']);
        $storage = Backup::storageUsedByUser($user['id']);
        $entitlements = Billing::getEntitlements($user['id']);
        $quota = (int)$entitlements['storage_quota_mb'] * 1024 * 1024;

        $stmt = DB::conn()->prepare('SELECT COUNT(*) as cnt FROM backups WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $stmt->execute([$user['id']]);
        $backups24h = (int)$stmt->fetch()['cnt'];

        $stmt = DB::conn()->prepare('SELECT COUNT(*) as cnt FROM backups WHERE user_id = ? AND status = "FAILED" AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $stmt->execute([$user['id']]);
        $failures24h = (int)$stmt->fetch()['cnt'];

        $labels = [];
        $storageByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('D', strtotime($day));
            $storageByDay[$day] = 0.0;
        }
        $stmt = DB::conn()->prepare(
            'SELECT DATE(created_at) as day, COALESCE(SUM(size_bytes),0) as bytes
             FROM backups
             WHERE user_id = ? AND status = "COMPLETED" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(created_at)'
        );
        $stmt->execute([$user['id']]);
        foreach ($stmt->fetchAll() as $row) {
            $day = $row['day'];
            if (isset($storageByDay[$day])) {
                $storageByDay[$day] = round(((float)$row['bytes']) / (1024 * 1024), 2);
            }
        }

        $stmt = DB::conn()->prepare(
            'SELECT
                SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) as completed_cnt,
                SUM(CASE WHEN status = "FAILED" THEN 1 ELSE 0 END) as failed_cnt
             FROM backups
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute([$user['id']]);
        $successRow = $stmt->fetch() ?: ['completed_cnt' => 0, 'failed_cnt' => 0];
        $completedCount = (int)($successRow['completed_cnt'] ?? 0);
        $failedCount = (int)($successRow['failed_cnt'] ?? 0);

        $this->json([
            'systems_count' => count($systems),
            'backups_24h' => $backups24h,
            'failures_24h' => $failures24h,
            'storage_used' => $storage,
            'storage_quota' => $quota,
            'billing_active' => (bool)$entitlements['active'],
            'billing_ends_at' => $entitlements['ends_at'],
            'allowed_systems' => (int)$entitlements['allowed_systems'],
            'storage_chart' => [
                'labels' => $labels,
                'data_mb' => array_values($storageByDay),
            ],
            'success_chart' => [
                'completed' => $completedCount,
                'failed' => $failedCount,
            ],
        ]);
    }

    public function latestStatus($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $latest = Backup::latestEvent($id);
        $this->json($latest ?: []);
    }

    public function triggerNow($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $billing = Billing::ensureActive($user['id']);
        if (!$billing['ok']) {
            $this->json(['error' => $billing['message'], 'code' => $billing['code']], 402);
            return;
        }
        AuditLog::log('TRIGGER_DISPATCHED', $user['id'], $system['id'], ['mode' => 'manual']);
        $result = $this->sendTrigger($system, 'manual', 'ui_trigger_now');
        $this->json($result);
    }

    public function testTrigger($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $billing = Billing::ensureActive($user['id']);
        if (!$billing['ok']) {
            $this->json(['error' => $billing['message'], 'code' => $billing['code']], 402);
            return;
        }
        AuditLog::log('TRIGGER_DISPATCHED', $user['id'], $system['id'], ['mode' => 'test']);
        $result = $this->sendTrigger($system, 'test', 'ui_test_trigger');
        $this->json($result);
    }

    public function prepareAgentBundle($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }

        $csrf = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['_csrf'] ?? '');
        if (!CSRF::validate($csrf)) {
            $this->json(['error' => 'Invalid CSRF'], 419);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $opts = [
            'db_host' => trim($input['db_host'] ?? '127.0.0.1'),
            'db_user' => trim($input['db_user'] ?? 'root'),
            'db_pass' => (string)($input['db_pass'] ?? ''),
            'db_name' => trim($input['db_name'] ?? 'your_db_name'),
            'mysqldump_path' => trim($input['mysqldump_path'] ?? 'mysqldump'),
            'mysql_path' => trim($input['mysql_path'] ?? 'mysql'),
        ];

        $key = bin2hex(random_bytes(16));
        if (!isset($_SESSION['agent_bundle'])) {
            $_SESSION['agent_bundle'] = [];
        }
        if (!isset($_SESSION['agent_bundle'][$id])) {
            $_SESSION['agent_bundle'][$id] = [];
        }
        $_SESSION['agent_bundle'][$id][$key] = [
            'created_at' => time(),
            'opts' => $opts,
        ];

        AuditLog::log('agent_bundle_prepared', $user['id'], $id, ['bundle_key' => $key]);
        $this->json([
            'ok' => true,
            'download_url' => Helpers::baseUrl('/systems/' . $id . '/download-agent?bundle_key=' . $key),
            'message' => 'Bundle prepared.',
        ]);
    }

    public function latestEvents()
    {
        $user = Auth::user();
        $systems = System::allByUser($user['id']);
        $events = [];
        foreach ($systems as $system) {
            $latest = Backup::latestEvent($system['id']);
            if ($latest) {
                $events[] = [
                    'system_id' => $system['id'],
                    'system_name' => $system['name'],
                    'status' => $latest['status'],
                    'message' => $latest['message'],
                    'event_time' => $latest['event_time'],
                ];
            }
        }
        $this->json(['events' => $events]);
    }

    public function createToken($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $billing = Billing::ensureActive($user['id']);
        if (!$billing['ok']) {
            $this->json(['error' => $billing['message'], 'code' => $billing['code']], 402);
            return;
        }
        $key = 'token_' . $user['id'];
        if (!RateLimiter::check($key, 5, 300)) {
            $this->json(['error' => 'Rate limit exceeded'], 429);
            return;
        }
        $token = Token::createAndReturnPlain($id, 'agent', 'manual');
        $prefix = substr($token, 0, 10);
        AuditLog::log('token_created', $user['id'], $id, ['prefix' => $prefix]);
        $this->json(['token' => $token, 'prefix' => $prefix]);
    }

    public function signDownload($id)
    {
        $user = Auth::user();
        $backup = Backup::find($id);
        if (!$backup || $backup['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $billing = Billing::ensureActive($user['id']);
        if (!$billing['ok']) {
            $this->json(['error' => $billing['message'], 'code' => $billing['code']], 402);
            return;
        }
        $token = bin2hex(random_bytes(24));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 600);
        $stmt = DB::conn()->prepare('INSERT INTO download_tokens (backup_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$backup['id'], $hash, $expires]);
        AuditLog::log('download_signed', $user['id'], $backup['system_id'], ['backup_id' => $backup['id']]);
        $this->json(['url' => Helpers::baseUrl('/download/' . $token), 'expires_at' => $expires]);
    }

    public function deleteBackup($id)
    {
        $user = Auth::user();
        $backup = Backup::find($id);
        if (!$backup || $backup['user_id'] != $user['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $path = $backup['storage_path'];
        if ($path && file_exists($path)) {
            unlink($path);
        }
        $stmt = DB::conn()->prepare('DELETE FROM backups WHERE id = ?');
        $stmt->execute([$id]);
        AuditLog::log('backup_deleted', $user['id'], $backup['system_id'], ['backup_id' => $backup['id']]);
        $this->json(['ok' => true]);
    }
}

