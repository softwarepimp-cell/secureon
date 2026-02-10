<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\CSRF;
use App\Core\DB;
use App\Core\Helpers;
use App\Core\Billing;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class AdminBillingController extends Controller
{
    public function packages()
    {
        $user = Auth::user();
        $plans = Plan::all();
        $this->view('app/admin_packages', [
            'user' => $user,
            'plans' => $plans,
        ]);
    }

    public function packageNew()
    {
        $user = Auth::user();
        $this->view('app/admin_package_form', [
            'user' => $user,
            'plan' => null,
            'action' => Helpers::baseUrl('/admin/packages/create'),
            'title' => 'Create Package',
        ]);
    }

    public function packageCreate()
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/packages');
        }
        $admin = Auth::user();
        $data = $this->validatePlanPayload($_POST);
        if (!$data['ok']) {
            Helpers::redirect('/admin/packages/new?err=' . urlencode($data['error']));
        }

        $planId = Plan::create($data['data']);
        AuditLog::log('ADMIN_PACKAGE_CREATED', $admin['id'], null, ['plan_id' => $planId]);
        Helpers::redirect('/admin/packages?created=' . $planId);
    }

    public function packageEdit($id)
    {
        $user = Auth::user();
        $plan = Plan::find($id);
        if (!$plan) {
            http_response_code(404);
            echo 'Plan not found';
            return;
        }
        $this->view('app/admin_package_form', [
            'user' => $user,
            'plan' => $plan,
            'action' => Helpers::baseUrl('/admin/packages/' . $id . '/update'),
            'title' => 'Edit Package',
        ]);
    }

    public function packageUpdate($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/packages');
        }
        $admin = Auth::user();
        $plan = Plan::find($id);
        if (!$plan) {
            Helpers::redirect('/admin/packages');
        }

        $data = $this->validatePlanPayload($_POST);
        if (!$data['ok']) {
            Helpers::redirect('/admin/packages/' . $id . '/edit?err=' . urlencode($data['error']));
        }

        Plan::update($id, $data['data']);
        AuditLog::log('ADMIN_PACKAGE_UPDATED', $admin['id'], null, ['plan_id' => (int)$id]);
        Helpers::redirect('/admin/packages?updated=' . $id);
    }

    public function packageToggle($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/packages');
        }
        $admin = Auth::user();
        $plan = Plan::find($id);
        if (!$plan) {
            Helpers::redirect('/admin/packages');
        }
        $target = (int)$plan['is_active'] === 1 ? 0 : 1;
        Plan::toggle($id, $target);
        AuditLog::log('ADMIN_PACKAGE_TOGGLED', $admin['id'], null, ['plan_id' => (int)$id, 'is_active' => $target]);
        Helpers::redirect('/admin/packages');
    }

    public function billingRequests()
    {
        $user = Auth::user();
        $status = $_GET['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'approved', 'declined', 'all'], true)) {
            $status = 'pending';
        }
        $requests = $status === 'all' ? PaymentRequest::listForAdmin(null) : PaymentRequest::listForAdmin($status);
        $this->view('app/admin_billing_requests', [
            'user' => $user,
            'requests' => $requests,
            'status' => $status,
            'currency' => Helpers::config('DEFAULT_CURRENCY', 'USD'),
        ]);
    }

    public function billingRequestDetail($id)
    {
        $user = Auth::user();
        $request = PaymentRequest::findWithRelations($id);
        if (!$request) {
            http_response_code(404);
            echo 'Payment request not found';
            return;
        }

        $plans = Plan::all();
        $latestSub = Subscription::latestForUser($request['user_id']);
        $usage = Billing::usage($request['user_id']);
        $this->view('app/admin_billing_request_detail', [
            'user' => $user,
            'request' => $request,
            'plans' => $plans,
            'latest_subscription' => $latestSub,
            'usage' => $usage,
            'currency' => Helpers::config('DEFAULT_CURRENCY', 'USD'),
        ]);
    }

    public function approveRequest($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/billing/requests');
        }
        $admin = Auth::user();
        $request = PaymentRequest::findWithRelations($id);
        if (!$request || $request['status'] !== 'pending') {
            Helpers::redirect('/admin/billing/requests?err=not_pending');
        }

        $startRaw = trim($_POST['approved_started_at'] ?? '');
        $endRaw = trim($_POST['approved_ends_at'] ?? '');
        $adminNote = trim($_POST['admin_note'] ?? '');

        try {
            $start = $startRaw !== '' ? new \DateTimeImmutable($startRaw) : new \DateTimeImmutable('now');
            if ($endRaw !== '') {
                $end = new \DateTimeImmutable($endRaw);
            } else {
                $end = $start->modify('+' . (int)$request['months'] . ' months');
            }
        } catch (\Throwable $e) {
            Helpers::redirect('/admin/billing/requests/' . $id . '?err=dates');
        }
        if ($end <= $start) {
            Helpers::redirect('/admin/billing/requests/' . $id . '?err=dates');
        }

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            $ok = PaymentRequest::approve(
                $id,
                $admin['id'],
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s'),
                $adminNote
            );
            if (!$ok) {
                throw new \RuntimeException('Request no longer pending');
            }

            Subscription::activate(
                (int)$request['user_id'],
                (int)$request['plan_id'],
                (int)$request['requested_systems'],
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Helpers::redirect('/admin/billing/requests/' . $id . '?err=approve');
        }

        AuditLog::log('PAYMENT_REQUEST_APPROVED', $request['user_id'], null, [
            'payment_request_id' => (int)$id,
            'reviewed_by' => $admin['id'],
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]);

        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents(
            $logPath,
            '[' . date('Y-m-d H:i:s') . '] Billing approved for user ' . $request['user_email'] . ' until ' . $end->format('Y-m-d H:i:s') . "\n",
            FILE_APPEND
        );

        Helpers::redirect('/admin/billing/requests?approved=' . $id);
    }

    public function declineRequest($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/billing/requests');
        }
        $admin = Auth::user();
        $request = PaymentRequest::findWithRelations($id);
        if (!$request || $request['status'] !== 'pending') {
            Helpers::redirect('/admin/billing/requests?err=not_pending');
        }
        $adminNote = trim($_POST['admin_note'] ?? '');
        if ($adminNote === '') {
            $adminNote = 'Payment could not be verified.';
        }

        $ok = PaymentRequest::decline($id, $admin['id'], $adminNote);
        if ($ok) {
            Subscription::declinePendingForUser((int)$request['user_id']);
            AuditLog::log('PAYMENT_REQUEST_DECLINED', $request['user_id'], null, [
                'payment_request_id' => (int)$id,
                'reviewed_by' => $admin['id'],
                'reason' => $adminNote,
            ]);
            $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
            if (!is_dir(dirname($logPath))) {
                mkdir(dirname($logPath), 0755, true);
            }
            file_put_contents(
                $logPath,
                '[' . date('Y-m-d H:i:s') . '] Billing declined for user ' . $request['user_email'] . '. Reason: ' . $adminNote . "\n",
                FILE_APPEND
            );
        }
        Helpers::redirect('/admin/billing/requests?declined=' . $id);
    }

    public function adjustUserSubscription($id)
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/admin/billing/requests');
        }
        $admin = Auth::user();
        $targetUser = User::find($id);
        if (!$targetUser) {
            Helpers::redirect('/admin/billing/requests?err=user');
        }

        $planId = (int)($_POST['plan_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        $allowedSystems = (int)($_POST['allowed_systems'] ?? 0);
        $startedAt = trim($_POST['started_at'] ?? '');
        $endsAt = trim($_POST['ends_at'] ?? '');

        if (!in_array($status, ['inactive', 'pending', 'active', 'expired', 'declined', 'cancelled'], true)) {
            Helpers::redirect('/admin/billing/requests?err=status');
        }
        $plan = Plan::find($planId);
        if (!$plan) {
            Helpers::redirect('/admin/billing/requests?err=plan');
        }
        if ($allowedSystems < 0 || $allowedSystems > (int)$plan['max_systems']) {
            Helpers::redirect('/admin/billing/requests?err=allowed_systems');
        }

        try {
            $start = $startedAt !== '' ? new \DateTimeImmutable($startedAt) : new \DateTimeImmutable('now');
            $end = $endsAt !== '' ? new \DateTimeImmutable($endsAt) : $start->modify('+1 month');
        } catch (\Throwable $e) {
            Helpers::redirect('/admin/billing/requests?err=dates');
        }
        if ($end <= $start) {
            Helpers::redirect('/admin/billing/requests?err=dates');
        }

        Subscription::adminAdjust(
            (int)$id,
            $planId,
            $status,
            $allowedSystems,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );

        AuditLog::log('ADMIN_SUBSCRIPTION_ADJUSTED', $admin['id'], null, [
            'target_user_id' => (int)$id,
            'plan_id' => $planId,
            'status' => $status,
            'allowed_systems' => $allowedSystems,
            'started_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
        ]);
        Helpers::redirect('/admin/billing/requests?adjusted=' . (int)$id);
    }

    private function validatePlanPayload($payload): array
    {
        $name = trim($payload['name'] ?? '');
        $description = trim($payload['description'] ?? '');
        $base = (float)($payload['base_price_monthly'] ?? 0);
        $perSystem = (float)($payload['price_per_system_monthly'] ?? 0);
        $storage = (int)($payload['storage_quota_mb'] ?? 0);
        $maxSystems = (int)($payload['max_systems'] ?? 0);
        $retention = (int)($payload['retention_days'] ?? 30);
        $interval = (int)($payload['min_backup_interval_minutes'] ?? 60);
        $isActive = isset($payload['is_active']) ? 1 : 0;

        if ($name === '') {
            return ['ok' => false, 'error' => 'Plan name is required'];
        }
        if ($base < 0 || $perSystem < 0) {
            return ['ok' => false, 'error' => 'Prices must be non-negative'];
        }
        if ($storage < 1 || $maxSystems < 1) {
            return ['ok' => false, 'error' => 'Storage and max systems must be at least 1'];
        }
        if ($retention < 1 || $interval < 1) {
            return ['ok' => false, 'error' => 'Retention and interval must be at least 1'];
        }

        return [
            'ok' => true,
            'data' => [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'base_price_monthly' => number_format($base, 2, '.', ''),
                'price_per_system_monthly' => number_format($perSystem, 2, '.', ''),
                'storage_quota_mb' => $storage,
                'max_systems' => $maxSystems,
                'retention_days' => $retention,
                'min_backup_interval_minutes' => $interval,
                'is_active' => $isActive,
            ],
        ];
    }
}
