<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Helpers;
use App\Core\Billing;
use App\Models\System;
use App\Models\Token;
use App\Models\Backup;
use App\Models\BackupEvent;
use App\Models\AuditLog;
use App\Models\User;

class AgentApiController extends Controller
{
    private function authSystem(): array
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (!preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return ['ok' => false, 'status' => 401, 'code' => 'UNAUTHORIZED', 'message' => 'Unauthorized'];
        }
        $token = trim($m[1]);
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $systemId = $input['system_id'] ?? ($_GET['system_id'] ?? null);
        if (!$systemId) {
            return ['ok' => false, 'status' => 400, 'code' => 'BAD_REQUEST', 'message' => 'Missing system_id'];
        }
        $tokenRow = Token::verifyToken((int)$systemId, $token, 'agent');
        if (!$tokenRow) {
            return ['ok' => false, 'status' => 401, 'code' => 'UNAUTHORIZED', 'message' => 'Unauthorized'];
        }
        Token::touch($tokenRow['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $system = System::find($systemId);
        if (!$system) {
            return ['ok' => false, 'status' => 404, 'code' => 'NOT_FOUND', 'message' => 'System not found'];
        }
        System::touchAgentSeen($systemId, $_SERVER['REMOTE_ADDR'] ?? '');
        $user = User::find($system['user_id']);
        if (!$user || ($user['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'status' => 403, 'code' => 'USER_SUSPENDED', 'message' => 'Account suspended'];
        }
        $subscription = Billing::activeSubscription($user['id']);
        if (!$subscription) {
            return ['ok' => false, 'status' => 402, 'code' => 'BILLING_INACTIVE', 'message' => 'Billing inactive or expired'];
        }
        return ['ok' => true, 'data' => [$system, $tokenRow, $subscription]];
    }

    private function authOrFail()
    {
        $auth = $this->authSystem();
        if (!$auth['ok']) {
            $this->json(['error' => $auth['message'], 'code' => $auth['code']], $auth['status']);
            return false;
        }
        return $auth['data'];
    }

    public function handshake()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $this->json([
            'system_id' => $system['id'],
            'allowed_frequency_minutes' => $subscription['min_backup_interval_minutes'] ?? 60,
            'max_backup_size_mb' => $subscription['storage_quota_mb'] ?? 1024,
            'encryption' => [
                'algorithm' => 'AES-256-GCM',
                'container' => 'SCX1',
            ]
        ]);
    }

    public function start()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $quota = Billing::ensureStorageAvailable($system['user_id']);
        if (!$quota['ok']) {
            $this->json(['error' => $quota['message'], 'code' => 'BILLING_QUOTA_EXCEEDED'], 402);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $label = $input['backup_label'] ?? null;
        $dbName = $input['db_name'] ?? '';
        $estimated = (int)($input['estimated_size'] ?? 0);
        $backupId = Backup::create($system['id'], $system['user_id'], $label, $dbName, $estimated);
        BackupEvent::log($backupId, 'START', 'Backup started', 0);
        AuditLog::log('backup_started', $system['user_id'], $system['id'], ['backup_id' => $backupId]);
        $this->json(['backup_id' => $backupId, 'upload_mode' => 'multipart']);
    }

    public function progress()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $backupId = $input['backup_id'] ?? null;
        $bytes = (int)($input['bytes_uploaded'] ?? 0);
        $message = $input['message'] ?? '';
        if (!$backupId) {
            $this->json(['error' => 'Missing backup_id'], 400);
            return;
        }
        $backup = Backup::find($backupId);
        if (!$backup || $backup['system_id'] != $system['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        BackupEvent::log($backupId, 'PROGRESS', $message, $bytes);
        $this->json(['ok' => true]);
    }

    public function complete()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $backupId = $input['backup_id'] ?? null;
        if (!$backupId) {
            $this->json(['error' => 'Missing backup_id'], 400);
            return;
        }
        $backup = Backup::find($backupId);
        if (!$backup || $backup['system_id'] != $system['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        $size = (int)($input['final_size'] ?? 0);
        $checksum = $input['checksum_sha256'] ?? null;
        Backup::updateStatus($backupId, 'COMPLETED', [
            'completed_at' => date('Y-m-d H:i:s'),
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
        ]);
        BackupEvent::log($backupId, 'COMPLETE', 'Backup completed', $size);
        AuditLog::log('backup_completed', $backup['user_id'], $backup['system_id'], ['backup_id' => $backupId]);
        $this->json(['ok' => true]);
    }

    public function fail()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $backupId = $input['backup_id'] ?? null;
        $error = $input['error_message'] ?? 'Unknown error';
        if (!$backupId) {
            $this->json(['error' => 'Missing backup_id'], 400);
            return;
        }
        $backup = Backup::find($backupId);
        if (!$backup || $backup['system_id'] != $system['id']) {
            $this->json(['error' => 'Not found'], 404);
            return;
        }
        Backup::updateStatus($backupId, 'FAILED', []);
        BackupEvent::log($backupId, 'FAIL', $error, 0);
        AuditLog::log('backup_failed', $backup['user_id'], $backup['system_id'], ['backup_id' => $backupId, 'error' => $error]);
        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] Alert: Backup failed for system ' . $backup['system_id'] . ' (backup ' . $backupId . ")\n", FILE_APPEND);
        $this->json(['ok' => true]);
    }

    public function upload()
    {
        $auth = $this->authOrFail();
        if ($auth === false) {
            return;
        }
        [$system, $tokenRow, $subscription] = $auth;
        $backupId = $_POST['backup_id'] ?? null;
        if (!$backupId || empty($_FILES['file'])) {
            $this->json(['error' => 'Missing backup_id or file'], 400);
            return;
        }
        $backup = Backup::find($backupId);
        if (!$backup) {
            $this->json(['error' => 'Backup not found'], 404);
            return;
        }
        $storageRoot = Helpers::config('STORAGE_PATH') . '/backups/' . $system['user_id'] . '/' . $system['id'];
        if (!is_dir($storageRoot)) {
            mkdir($storageRoot, 0755, true);
        }
        $dest = $storageRoot . '/backup-' . $backupId . '.scx';
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $this->json(['error' => 'Upload failed'], 500);
            return;
        }
        Backup::updateStatus($backupId, 'UPLOADED', ['storage_path' => $dest]);
        BackupEvent::log($backupId, 'UPLOAD', 'File uploaded', filesize($dest));
        AuditLog::log('backup_uploaded', $system['user_id'], $system['id'], ['backup_id' => $backupId]);
        $this->json(['ok' => true]);
    }

    public function restoreDownload($backup_id)
    {
        $auth = $this->authSystem();
        if (!$auth['ok']) {
            http_response_code($auth['status']);
            echo $auth['message'];
            return;
        }
        [$system, $tokenRow, $subscription] = $auth['data'];
        $backup = Backup::find($backup_id);
        if (!$backup || $backup['system_id'] != $system['id']) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $path = $backup['storage_path'];
        if (!$path || !file_exists($path)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup-' . $backup_id . '.scx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

