<?php
namespace App\Models;

use App\Core\DB;
use App\Core\Helpers;

class Subscription
{
    private static ?bool $hasUpdatedAt = null;

    private static function hasUpdatedAt(): bool
    {
        if (self::$hasUpdatedAt !== null) {
            return self::$hasUpdatedAt;
        }
        $stmt = DB::conn()->query("SHOW COLUMNS FROM subscriptions LIKE 'updated_at'");
        self::$hasUpdatedAt = (bool)$stmt->fetch();
        return self::$hasUpdatedAt;
    }

    private static function updateTouchSql(): string
    {
        return self::hasUpdatedAt() ? ', updated_at = NOW()' : '';
    }

    private static function insertColumnsSql(): string
    {
        return self::hasUpdatedAt()
            ? '(user_id, plan_id, status, allowed_systems, started_at, ends_at, created_at, updated_at)'
            : '(user_id, plan_id, status, allowed_systems, started_at, ends_at, created_at)';
    }

    private static function insertValuesSql(string $statusLiteral): string
    {
        return self::hasUpdatedAt()
            ? "VALUES (?, ?, {$statusLiteral}, ?, ?, ?, NOW(), NOW())"
            : "VALUES (?, ?, {$statusLiteral}, ?, ?, ?, NOW())";
    }

    public static function currentForUser($userId)
    {
        self::expireDueForUser($userId);
        $grace = (int)Helpers::config('BILLING_GRACE_DAYS', 0);
        $stmt = DB::conn()->prepare(
            'SELECT s.*, p.name as plan_name, p.description as plan_description, p.base_price_monthly, p.price_per_system_monthly, p.storage_quota_mb, p.max_systems, p.retention_days, p.min_backup_interval_minutes
             FROM subscriptions s
             JOIN plans p ON s.plan_id = p.id
             WHERE s.user_id = ? AND s.status = "active"
               AND (s.ends_at IS NULL OR DATE_ADD(s.ends_at, INTERVAL ? DAY) > NOW())
             ORDER BY s.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId, $grace]);
        return $stmt->fetch();
    }

    public static function latestForUser($userId)
    {
        $stmt = DB::conn()->prepare(
            'SELECT s.*, p.name as plan_name, p.storage_quota_mb, p.max_systems, p.retention_days, p.min_backup_interval_minutes
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public static function isBillingActive($userId): bool
    {
        return (bool)self::currentForUser($userId);
    }

    public static function expireDueForUser($userId)
    {
        $grace = (int)Helpers::config('BILLING_GRACE_DAYS', 0);
        $stmt = DB::conn()->prepare(
            'UPDATE subscriptions
             SET status = "expired"' . self::updateTouchSql() . '
             WHERE user_id = ? AND status = "active" AND ends_at IS NOT NULL AND DATE_ADD(ends_at, INTERVAL ? DAY) <= NOW()'
        );
        $stmt->execute([$userId, $grace]);
    }

    public static function expireDueAll()
    {
        $grace = (int)Helpers::config('BILLING_GRACE_DAYS', 0);
        $stmt = DB::conn()->prepare(
            'UPDATE subscriptions
             SET status = "expired"' . self::updateTouchSql() . '
             WHERE status = "active" AND ends_at IS NOT NULL AND DATE_ADD(ends_at, INTERVAL ? DAY) <= NOW()'
        );
        $stmt->execute([$grace]);
    }

    public static function activate($userId, $planId, $allowedSystems, $startedAt, $endsAt)
    {
        $pdo = DB::conn();
        $pdo->prepare('UPDATE subscriptions SET status = "expired"' . self::updateTouchSql() . ' WHERE user_id = ? AND status IN ("active","pending")')->execute([$userId]);
        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions ' . self::insertColumnsSql() . '
             ' . self::insertValuesSql('"active"')
        );
        $stmt->execute([$userId, $planId, $allowedSystems, $startedAt, $endsAt]);
        return (int)$pdo->lastInsertId();
    }

    public static function markPendingForUser($userId, $planId)
    {
        $pdo = DB::conn();
        $pdo->prepare('UPDATE subscriptions SET status = "inactive"' . self::updateTouchSql() . ' WHERE user_id = ? AND status = "pending"')->execute([$userId]);
        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions ' . self::insertColumnsSql() . '
             ' . self::insertValuesSql('"pending"')
        );
        $stmt->execute([$userId, $planId, 0, null, null]);
        return (int)$pdo->lastInsertId();
    }

    public static function declinePendingForUser($userId)
    {
        $stmt = DB::conn()->prepare('UPDATE subscriptions SET status = "declined"' . self::updateTouchSql() . ' WHERE user_id = ? AND status = "pending"');
        $stmt->execute([$userId]);
    }

    public static function adminAdjust($userId, $planId, $status, $allowedSystems, $startedAt, $endsAt)
    {
        $pdo = DB::conn();
        $pdo->prepare('UPDATE subscriptions SET status = "expired"' . self::updateTouchSql() . ' WHERE user_id = ? AND status IN ("active","pending")')->execute([$userId]);
        if ($status === 'active') {
            return self::activate($userId, $planId, $allowedSystems, $startedAt, $endsAt);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO subscriptions ' . self::insertColumnsSql() . '
             ' . self::insertValuesSql('?')
        );
        $stmt->execute([$userId, $planId, $status, $allowedSystems, $startedAt, $endsAt]);
        return (int)$pdo->lastInsertId();
    }

    public static function createOrUpdate($userId, $planId)
    {
        // Legacy/admin helper: make a 1-month active subscription using plan max_systems.
        $plan = Plan::find($planId);
        if (!$plan) {
            return 0;
        }
        $start = new \DateTimeImmutable('now');
        $end = $start->modify('+1 month');
        return self::activate(
            $userId,
            $planId,
            (int)$plan['max_systems'],
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );
    }
}

