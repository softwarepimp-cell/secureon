<?php
// Secureon Agent CLI + Web Trigger

if (!defined('SECUREON_AGENT_WEB') && php_sapi_name() !== 'cli') {
    echo "Run from CLI only.\n";
    exit(1);
}

$baseDir = __DIR__;
$config = secureon_load_config($baseDir);

if (!defined('SECUREON_AGENT_WEB')) {
    $command = $argv[1] ?? '';
    if ($command === 'backup') {
        secureon_run_backup($config, 'manual');
    } elseif ($command === 'restore') {
        $backupId = null;
        $targetDb = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--backup-id=')) $backupId = substr($arg, 12);
            if (str_starts_with($arg, '--target-db=')) $targetDb = substr($arg, 12);
        }
        if (!$backupId) {
            echo "Missing --backup-id\n";
            exit(1);
        }
        secureon_run_restore($config, $backupId, $targetDb);
    } else {
        echo "Usage: php secureon-agent.php backup\n";
        echo "   or: php secureon-agent.php restore --backup-id=123 --target-db=dbname\n";
    }
}

function secureon_load_config($baseDir) {
    $configPath = $baseDir . '/secureon-agent-config.php';
    if (!file_exists($configPath)) {
        echo "Missing secureon-agent-config.php. Copy from example.\n";
        exit(1);
    }
    return require $configPath;
}

function secureon_log($baseDir, $message) {
    $logDir = $baseDir . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/agent.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function secureon_temp_dir($baseDir) {
    $dir = $baseDir . '/cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function secureon_nonce_ok($baseDir, $nonce, $timestamp) {
    $cacheDir = $baseDir . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $file = $cacheDir . '/nonces.json';
    $data = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true) ?: [];
    }
    $now = time();
    $data = array_filter($data, function ($item) use ($now) {
        return ($item['ts'] ?? 0) > ($now - 120);
    });
    foreach ($data as $item) {
        if (($item['nonce'] ?? '') === $nonce) {
            return false;
        }
    }
    $data[] = ['nonce' => $nonce, 'ts' => $timestamp];
    $data = array_slice($data, -20);
    file_put_contents($file, json_encode(array_values($data)));
    return true;
}

function secureon_last_start_ok($baseDir, $intervalMinutes) {
    $cacheDir = $baseDir . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $file = $cacheDir . '/last_backup.json';
    if (!file_exists($file)) {
        return true;
    }
    $data = json_decode(file_get_contents($file), true) ?: [];
    $last = $data['ts'] ?? 0;
    if ($last && (time() - $last) < ($intervalMinutes * 60)) {
        return false;
    }
    return true;
}

function secureon_mark_start($baseDir) {
    $cacheDir = $baseDir . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $file = $cacheDir . '/last_backup.json';
    file_put_contents($file, json_encode(['ts' => time()]));
}

function secureon_web_trigger($baseDir) {
    $config = secureon_load_config($baseDir);
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $allowed = trim($config['allowed_ips'] ?? '');
    if ($allowed !== '') {
        $list = array_map('trim', explode(',', $allowed));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($ip, $list, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'IP not allowed']);
            return;
        }
    }

    $headers = getallheaders();
    $systemId = $headers['X-Secureon-System'] ?? $headers['x-secureon-system'] ?? null;
    $ts = (int)($headers['X-Secureon-Timestamp'] ?? $headers['x-secureon-timestamp'] ?? 0);
    $nonce = $headers['X-Secureon-Nonce'] ?? $headers['x-secureon-nonce'] ?? '';
    $signature = $headers['X-Secureon-Signature'] ?? $headers['x-secureon-signature'] ?? '';
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $mode = $body['mode'] ?? '';

    if ((int)$systemId !== (int)$config['system_id']) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid system']);
        return;
    }

    if (abs(time() - $ts) > 60) {
        http_response_code(401);
        echo json_encode(['error' => 'Timestamp invalid']);
        return;
    }

    if (!secureon_nonce_ok($baseDir, $nonce, $ts)) {
        http_response_code(401);
        echo json_encode(['error' => 'Nonce replay']);
        return;
    }

    $payload = $systemId . '.' . $ts . '.' . $nonce;
    $expected = hash_hmac('sha256', $payload, $config['system_secret']);
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Bad signature']);
        return;
    }

    $interval = (int)($config['interval_minutes'] ?? 60);
    $devMode = (bool)($config['dev_mode'] ?? false);
    if (!$devMode && $mode !== 'test') {
        if (!secureon_last_start_ok($baseDir, $interval)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limited']);
            return;
        }
    }

    secureon_mark_start($baseDir);
    echo json_encode(['accepted' => true, 'message' => 'Backup started']);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    try {
        secureon_run_backup($config, 'trigger');
    } catch (Exception $e) {
        secureon_log($baseDir, 'Trigger backup failed: ' . $e->getMessage());
    }
}

function deriveKey($config, $salt) {
    $systemSecret = $config['system_secret'] ?? '';
    $master = $config['master_key'] ?? '';
    if ($master === '' || $master === 'SAME_AS_APP_KEY') {
        throw new Exception('Agent master_key is not set. It must match Secureon APP_KEY.');
    }
    $info = 'secureon:' . ($config['system_id'] ?? '0');
    return hash_hkdf('sha256', $master . $systemSecret, 32, $info, $salt);
}

function secureon_run_backup($config, $mode) {
    $base = rtrim($config['secureon_base_url'], '/');
    $headers = ['Authorization: Bearer ' . $config['agent_token']];

    // Handshake
    $handshake = httpPostJson($base . '/api/v1/agent/handshake', ['system_id' => $config['system_id']], $headers);
    if (isset($handshake['error']) || empty($handshake)) {
        secureon_log(__DIR__, 'Handshake failed: ' . ($handshake['error'] ?? 'no response'));
        return;
    }

    // Start
    $start = httpPostJson($base . '/api/v1/agent/backup/start', [
        'system_id' => $config['system_id'],
        'db_name' => $config['db_name'],
        'estimated_size' => 0,
        'backup_label' => $mode
    ], $headers);
    if (empty($start['backup_id'])) {
        secureon_log(__DIR__, 'Backup start failed: ' . ($start['error'] ?? 'no response'));
        return;
    }
    $backupId = $start['backup_id'];

    try {
        // Dump with mysqldump
        $tmpDir = secureon_temp_dir(__DIR__);
        $dumpFile = $tmpDir . '/secureon_' . $backupId . '.sql';
        $mysqldump = $config['mysqldump_path'] ?? 'mysqldump';
        $auth = sprintf('-h%s -u%s',
            escapeshellarg($config['db_host']),
            escapeshellarg($config['db_user'])
        );
        if (!empty($config['db_pass'])) {
            $auth .= ' -p' . escapeshellarg($config['db_pass']);
        }
        $cmd = sprintf('%s %s %s > %s',
            escapeshellcmd($mysqldump),
            $auth,
            escapeshellarg($config['db_name']),
            escapeshellarg($dumpFile)
        );
        @shell_exec($cmd);
        if (!file_exists($dumpFile) || filesize($dumpFile) === 0) {
            // Fallback: PHP exporter
            $exportError = null;
            $exported = secureon_fallback_export($config, $dumpFile, $exportError);
            if (!$exported) {
                $msg = 'mysqldump failed';
                if (empty($exportError)) {
                    $exportError = 'Fallback exporter failed';
                }
                $msg .= '; fallback error: ' . $exportError;
                throw new Exception($msg);
            }
        }

        // Compress
        $gzFile = $dumpFile . '.gz';
        $in = fopen($dumpFile, 'rb');
        if (!$in) {
            throw new Exception('Failed to read dump file');
        }
        $out = gzopen($gzFile, 'wb9');
        if (!$out) {
            fclose($in);
            throw new Exception('Failed to open gzip output');
        }
        while (!feof($in)) { gzwrite($out, fread($in, 1024 * 512)); }
        fclose($in); gzclose($out);

        // Encrypt to .scx
        $scxFile = $tmpDir . '/secureon_' . $backupId . '.scx';
        $plain = file_get_contents($gzFile);
        if ($plain === false || $plain === '') {
            throw new Exception('Failed to read gzip output');
        }
        $checksumPlain = hash('sha256', $plain);
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = deriveKey($config, $salt);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new Exception('Encryption failed');
        }
        $header = [
            'version' => 1,
            'created_at' => date('c'),
            'algorithm' => 'AES-256-GCM',
            'original' => 'mysql',
            'db_name' => $config['db_name'],
            'checksum_plain_sha256' => $checksumPlain,
            'salt' => bin2hex($salt),
            'iv' => bin2hex($iv),
            'tag' => bin2hex($tag),
        ];
        $json = json_encode($header);
        $fp = fopen($scxFile, 'wb');
        if (!$fp) {
            throw new Exception('Failed to write SCX file');
        }
        fwrite($fp, 'SCX1');
        fwrite($fp, pack('N', strlen($json)));
        fwrite($fp, $json);
        fwrite($fp, $ciphertext);
        fclose($fp);

        // Upload
        $post = [
            'system_id' => $config['system_id'],
            'backup_id' => $backupId,
            'file' => new CURLFile($scxFile, 'application/octet-stream', 'backup.scx')
        ];
        $upload = httpPostMultipart($base . '/api/v1/agent/backup/upload', $post, $headers);
        if (!($upload['ok'] ?? false)) {
            $err = $upload['error'] ?? 'Upload failed';
            throw new Exception($err);
        }

        // Complete
        $finalSize = filesize($scxFile);
        $checksum = hash_file('sha256', $scxFile);
        $complete = httpPostJson($base . '/api/v1/agent/backup/complete', [
            'system_id' => $config['system_id'],
            'backup_id' => $backupId,
            'final_size' => $finalSize,
            'checksum_sha256' => $checksum
        ], $headers);
        if (!($complete['ok'] ?? false)) {
            $err = $complete['error'] ?? 'Complete failed';
            throw new Exception($err);
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage() ?: 'Unknown error';
        secureon_log(__DIR__, 'Backup failed: ' . $msg);
        httpPostJson($base . '/api/v1/agent/backup/fail', [
            'system_id' => $config['system_id'],
            'backup_id' => $backupId,
            'error_message' => $msg
        ], $headers);
    }
}

function secureon_fallback_export($config, $dumpFile, &$error = null) {
    try {
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            // Buffer results to avoid "unbuffered queries are active" errors.
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
        $fh = fopen($dumpFile, 'wb');
        if (!$fh) {
            $last = error_get_last();
            $error = 'Unable to write dump file';
            if (!empty($last['message'])) {
                $error .= ': ' . $last['message'];
            }
            return false;
        }

        set_time_limit(0);
        fwrite($fh, "-- Secureon fallback export\n");
        fwrite($fh, "SET NAMES utf8mb4;\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        fflush($fh);
        if (ftell($fh) === 0) {
            $error = 'Unable to write to dump file (0 bytes after header)';
            fclose($fh);
            return false;
        }

        $tablesStmt = $pdo->query('SHOW FULL TABLES');
        $tables = $tablesStmt->fetchAll(PDO::FETCH_NUM);
        $tablesStmt->closeCursor();
        foreach ($tables as $row) {
            $table = $row[0];
            $type = strtoupper($row[1] ?? 'BASE TABLE');
            if ($type !== 'BASE TABLE') {
                continue;
            }

            // Schema
            $stmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $create = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();
            $createSql = $create['Create Table'] ?? $create['Create View'] ?? null;
            if (!$createSql && count($create) >= 2) {
                $vals = array_values($create);
                $createSql = $vals[1] ?? null;
            }
            if ($createSql) {
                fwrite($fh, "DROP TABLE IF EXISTS `" . $table . "`;\n");
                fwrite($fh, $createSql . ";\n\n");
            }

            // Data
            $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            $columns = null;
            $rowCount = 0;
            $batchCount = 0;
            $batchSize = 200;
            $colsSql = '';

            foreach ($stmt as $dataRow) {
                if ($columns === null) {
                    $columns = array_keys($dataRow);
                    $colsSql = '`' . implode('`,`', array_map(function ($c) {
                        return str_replace('`', '``', $c);
                    }, $columns)) . '`';
                }

                $values = [];
                foreach ($columns as $col) {
                    $val = $dataRow[$col];
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($val) || is_float($val)) {
                        $values[] = (string)$val;
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }

                if ($batchCount === 0) {
                    fwrite($fh, "INSERT INTO `" . $table . "` (" . $colsSql . ") VALUES\n");
                } else {
                    fwrite($fh, ",\n");
                }
                fwrite($fh, "(" . implode(',', $values) . ")");

                $rowCount++;
                $batchCount++;
                if ($batchCount >= $batchSize) {
                    fwrite($fh, ";\n\n");
                    $batchCount = 0;
                }
            }

            if ($batchCount > 0) {
                fwrite($fh, ";\n\n");
            }
            $stmt->closeCursor();
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fflush($fh);
        fclose($fh);
        clearstatcache(true, $dumpFile);
        if (filesize($dumpFile) === 0) {
            $error = 'Fallback export produced empty file';
            return false;
        }
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if ($error === '' || $error === null) {
            $error = 'Fallback exporter error: ' . get_class($e);
        }
        return false;
    }
}

function secureon_run_restore($config, $backupId, $targetDb) {
    $base = rtrim($config['secureon_base_url'], '/');
    $headers = ['Authorization: Bearer ' . $config['agent_token']];

    $url = $base . '/api/v1/agent/backup/restore/' . $backupId . '?system_id=' . $config['system_id'];
    $tmpDir = secureon_temp_dir(__DIR__);
    $scxFile = $tmpDir . '/secureon_restore_' . $backupId . '.scx';
    $fp = fopen($scxFile, 'wb');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    if (!file_exists($scxFile) || filesize($scxFile) === 0) {
        echo "Download failed\n";
        return;
    }

    $fp = fopen($scxFile, 'rb');
    $magic = fread($fp, 4);
    if ($magic !== 'SCX1') { echo "Invalid file\n"; return; }
    $lenData = fread($fp, 4);
    $len = unpack('N', $lenData)[1];
    $json = fread($fp, $len);
    $header = json_decode($json, true);
    $ciphertext = stream_get_contents($fp);
    fclose($fp);

    $salt = hex2bin($header['salt']);
    $iv = hex2bin($header['iv']);
    $tag = hex2bin($header['tag']);
    $key = deriveKey($config, $salt);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    $gzFile = $tmpDir . '/secureon_restore_' . $backupId . '.sql.gz';
    file_put_contents($gzFile, $plain);
    $sqlFile = $tmpDir . '/secureon_restore_' . $backupId . '.sql';
    $in = gzopen($gzFile, 'rb');
    $out = fopen($sqlFile, 'wb');
    while (!gzeof($in)) { fwrite($out, gzread($in, 1024 * 512)); }
    gzclose($in); fclose($out);

    $db = $targetDb ?: $config['db_name'];
    $mysql = $config['mysql_path'] ?? 'mysql';
    $auth = sprintf('-h%s -u%s',
        escapeshellarg($config['db_host']),
        escapeshellarg($config['db_user'])
    );
    if (!empty($config['db_pass'])) {
        $auth .= ' -p' . escapeshellarg($config['db_pass']);
    }
    $cmd = sprintf('%s %s %s < %s',
        escapeshellcmd($mysql),
        $auth,
        escapeshellarg($db),
        escapeshellarg($sqlFile)
    );
    @shell_exec($cmd);
    echo "Restore attempted for backup $backupId\n";
}

function httpPostJson($url, $data, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true) ?: [];
}

function httpPostMultipart($url, $data, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true) ?: [];
}
