USE secureon;

-- Plans: convert legacy pricing columns to manual-billing model
ALTER TABLE plans
  CHANGE COLUMN price_monthly base_price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE plans
  ADD COLUMN description TEXT NULL AFTER name,
  ADD COLUMN price_per_system_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER base_price_monthly,
  ADD COLUMN max_systems INT NOT NULL DEFAULT 1 AFTER storage_quota_mb,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER min_backup_interval_minutes,
  ADD COLUMN updated_at DATETIME NULL AFTER created_at;

UPDATE plans
SET
  max_systems = CASE
    WHEN name = 'Starter' THEN 2
    WHEN name = 'Pro' THEN 10
    WHEN name = 'Business' THEN 50
    ELSE GREATEST(max_systems, 1)
  END,
  description = CASE
    WHEN name = 'Starter' THEN 'Entry plan for small deployments'
    WHEN name = 'Pro' THEN 'Growing workloads with tighter RPO'
    WHEN name = 'Business' THEN 'High-volume backup operations'
    ELSE COALESCE(description, 'Custom package')
  END,
  updated_at = NOW();

-- Subscriptions: explicit lifecycle + allowed_systems entitlement
ALTER TABLE subscriptions
  MODIFY COLUMN status ENUM('inactive','pending','active','expired','declined','cancelled') NOT NULL DEFAULT 'inactive',
  ADD COLUMN allowed_systems INT NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN updated_at DATETIME NULL AFTER created_at;

UPDATE subscriptions s
JOIN plans p ON p.id = s.plan_id
SET s.allowed_systems = GREATEST(1, p.max_systems),
    s.updated_at = NOW()
WHERE s.allowed_systems = 0;

-- Payment requests for manual approval loop
CREATE TABLE IF NOT EXISTS payment_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  months INT NOT NULL,
  requested_systems INT NOT NULL,
  amount_total DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  proof_reference VARCHAR(120) NOT NULL,
  proof_note TEXT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at DATETIME NULL,
  approved_started_at DATETIME NULL,
  approved_ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_requests_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_payment_requests_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
  CONSTRAINT fk_payment_requests_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
);

-- Optional default plan seed for empty installations
INSERT INTO plans
  (name, description, base_price_monthly, price_per_system_monthly, storage_quota_mb, max_systems, retention_days, min_backup_interval_minutes, is_active, created_at, updated_at)
SELECT 'Starter', 'Entry plan for small deployments', 19.00, 2.00, 51200, 2, 7, 60, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM plans);
