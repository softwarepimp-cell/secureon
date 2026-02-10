<?php
namespace App\Controllers;

use App\Core\Billing;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Helpers;
use App\Core\RateLimiter;
use App\Models\AuditLog;
use App\Models\System;
use App\Models\Token;

class BadgeApiController extends Controller
{
    private function bearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.+)$/i', (string)$auth, $m)) {
            return null;
        }
        return trim($m[1]);
    }

    private function deny(int $httpStatus, string $message, ?int $systemId = null, string $reason = ''): void
    {
        AuditLog::log('BADGE_STATUS_VIEW_DENIED', null, $systemId, [
            'reason' => $reason ?: $message,
            'http_status' => $httpStatus,
        ]);
        $this->json(['ok' => false, 'error' => $message], $httpStatus);
    }

    public function status()
    {
        $systemId = (int)($_GET['system_id'] ?? 0);
        if ($systemId <= 0) {
            $this->deny(400, 'Missing system_id');
            return;
        }

        $token = $this->bearerToken();
        if (!$token) {
            $this->deny(401, 'Missing badge token', $systemId, 'missing_token');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::check('badge_ip_' . $ip, 180, 60)) {
            $this->deny(429, 'Rate limit exceeded', $systemId, 'ip_rate_limited');
            return;
        }

        $tokenHashKey = substr(hash('sha256', $token), 0, 24);
        if (!RateLimiter::check('badge_auth_' . $tokenHashKey, 120, 60)) {
            $this->deny(429, 'Rate limit exceeded', $systemId, 'auth_rate_limited');
            return;
        }

        $tokenRow = Token::verifyToken($systemId, $token, 'badge');
        if (!$tokenRow) {
            $this->deny(401, 'Invalid badge token', $systemId, 'invalid_token');
            return;
        }

        if (!RateLimiter::check('badge_token_' . $tokenRow['id'], 60, 60)) {
            $this->deny(429, 'Rate limit exceeded', $systemId, 'token_rate_limited');
            return;
        }

        $system = System::find($systemId);
        if (!$system) {
            $this->deny(404, 'System not found', $systemId, 'system_missing');
            return;
        }

        Token::touch($tokenRow['id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');

        $billing = Billing::ensureActive((int)$system['user_id']);
        $status = 'warning';
        $message = 'No backups yet';
        $lastBackupAt = null;
        $lastBackupStatus = null;

        $stmt = DB::conn()->prepare(
            'SELECT status, created_at, completed_at
             FROM backups
             WHERE system_id = ?
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$systemId]);
        $latestBackup = $stmt->fetch();

        if (!$billing['ok']) {
            $status = 'billing_required';
            $message = 'Subscription inactive';
        } elseif ($latestBackup) {
            $lastBackupAt = $latestBackup['completed_at'] ?: $latestBackup['created_at'];
            $rawBackupStatus = strtolower((string)$latestBackup['status']);
            if ($rawBackupStatus === 'failed') {
                $lastBackupStatus = 'failed';
                $status = 'failed';
                $message = 'Last backup failed';
            } elseif ($rawBackupStatus === 'completed' || !empty($latestBackup['completed_at'])) {
                $lastBackupStatus = 'completed';
                $interval = (int)($system['expected_interval_minutes'] ?? 0);
                if ($interval <= 0) {
                    $interval = (int)($billing['entitlements']['min_backup_interval_minutes'] ?? 60);
                }
                $ageMinutes = max(0, (time() - strtotime((string)$lastBackupAt)) / 60);
                if ($ageMinutes <= $interval) {
                    $status = 'healthy';
                    $message = 'Last backup succeeded';
                } elseif ($ageMinutes <= ($interval * 2)) {
                    $status = 'warning';
                    $message = 'Backup delayed';
                } else {
                    $status = 'failed';
                    $message = 'Backup overdue';
                }
            } else {
                $lastBackupStatus = null;
                $status = 'warning';
                $message = 'Backup in progress';
            }
        } else {
            $status = 'warning';
            $message = 'No backups yet';
        }

        AuditLog::log('BADGE_STATUS_VIEW', (int)$system['user_id'], $systemId, [
            'status' => $status,
            'token_prefix' => $tokenRow['token_prefix'] ?? '',
        ]);

        $this->json([
            'ok' => true,
            'system_id' => (int)$system['id'],
            'system_name' => (string)$system['name'],
            'status' => $status,
            'last_backup_at' => $lastBackupAt,
            'last_backup_status' => $lastBackupStatus,
            'message' => $message,
            'dashboard_url' => Helpers::baseUrl('/systems/' . $systemId),
        ]);
    }
}
