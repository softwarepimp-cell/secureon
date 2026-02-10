<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Helpers;
use App\Core\CSRF;
use App\Core\Billing;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\PaymentRequest;
use App\Models\AuditLog;

class BillingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $plans = Plan::allActive();
        $subscription = Subscription::currentForUser($user['id']);
        $latestSubscription = Subscription::latestForUser($user['id']);
        $usage = Billing::usage($user['id']);
        $requests = PaymentRequest::listByUser($user['id']);

        $this->view('app/billing', [
            'user' => $user,
            'plans' => $plans,
            'subscription' => $subscription,
            'latest_subscription' => $latestSubscription,
            'usage' => $usage,
            'requests' => $requests,
            'currency' => Helpers::config('DEFAULT_CURRENCY', 'USD'),
            'max_months' => (int)Helpers::config('MAX_BILLING_MONTHS', 60),
        ]);
    }

    public function estimate()
    {
        $csrf = $_SERVER['HTTP_X_CSRF'] ?? ($_POST['_csrf'] ?? '');
        if (!CSRF::validate($csrf)) {
            $this->json(['error' => 'Invalid CSRF'], 419);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $planId = (int)($input['plan_id'] ?? 0);
        $months = (int)($input['months'] ?? 0);
        $requestedSystems = (int)($input['requested_systems'] ?? 0);
        $maxMonths = (int)Helpers::config('MAX_BILLING_MONTHS', 60);

        $plan = Plan::find($planId);
        if (!$plan || (int)$plan['is_active'] !== 1) {
            $this->json(['error' => 'Plan not available'], 404);
            return;
        }
        if ($months < 1 || $months > $maxMonths) {
            $this->json(['error' => 'Invalid duration'], 422);
            return;
        }
        if ($requestedSystems < 1 || $requestedSystems > (int)$plan['max_systems']) {
            $this->json(['error' => 'Invalid requested systems'], 422);
            return;
        }

        $calc = Billing::calculateAmount($plan, $months, $requestedSystems);
        $this->json([
            'ok' => true,
            'currency' => Helpers::config('DEFAULT_CURRENCY', 'USD'),
            'plan_name' => $plan['name'],
            'plan' => [
                'storage_quota_mb' => (int)$plan['storage_quota_mb'],
                'max_systems' => (int)$plan['max_systems'],
                'retention_days' => (int)$plan['retention_days'],
                'min_backup_interval_minutes' => (int)$plan['min_backup_interval_minutes'],
            ],
            'breakdown' => $calc,
        ]);
    }

    public function requestPayment()
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/billing?err=csrf');
        }

        $user = Auth::user();
        $planId = (int)($_POST['plan_id'] ?? 0);
        $months = (int)($_POST['months'] ?? 0);
        $requestedSystems = (int)($_POST['requested_systems'] ?? 0);
        $proofReference = trim($_POST['proof_reference'] ?? '');
        $proofNote = trim($_POST['proof_note'] ?? '');
        $ack = $_POST['ack_manual'] ?? '';
        $maxMonths = (int)Helpers::config('MAX_BILLING_MONTHS', 60);

        $plan = Plan::find($planId);
        if (!$plan || (int)$plan['is_active'] !== 1) {
            Helpers::redirect('/billing?err=plan');
        }
        if ($months < 1 || $months > $maxMonths) {
            Helpers::redirect('/billing?err=months');
        }
        if ($requestedSystems < 1 || $requestedSystems > (int)$plan['max_systems']) {
            Helpers::redirect('/billing?err=systems');
        }
        if ($proofReference === '') {
            Helpers::redirect('/billing?err=proof');
        }
        if (!$ack) {
            Helpers::redirect('/billing?err=ack');
        }

        $calc = Billing::calculateAmount($plan, $months, $requestedSystems);
        $currency = Helpers::config('DEFAULT_CURRENCY', 'USD');
        $requestId = PaymentRequest::create(
            $user['id'],
            $planId,
            $months,
            $requestedSystems,
            $calc['total'],
            $currency,
            $proofReference,
            $proofNote
        );

        Subscription::markPendingForUser($user['id'], $planId);

        AuditLog::log('PAYMENT_REQUEST_CREATED', $user['id'], null, [
            'payment_request_id' => $requestId,
            'plan_id' => $planId,
            'months' => $months,
            'requested_systems' => $requestedSystems,
            'amount_total' => $calc['total'],
        ]);

        $logPath = Helpers::config('STORAGE_PATH') . '/logs/mail.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        file_put_contents(
            $logPath,
            '[' . date('Y-m-d H:i:s') . '] PAYMENT_REQUEST_PENDING user=' . $user['email'] . ' request=' . $requestId . "\n",
            FILE_APPEND
        );

        Helpers::redirect('/billing?requested=' . $requestId);
    }

    public function requests()
    {
        $user = Auth::user();
        $requests = PaymentRequest::listByUser($user['id']);
        $this->view('app/billing_requests', [
            'user' => $user,
            'requests' => $requests,
            'currency' => Helpers::config('DEFAULT_CURRENCY', 'USD'),
        ]);
    }
}

