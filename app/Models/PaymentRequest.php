<?php
namespace App\Models;

use App\Core\DB;

class PaymentRequest
{
    public static function create($userId, $planId, $months, $requestedSystems, $amountTotal, $currency, $proofReference, $proofNote)
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO payment_requests
            (user_id, plan_id, months, requested_systems, amount_total, currency, proof_reference, proof_note, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $planId,
            $months,
            $requestedSystems,
            $amountTotal,
            $currency,
            $proofReference,
            $proofNote,
        ]);
        return (int)DB::conn()->lastInsertId();
    }

    public static function find($id)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM payment_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findWithRelations($id)
    {
        $stmt = DB::conn()->prepare(
            'SELECT pr.*, u.name as user_name, u.email as user_email, p.name as plan_name, p.storage_quota_mb, p.max_systems, p.base_price_monthly, p.price_per_system_monthly
             FROM payment_requests pr
             JOIN users u ON u.id = pr.user_id
             JOIN plans p ON p.id = pr.plan_id
             WHERE pr.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function listByUser($userId)
    {
        $stmt = DB::conn()->prepare(
            'SELECT pr.*, p.name as plan_name
             FROM payment_requests pr
             JOIN plans p ON p.id = pr.plan_id
             WHERE pr.user_id = ?
             ORDER BY pr.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function listForAdmin($status = null)
    {
        if ($status) {
            $stmt = DB::conn()->prepare(
                'SELECT pr.*, u.name as user_name, u.email as user_email, p.name as plan_name
                 FROM payment_requests pr
                 JOIN users u ON u.id = pr.user_id
                 JOIN plans p ON p.id = pr.plan_id
                 WHERE pr.status = ?
                 ORDER BY pr.created_at ASC'
            );
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }
        $stmt = DB::conn()->query(
            'SELECT pr.*, u.name as user_name, u.email as user_email, p.name as plan_name
             FROM payment_requests pr
             JOIN users u ON u.id = pr.user_id
             JOIN plans p ON p.id = pr.plan_id
             ORDER BY FIELD(pr.status, "pending", "approved", "declined"), pr.created_at ASC'
        );
        return $stmt->fetchAll();
    }

    public static function approve($id, $reviewedByUserId, $startedAt, $endsAt, $adminNote = null)
    {
        $stmt = DB::conn()->prepare(
            'UPDATE payment_requests
             SET status = "approved",
                 admin_note = ?,
                 reviewed_by_user_id = ?,
                 reviewed_at = NOW(),
                 approved_started_at = ?,
                 approved_ends_at = ?,
                 updated_at = NOW()
             WHERE id = ? AND status = "pending"'
        );
        $stmt->execute([$adminNote, $reviewedByUserId, $startedAt, $endsAt, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function decline($id, $reviewedByUserId, $adminNote)
    {
        $stmt = DB::conn()->prepare(
            'UPDATE payment_requests
             SET status = "declined",
                 admin_note = ?,
                 reviewed_by_user_id = ?,
                 reviewed_at = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND status = "pending"'
        );
        $stmt->execute([$adminNote, $reviewedByUserId, $id]);
        return $stmt->rowCount() > 0;
    }
}

