<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Billing;
use App\Models\Backup;
use App\Models\AuditLog;
use App\Models\System;
use App\Core\Helpers;
use App\Core\DB as CoreDB;

class BackupsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $backups = Backup::listByUser($user['id']);
        $this->view('app/backups_list', ['user' => $user, 'backups' => $backups]);
    }

    public function downloadSigned($token)
    {
        $pdo = CoreDB::conn();
        $stmt = $pdo->prepare('SELECT * FROM download_tokens WHERE token_hash = ?');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row || strtotime($row['expires_at']) < time()) {
            http_response_code(403);
            echo 'Download link expired.';
            return;
        }
        $backup = Backup::find($row['backup_id']);
        if (!$backup) {
            http_response_code(404);
            echo 'Backup not found.';
            return;
        }
        $billing = Billing::ensureActive($backup['user_id']);
        if (!$billing['ok']) {
            http_response_code(402);
            echo 'Billing required. Downloads are disabled until subscription is active.';
            return;
        }
        $path = $backup['storage_path'];
        if (!$path || !file_exists($path)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }
        $stmt = $pdo->prepare('UPDATE download_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$row['id']]);
        AuditLog::log('download_backup', $backup['user_id'], $backup['system_id'], ['backup_id' => $backup['id']]);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup-' . $backup['id'] . '.scx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function downloadSql($id)
    {
        $user = Auth::user();
        $backup = Backup::find($id);
        if (!$backup || $backup['user_id'] != $user['id']) {
            http_response_code(404);
            echo 'Backup not found.';
            return;
        }
        $system = System::find($backup['system_id']);
        if (!$system || $system['user_id'] != $user['id']) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        $billing = Billing::ensureActive($user['id']);
        if (!$billing['ok']) {
            http_response_code(402);
            echo 'Billing required. Downloads are disabled until subscription is active.';
            return;
        }
        if (empty($system['secret'])) {
            http_response_code(500);
            echo 'System secret missing. Cannot decrypt backup.';
            return;
        }
        $path = $backup['storage_path'];
        if (!$path || !file_exists($path)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }

        $fp = fopen($path, 'rb');
        $magic = fread($fp, 4);
        if ($magic !== 'SCX1') {
            fclose($fp);
            http_response_code(400);
            echo 'Invalid container.';
            return;
        }
        $lenData = fread($fp, 4);
        $len = unpack('N', $lenData)[1] ?? 0;
        $json = $len ? fread($fp, $len) : '';
        $header = json_decode($json, true) ?: [];
        $ciphertext = stream_get_contents($fp);
        fclose($fp);

        $salt = hex2bin($header['salt'] ?? '') ?: '';
        $iv = hex2bin($header['iv'] ?? '') ?: '';
        $tag = hex2bin($header['tag'] ?? '') ?: '';
        $appKey = Helpers::config('APP_KEY');
        $info = 'secureon:' . $system['id'];
        $key = hash_hkdf('sha256', $appKey . $system['secret'], 32, $info, $salt);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            http_response_code(500);
            echo 'Decryption failed. Ensure the agent master_key matches Secureon APP_KEY, then create a new backup.';
            return;
        }
        if (!empty($header['checksum_plain_sha256'])) {
            $calc = hash('sha256', $plain);
            if (!hash_equals($header['checksum_plain_sha256'], $calc)) {
                http_response_code(500);
                echo 'Checksum mismatch.';
                return;
            }
        }
        $sql = gzdecode($plain);
        if ($sql === false) {
            http_response_code(500);
            echo 'Decompression failed.';
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'secureon_sql_');
        file_put_contents($tmp, $sql);
        AuditLog::log('download_sql', $backup['user_id'], $backup['system_id'], ['backup_id' => $backup['id']]);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="backup-' . $backup['id'] . '.sql"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }
}

