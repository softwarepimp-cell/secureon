<?php
namespace App\Models;

use App\Core\DB;

class Plan
{
    public static function all()
    {
        $stmt = DB::conn()->query('SELECT * FROM plans ORDER BY base_price_monthly ASC, id ASC');
        return $stmt->fetchAll();
    }

    public static function allActive()
    {
        $stmt = DB::conn()->query('SELECT * FROM plans WHERE is_active = 1 ORDER BY base_price_monthly ASC, id ASC');
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $stmt = DB::conn()->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($data)
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO plans
            (name, description, base_price_monthly, price_per_system_monthly, storage_quota_mb, max_systems, retention_days, min_backup_interval_minutes, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['base_price_monthly'],
            $data['price_per_system_monthly'],
            $data['storage_quota_mb'],
            $data['max_systems'],
            $data['retention_days'],
            $data['min_backup_interval_minutes'],
            $data['is_active'],
        ]);
        return (int)DB::conn()->lastInsertId();
    }

    public static function update($id, $data)
    {
        $stmt = DB::conn()->prepare(
            'UPDATE plans
             SET name = ?, description = ?, base_price_monthly = ?, price_per_system_monthly = ?, storage_quota_mb = ?, max_systems = ?, retention_days = ?, min_backup_interval_minutes = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['base_price_monthly'],
            $data['price_per_system_monthly'],
            $data['storage_quota_mb'],
            $data['max_systems'],
            $data['retention_days'],
            $data['min_backup_interval_minutes'],
            $data['is_active'],
            $id,
        ]);
    }

    public static function toggle($id, $isActive)
    {
        $stmt = DB::conn()->prepare('UPDATE plans SET is_active = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$isActive ? 1 : 0, $id]);
    }
}

