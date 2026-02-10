<?php
namespace App\Core;

use App\Models\Backup;
use App\Models\Subscription;
use App\Models\System;

class Billing
{
    public static function usage($userId): array
    {
        $systemsCount = count(System::allByUser($userId));
        $storageBytes = Backup::storageUsedByUser($userId);
        return [
            'systems_count' => $systemsCount,
            'storage_bytes' => $storageBytes,
        ];
    }

    public static function activeSubscription($userId)
    {
        return Subscription::currentForUser($userId);
    }

    public static function latestSubscription($userId)
    {
        return Subscription::latestForUser($userId);
    }

    public static function getEntitlements($userId): array
    {
        $subscription = self::activeSubscription($userId);
        if (!$subscription) {
            return [
                'active' => false,
                'allowed_systems' => 0,
                'storage_quota_mb' => 0,
                'retention_days' => 0,
                'min_backup_interval_minutes' => 60,
                'plan_name' => null,
                'ends_at' => null,
            ];
        }

        $allowedSystems = (int)($subscription['allowed_systems'] ?? 0);
        if ($allowedSystems <= 0) {
            // Backward compatibility for legacy active subscriptions.
            $allowedSystems = (int)($subscription['max_systems'] ?? 0);
        }

        return [
            'active' => true,
            'allowed_systems' => $allowedSystems,
            'storage_quota_mb' => (int)($subscription['storage_quota_mb'] ?? 0),
            'retention_days' => (int)($subscription['retention_days'] ?? 0),
            'min_backup_interval_minutes' => (int)($subscription['min_backup_interval_minutes'] ?? 60),
            'plan_name' => $subscription['plan_name'] ?? null,
            'ends_at' => $subscription['ends_at'] ?? null,
            'subscription' => $subscription,
        ];
    }

    public static function ensureActive($userId): array
    {
        $ent = self::getEntitlements($userId);
        if (!$ent['active']) {
            return ['ok' => false, 'code' => 'BILLING_INACTIVE', 'message' => 'Billing required. Submit or renew payment request.'];
        }
        return ['ok' => true, 'code' => null, 'message' => null, 'entitlements' => $ent];
    }

    public static function ensureCanCreateSystem($userId): array
    {
        $active = self::ensureActive($userId);
        if (!$active['ok']) {
            return $active;
        }
        $ent = $active['entitlements'];
        $usage = self::usage($userId);
        if ($usage['systems_count'] >= $ent['allowed_systems']) {
            return ['ok' => false, 'code' => 'SYSTEM_LIMIT', 'message' => 'System limit reached. Upgrade package or request more systems.'];
        }
        return ['ok' => true, 'entitlements' => $ent, 'usage' => $usage];
    }

    public static function ensureStorageAvailable($userId): array
    {
        $active = self::ensureActive($userId);
        if (!$active['ok']) {
            return $active;
        }
        $ent = $active['entitlements'];
        $usage = self::usage($userId);
        $quotaBytes = (int)$ent['storage_quota_mb'] * 1024 * 1024;
        if ($quotaBytes > 0 && $usage['storage_bytes'] >= $quotaBytes) {
            return ['ok' => false, 'code' => 'QUOTA_EXCEEDED', 'message' => 'Storage quota reached. Upgrade plan or request extension.'];
        }
        return ['ok' => true, 'entitlements' => $ent, 'usage' => $usage];
    }

    public static function calculateAmount($plan, $months, $requestedSystems): array
    {
        $baseMonthly = (float)$plan['base_price_monthly'];
        $systemMonthly = (float)$plan['price_per_system_monthly'];
        $baseTotal = $baseMonthly * $months;
        $systemsTotal = $systemMonthly * $months * $requestedSystems;
        $total = $baseTotal + $systemsTotal;
        return [
            'months' => $months,
            'requested_systems' => $requestedSystems,
            'base_total' => round($baseTotal, 2),
            'systems_total' => round($systemsTotal, 2),
            'total' => round($total, 2),
        ];
    }
}

