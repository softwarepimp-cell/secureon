<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Helpers;
use App\Core\Billing;
use App\Models\System;
use App\Models\Subscription;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Core\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $systems = System::allByUser($user['id']);
        $subscription = Subscription::currentForUser($user['id']);
        $latestSubscription = Subscription::latestForUser($user['id']);
        $entitlements = Billing::getEntitlements($user['id']);
        $lateSystems = [];
        $triggerFailures = [];
        foreach ($systems as $s) {
            $interval = (int)($s['expected_interval_minutes'] ?? $entitlements['min_backup_interval_minutes'] ?? 60);
            $stmt = \App\Core\DB::conn()->prepare('SELECT MAX(completed_at) as last_completed FROM backups WHERE system_id = ? AND status = "COMPLETED"');
            $stmt->execute([$s['id']]);
            $lastCompleted = $stmt->fetch()['last_completed'] ?? null;
            if ($lastCompleted) {
                $diffMinutes = (time() - strtotime($lastCompleted)) / 60;
                if ($diffMinutes > $interval) {
                    $lateSystems[] = ['id' => $s['id'], 'name' => $s['name'], 'last_completed' => $lastCompleted];
                }
            }
            if (!empty($s['last_trigger_status']) && $s['last_trigger_status'] !== 'success') {
                $triggerFailures[] = ['id' => $s['id'], 'name' => $s['name'], 'status' => $s['last_trigger_status']];
            }
        }
        $this->view('app/dashboard', [
            'user' => $user,
            'systems' => $systems,
            'subscription' => $subscription,
            'latest_subscription' => $latestSubscription,
            'entitlements' => $entitlements,
            'late_systems' => $lateSystems,
            'trigger_failures' => $triggerFailures,
        ]);
    }

    public function settings()
    {
        $user = Auth::user();
        $this->view('app/settings', [
            'user' => $user,
            'common_timezones' => Helpers::commonTimezones(),
            'active_timezone' => Helpers::userTimezone(),
            'server_timezone' => Helpers::appTimezone(),
            'timezone_persistence_db' => User::hasTimezoneColumn(),
        ]);
    }

    public function alerts()
    {
        $user = Auth::user();
        $logPath = \App\Core\Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        $lines = [];
        if (file_exists($logPath)) {
            $lines = array_slice(array_reverse(file($logPath, FILE_IGNORE_NEW_LINES)), 0, 50);
        }
        $this->view('app/alerts', ['user' => $user, 'alerts' => $lines]);
    }

    public function updateProfile()
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/settings');
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name && $email) {
            User::updateProfile($user['id'], $name, $email);
            AuditLog::log('profile_update', $user['id'], null, []);
        }
        Helpers::redirect('/settings');
    }

    public function updatePassword()
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/settings');
        }
        $password = $_POST['password'] ?? '';
        if (strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            User::updatePassword($user['id'], $hash);
            AuditLog::log('password_update', $user['id'], null, []);
        }
        Helpers::redirect('/settings');
    }

    public function updateTimezone()
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/settings');
        }

        $selected = trim((string)($_POST['timezone'] ?? Helpers::appTimezone()));
        if (!Helpers::isValidTimezone($selected)) {
            Helpers::redirect('/settings?tz=invalid');
        }

        $selected = Helpers::setUserTimezone($selected);
        User::updateTimezone($user['id'], $selected);
        AuditLog::log('timezone_update', $user['id'], null, ['timezone' => $selected]);
        Helpers::redirect('/settings?tz=updated');
    }

    public function admin()
    {
        $user = Auth::user();
        $stats = [
            'total_users' => 0,
            'active_users' => 0,
            'suspended_users' => 0,
            'total_backups' => 0,
            'total_storage' => 0,
            'failures_24h' => 0,
        ];

        $row = DB::conn()->query('SELECT COUNT(*) as total, SUM(status = "active") as active, SUM(status = "suspended") as suspended FROM users')->fetch();
        if ($row) {
            $stats['total_users'] = (int)$row['total'];
            $stats['active_users'] = (int)$row['active'];
            $stats['suspended_users'] = (int)$row['suspended'];
        }
        $row = DB::conn()->query('SELECT COUNT(*) as total_backups, COALESCE(SUM(size_bytes),0) as total_storage FROM backups WHERE status = "COMPLETED"')->fetch();
        if ($row) {
            $stats['total_backups'] = (int)$row['total_backups'];
            $stats['total_storage'] = (int)$row['total_storage'];
        }
        $row = DB::conn()->query('SELECT COUNT(*) as failures FROM backups WHERE status = "FAILED" AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetch();
        if ($row) {
            $stats['failures_24h'] = (int)$row['failures'];
        }

        $users = User::allWithStats();
        $plans = Plan::all();

        $this->view('app/admin', [
            'user' => $user,
            'stats' => $stats,
            'users' => $users,
            'plans' => $plans,
        ]);
    }

    public function suspendUser($id)
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin');
        }
        $reason = trim($_POST['reason'] ?? 'Policy violation');
        if ((int)$user['id'] === (int)$id) {
            Helpers::redirect('/admin');
        }
        User::setStatus($id, 'suspended', $reason);
        AuditLog::log('user_suspended', $user['id'], null, ['target_user_id' => $id, 'reason' => $reason]);
        Helpers::redirect('/admin');
    }

    public function unsuspendUser($id)
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin');
        }
        User::setStatus($id, 'active', null);
        AuditLog::log('user_unsuspended', $user['id'], null, ['target_user_id' => $id]);
        Helpers::redirect('/admin');
    }

    public function updateUserRole($id)
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin');
        }
        $role = $_POST['role'] ?? 'user';
        if ((int)$user['id'] === (int)$id && $role !== 'super_admin') {
            Helpers::redirect('/admin');
        }
        User::setRole($id, $role);
        AuditLog::log('user_role_changed', $user['id'], null, ['target_user_id' => $id, 'role' => $role]);
        Helpers::redirect('/admin');
    }

    public function updateUserPlan($id)
    {
        $user = Auth::user();
        if (!\App\Core\CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin');
        }
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId) {
            Subscription::createOrUpdate($id, $planId);
            AuditLog::log('user_plan_changed', $user['id'], null, ['target_user_id' => $id, 'plan_id' => $planId]);
        }
        Helpers::redirect('/admin');
    }
}

