<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Helpers;
use App\Core\CSRF;
use App\Core\Billing;
use App\Models\System;
use App\Models\Token;
use App\Models\Backup;
use App\Models\BackupEvent;
use App\Models\AuditLog;
use App\Core\DB;

class SystemsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $systems = System::allByUser($user['id']);
        $entitlements = Billing::getEntitlements($user['id']);
        foreach ($systems as &$s) {
            $interval = (int)($s['expected_interval_minutes'] ?? $entitlements['min_backup_interval_minutes'] ?? 60);
            $stmt = DB::conn()->prepare('SELECT MAX(created_at) as last_backup FROM backups WHERE system_id = ? AND status = "COMPLETED"');
            $stmt->execute([$s['id']]);
            $last = $stmt->fetch()['last_backup'] ?? null;
            $s['last_backup'] = $last;
            $s['next_expected'] = $last ? date('Y-m-d H:i:s', strtotime($last) + ($interval * 60)) : 'N/A';
        }
        $this->view('app/systems_list', [
            'user' => $user,
            'systems' => $systems,
            'entitlements' => $entitlements,
            'usage' => Billing::usage($user['id']),
        ]);
    }

    public function createForm()
    {
        $user = Auth::user();
        $guard = Billing::ensureCanCreateSystem($user['id']);
        if (!$guard['ok']) {
            Helpers::redirect('/billing?blocked=' . urlencode($guard['code']));
        }
        $minInterval = (int)($guard['entitlements']['min_backup_interval_minutes'] ?? 60);
        $this->view('app/systems_new', [
            'min_interval' => $minInterval,
            'allowed_systems' => (int)$guard['entitlements']['allowed_systems'],
            'systems_count' => (int)$guard['usage']['systems_count'],
        ]);
    }

    public function create()
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/systems/new');
        }
        $user = Auth::user();
        $name = trim($_POST['name'] ?? '');
        $environment = trim($_POST['environment'] ?? 'production');
        $timezone = trim($_POST['timezone'] ?? 'UTC');
        $interval = (int)($_POST['interval_minutes'] ?? 60);
        if (!$name) {
            $this->view('app/systems_new', ['error' => 'Name is required.']);
            return;
        }
        $guard = Billing::ensureCanCreateSystem($user['id']);
        if (!$guard['ok']) {
            $this->view('app/systems_new', ['error' => $guard['message']]);
            return;
        }
        $minInterval = (int)($guard['entitlements']['min_backup_interval_minutes'] ?? 60);
        $interval = max($interval, $minInterval);
        $systemId = System::create($user['id'], $name, $environment, $timezone, $interval);
        AuditLog::log('system_created', $user['id'], $systemId, ['interval' => $interval]);
        Helpers::redirect('/systems/' . $systemId . '?new=1');
    }

    public function show($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }
        $tokens = Token::allBySystem($id);
        $backups = Backup::listBySystem($id);
        $latest = Backup::latestEvent($id);
        $events = [];
        if (!empty($backups)) {
            $events = BackupEvent::listByBackup($backups[0]['id']);
        }
        $nextTrigger = null;
        if (!empty($system['expected_interval_minutes'])) {
            $last = $latest['event_time'] ?? $system['last_trigger_at'] ?? $system['created_at'];
            $nextTrigger = $last ? date('Y-m-d H:i:s', strtotime($last) + ((int)$system['expected_interval_minutes'] * 60)) : null;
        }
        $badgeTokenPreview = Token::latestByType($id, 'badge');
        $badgeTokenPlain = $_SESSION['badge_plain_token'][$id] ?? null;
        if (isset($_SESSION['badge_plain_token'][$id])) {
            unset($_SESSION['badge_plain_token'][$id]);
        }
        $this->view('app/systems_detail', [
            'user' => $user,
            'system' => $system,
            'tokens' => $tokens,
            'backups' => $backups,
            'latest' => $latest,
            'events' => $events,
            'next_trigger' => $nextTrigger,
            'entitlements' => Billing::getEntitlements($user['id']),
            'badge_token_preview' => $badgeTokenPreview,
            'badge_plain_token' => $badgeTokenPlain,
        ]);
    }

    public function updateTriggerConfig($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/systems/' . $id);
        }
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }
        $triggerUrl = trim($_POST['trigger_url'] ?? '');
        $allowlist = trim($_POST['agent_ip_allowlist'] ?? '');
        System::updateTriggerConfig($id, $triggerUrl ?: null, $allowlist ?: null);
        AuditLog::log('system_trigger_updated', $user['id'], $id, ['trigger_url' => $triggerUrl]);
        Helpers::redirect('/systems/' . $id);
    }

    public function downloadAgentBundle($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }

        // Create fresh tokens for this bundle package
        $plainToken = Token::createAndReturnPlain($id, 'agent', 'bundle');
        $plainBadgeToken = Token::createAndReturnPlain($id, 'badge', 'bundle');

        $base = Helpers::config('BASE_URL');
        $bundleDir = Helpers::config('STORAGE_PATH') . '/tmp';
        if (!is_dir($bundleDir)) {
            mkdir($bundleDir, 0755, true);
        }

        $bundleOpts = [
            'db_host' => '127.0.0.1',
            'db_user' => 'root',
            'db_pass' => '',
            'db_name' => 'your_db_name',
            'mysqldump_path' => 'mysqldump',
            'mysql_path' => 'mysql',
        ];
        $bundleKey = trim($_GET['bundle_key'] ?? '');
        if ($bundleKey !== '' && isset($_SESSION['agent_bundle'][$id][$bundleKey])) {
            $entry = $_SESSION['agent_bundle'][$id][$bundleKey];
            if (($entry['created_at'] ?? 0) >= (time() - 600)) {
                $bundleOpts = array_merge($bundleOpts, $entry['opts'] ?? []);
            }
            unset($_SESSION['agent_bundle'][$id][$bundleKey]);
        }

        $triggerFilename = $system['trigger_path'] . '.php';
        $stagingRoot = $bundleDir . '/build-' . $system['id'] . '-' . bin2hex(random_bytes(4));
        $agentRootPath = $stagingRoot . '/secureon-agent';
        if (!is_dir($agentRootPath)) {
            mkdir($agentRootPath, 0755, true);
        }

        $agentFile = file_get_contents(__DIR__ . '/../../agent/secureon-agent.php');
        file_put_contents($agentRootPath . '/secureon-agent.php', $agentFile);

        $devMode = Helpers::config('DEV_MODE', false) ? 'true' : 'false';
        $appKey = Helpers::config('APP_KEY');
        $config = "<?php\nreturn [\n    'system_id' => " . (int)$system['id'] . ",\n    'system_secret' => '" . $system['secret'] . "',\n    'agent_token' => '" . $plainToken . "',\n    'secureon_base_url' => '" . $base . "',\n    'db_host' => '" . addslashes((string)$bundleOpts['db_host']) . "',\n    'db_user' => '" . addslashes((string)$bundleOpts['db_user']) . "',\n    'db_pass' => '" . addslashes((string)$bundleOpts['db_pass']) . "',\n    'db_name' => '" . addslashes((string)$bundleOpts['db_name']) . "',\n    'mysqldump_path' => '" . addslashes((string)$bundleOpts['mysqldump_path']) . "',\n    'mysql_path' => '" . addslashes((string)$bundleOpts['mysql_path']) . "',\n    'master_key' => '" . addslashes((string)$appKey) . "',\n    'allowed_ips' => '" . ($system['agent_ip_allowlist'] ?? '') . "',\n    'interval_minutes' => " . (int)$system['expected_interval_minutes'] . ",\n    'dev_mode' => " . $devMode . ",\n];\n";
        file_put_contents($agentRootPath . '/secureon-agent-config.php', $config);

        $triggerPhp = $this->buildTriggerPhp();
        file_put_contents($agentRootPath . '/' . $triggerFilename, $triggerPhp);

        $badgeScript = $this->buildBadgeScript($system, $plainBadgeToken, [
            'position' => 'bottom-right',
            'theme' => 'auto',
            'link_to_dashboard' => true,
            'minimal_mode' => true,
            'show_powered_by' => true,
        ]);
        file_put_contents($agentRootPath . '/secureon-badge.php', $badgeScript);

        $htaccess = "Options -Indexes\n<Files *.php>\n    Require all denied\n</Files>\n<Files " . $triggerFilename . ">\n    Require all granted\n</Files>\n";
        file_put_contents($agentRootPath . '/.htaccess', $htaccess);

        $readme = "SECUREON AGENT INSTALL\n\n1) Upload and extract this folder on your target server.\n2) secureon-agent-config.php is prefilled; verify db_host, db_user, db_pass, db_name.\n3) Make sure this trigger file is reachable:\n   " . $triggerFilename . "\n4) Copy final trigger URL into Secureon dashboard Trigger URL field.\n   Example: https://clientdomain.com/secureon-agent/" . $triggerFilename . "\n5) Click Test Trigger in Secureon.\n6) Optional status badge:\n   - Include secureon-badge.php from your platform header.\n   - Example: <?php include __DIR__ . '/secureon-agent/secureon-badge.php'; ?>\n7) CLI restore is available:\n   php secureon-agent.php restore --backup-id=123 --target-db=yourdb\n\nSecurity note:\n- Keep this folder private when possible.\n- .htaccess blocks all PHP except trigger file.\n";
        file_put_contents($agentRootPath . '/README_INSTALL.txt', $readme);

        if (!is_dir($agentRootPath . '/cache')) {
            mkdir($agentRootPath . '/cache', 0755, true);
        }
        if (!is_dir($agentRootPath . '/logs')) {
            mkdir($agentRootPath . '/logs', 0755, true);
        }

        $zipPath = $bundleDir . '/secureon-agent-' . $system['id'] . '-' . time() . '.zip';
        $built = $this->buildZipFromDirectory($stagingRoot, $zipPath);
        if (!$built) {
            $this->recursiveDelete($stagingRoot);
            http_response_code(500);
            echo 'Bundle build failed. Enable PHP zip extension or use Windows PowerShell Compress-Archive.';
            return;
        }

        AuditLog::log('agent_bundle_downloaded', $user['id'], $id, ['bundle' => basename($zipPath)]);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="secureon-agent-' . $system['id'] . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        $this->recursiveDelete($stagingRoot);
        @unlink($zipPath);
        exit;
    }

    public function createBadgeToken($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/systems/' . $id);
        }
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || (int)$system['user_id'] !== (int)$user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }

        $plain = Token::createAndReturnPlain($id, 'badge', 'dashboard');
        if (!isset($_SESSION['badge_plain_token'])) {
            $_SESSION['badge_plain_token'] = [];
        }
        $_SESSION['badge_plain_token'][$id] = $plain;
        AuditLog::log('badge_token_created', $user['id'], $id, ['token_prefix' => substr($plain, 0, 10)]);
        Helpers::redirect('/systems/' . $id . '?badge_token=created');
    }

    public function revokeBadgeToken($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/systems/' . $id);
        }
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || (int)$system['user_id'] !== (int)$user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }

        $revoked = Token::revokeByType($id, 'badge');
        if (isset($_SESSION['badge_plain_token'][$id])) {
            unset($_SESSION['badge_plain_token'][$id]);
        }
        AuditLog::log('badge_token_revoked', $user['id'], $id, ['count' => $revoked]);
        Helpers::redirect('/systems/' . $id . '?badge_token=revoked');
    }

    public function downloadBadgeScript($id)
    {
        $user = Auth::user();
        $system = System::find($id);
        if (!$system || (int)$system['user_id'] !== (int)$user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }

        $position = strtolower(trim($_GET['position'] ?? 'bottom-right'));
        $theme = strtolower(trim($_GET['theme'] ?? 'auto'));
        $linkToDashboard = isset($_GET['link_to_dashboard']) ? ((int)$_GET['link_to_dashboard'] === 1) : true;
        $minimalMode = isset($_GET['minimal_mode']) ? ((int)$_GET['minimal_mode'] === 1) : true;
        $showPoweredBy = isset($_GET['show_powered_by']) ? ((int)$_GET['show_powered_by'] === 1) : true;
        $showTooltip = isset($_GET['show_tooltip']) ? ((int)$_GET['show_tooltip'] === 1) : true;

        $allowedPositions = ['top-right', 'bottom-right', 'bottom-left', 'top-left'];
        if (!in_array($position, $allowedPositions, true)) {
            $position = 'bottom-right';
        }
        $allowedThemes = ['auto', 'light', 'dark'];
        if (!in_array($theme, $allowedThemes, true)) {
            $theme = 'auto';
        }

        $plainToken = $_SESSION['badge_plain_token'][$id] ?? null;
        if (!$plainToken) {
            $plainToken = Token::createAndReturnPlain($id, 'badge', 'download');
        } else {
            unset($_SESSION['badge_plain_token'][$id]);
        }

        $script = $this->buildBadgeScript($system, $plainToken, [
            'position' => $position,
            'theme' => $theme,
            'link_to_dashboard' => $linkToDashboard,
            'minimal_mode' => $minimalMode,
            'show_powered_by' => $showPoweredBy,
            'show_tooltip' => $showTooltip,
        ]);

        AuditLog::log('badge_script_downloaded', $user['id'], $id, [
            'position' => $position,
            'theme' => $theme,
        ]);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="secureon-badge.php"');
        header('Content-Length: ' . strlen($script));
        echo $script;
        exit;
    }

    public function delete($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/systems/' . $id);
        }

        $user = Auth::user();
        $system = System::find($id);
        if (!$system || $system['user_id'] != $user['id']) {
            http_response_code(404);
            echo 'System not found';
            return;
        }

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            // Remove backup files first, then DB rows.
            $stmt = $pdo->prepare('SELECT id, storage_path FROM backups WHERE system_id = ?');
            $stmt->execute([$id]);
            $backups = $stmt->fetchAll();

            foreach ($backups as $b) {
                if (!empty($b['storage_path']) && file_exists($b['storage_path'])) {
                    @unlink($b['storage_path']);
                }
                $pdo->prepare('DELETE FROM backup_events WHERE backup_id = ?')->execute([$b['id']]);
                $pdo->prepare('DELETE FROM download_tokens WHERE backup_id = ?')->execute([$b['id']]);
            }

            $pdo->prepare('DELETE FROM backups WHERE system_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM system_tokens WHERE system_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM systems WHERE id = ?')->execute([$id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo 'Failed to delete system.';
            return;
        }

        AuditLog::log('system_deleted', $user['id'], $id, ['name' => $system['name']]);
        Helpers::redirect('/systems?deleted=1');
    }

    private function buildTriggerPhp()
    {
        return "<?php\n".
            "define('SECUREON_AGENT_WEB', true);\n".
            "ini_set('display_errors', '0');\n".
            "error_reporting(E_ALL);\n".
            "\$baseDir = __DIR__;\n".
            "require \$baseDir . '/secureon-agent.php';\n".
            "secureon_web_trigger(\$baseDir);\n";
    }

    private function buildBadgeScript($system, $badgeToken, array $opts = [])
    {
        $templatePath = __DIR__ . '/../../agent/secureon-badge.php.template';
        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new \RuntimeException('Badge template missing');
        }

        $replacements = [
            '__SECUREON_BASE_URL__' => addslashes((string)Helpers::config('BASE_URL')),
            '__SYSTEM_ID__' => (string)(int)$system['id'],
            '__BADGE_TOKEN__' => addslashes((string)$badgeToken),
            '__POSITION__' => addslashes((string)($opts['position'] ?? 'bottom-right')),
            '__THEME__' => addslashes((string)($opts['theme'] ?? 'auto')),
            '__LINK_TO_DASHBOARD__' => !empty($opts['link_to_dashboard']) ? 'true' : 'false',
            '__CACHE_SECONDS__' => '60',
            '__TIMEOUT_SECONDS__' => '3',
            '__MINIMAL_MODE__' => !empty($opts['minimal_mode']) ? 'true' : 'false',
            '__SHOW_POWERED_BY__' => !empty($opts['show_powered_by']) ? 'true' : 'false',
            '__SHOW_TOOLTIP__' => array_key_exists('show_tooltip', $opts) ? (!empty($opts['show_tooltip']) ? 'true' : 'false') : 'true',
        ];

        return strtr($template, $replacements);
    }

    private function buildZipFromDirectory($sourceDir, $zipPath)
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return false;
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = ltrim(str_replace('\\', '/', substr($filePath, strlen($sourceDir))), '/');
                if ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                } else {
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            return file_exists($zipPath);
        }

        // Windows fallback when php_zip is not enabled
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $src = str_replace("'", "''", $sourceDir);
            $dst = str_replace("'", "''", $zipPath);
            $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"Compress-Archive -Path '$src\\\\*' -DestinationPath '$dst' -Force\"";
            @exec($cmd, $out, $code);
            return $code === 0 && file_exists($zipPath);
        }

        return false;
    }

    private function recursiveDelete($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->recursiveDelete($itemPath);
            } else {
                @unlink($itemPath);
            }
        }
        @rmdir($path);
    }
}

