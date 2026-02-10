CREATE DATABASE IF NOT EXISTS secureon;
USE secureon;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) DEFAULT 'user',
  status VARCHAR(20) DEFAULT 'active',
  suspended_at DATETIME NULL,
  suspension_reason TEXT NULL,
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL
);

CREATE TABLE plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  base_price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  price_per_system_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  storage_quota_mb INT NOT NULL DEFAULT 0,
  max_systems INT NOT NULL DEFAULT 1,
  retention_days INT NOT NULL DEFAULT 30,
  min_backup_interval_minutes INT NOT NULL DEFAULT 60,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  status ENUM('inactive','pending','active','expired','declined','cancelled') NOT NULL DEFAULT 'inactive',
  allowed_systems INT NOT NULL DEFAULT 0,
  started_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (plan_id) REFERENCES plans(id)
);

CREATE TABLE systems (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  environment VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL,
  timezone VARCHAR(60) NOT NULL,
  secret VARCHAR(64) NOT NULL,
  trigger_url VARCHAR(500) NULL,
  trigger_path VARCHAR(120) NOT NULL,
  last_trigger_at DATETIME NULL,
  last_trigger_status VARCHAR(30) NULL,
  last_trigger_http_code INT NULL,
  last_trigger_latency_ms INT NULL,
  last_trigger_message TEXT NULL,
  expected_interval_minutes INT NULL,
  agent_ip_allowlist TEXT NULL,
  agent_last_seen_at DATETIME NULL,
  agent_last_ip VARCHAR(64) NULL,
  last_trigger_nonce VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE system_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  token_prefix VARCHAR(10) NOT NULL,
  token_type ENUM('agent','badge') NOT NULL DEFAULT 'agent',
  label VARCHAR(50) NULL,
  last_used_at DATETIME NULL,
  last_used_ip VARCHAR(64) NULL,
  last_used_user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  FOREIGN KEY (system_id) REFERENCES systems(id)
);

CREATE TABLE backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  system_id INT NOT NULL,
  user_id INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  size_bytes BIGINT NULL,
  checksum_sha256 VARCHAR(64) NULL,
  storage_path TEXT NULL,
  label VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (system_id) REFERENCES systems(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE backup_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  backup_id INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  message TEXT NULL,
  bytes_uploaded BIGINT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (backup_id) REFERENCES backups(id)
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  system_id INT NULL,
  action VARCHAR(100) NOT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  meta_json TEXT NULL,
  created_at DATETIME NOT NULL
);

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE download_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  backup_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  FOREIGN KEY (backup_id) REFERENCES backups(id)
);

CREATE TABLE payment_requests (
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
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (plan_id) REFERENCES plans(id),
  FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
);

INSERT INTO plans (name, description, base_price_monthly, price_per_system_monthly, storage_quota_mb, max_systems, retention_days, min_backup_interval_minutes, is_active, created_at, updated_at) VALUES
('Starter', 'Entry plan for small deployments', 19.00, 2.00, 51200, 2, 7, 60, 1, NOW(), NOW()),
('Pro', 'Growing workloads with tighter RPO', 49.00, 3.00, 204800, 10, 30, 30, 1, NOW(), NOW()),
('Business', 'High-volume backup operations', 99.00, 5.00, 1024000, 50, 90, 30, 1, NOW(), NOW());

INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES
('Super Admin', 'super@secureon.cloud', '$2y$10$ivV8XVBA.7eweJNdoU304uyGgph728IO9tno..9gt/6u54Kk.BoHm', 'super_admin', 'active', NOW()),
('Demo User', 'demo@secureon.cloud', '$2y$10$9fWQU1wrcqHLzt6v0XQWquWGIWq8U1fGdGx9tqBoD70bRRDkUnb6W', 'admin', 'active', NOW());

INSERT INTO subscriptions (user_id, plan_id, status, allowed_systems, started_at, ends_at, created_at, updated_at) VALUES
(2, 2, 'active', 5, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW(), NOW());

INSERT INTO systems (user_id, name, environment, status, timezone, secret, trigger_path, expected_interval_minutes, created_at) VALUES
(2, 'Production DB', 'production', 'Healthy', 'UTC', 'a1b2c3d4e5f60123456789abcdef1234', 'trigger-a1b2c3d4e5f6', 60, NOW()),
(2, 'Staging DB', 'staging', 'Warning', 'UTC', 'b2c3d4e5f60123456789abcdef1234a', 'trigger-b2c3d4e5f601', 60, NOW());

INSERT INTO backups (system_id, user_id, status, started_at, completed_at, size_bytes, checksum_sha256, storage_path, label, created_at) VALUES
(1, 2, 'COMPLETED', NOW(), NOW(), 1048576, 'demo', NULL, 'Nightly', NOW()),
(2, 2, 'FAILED', NOW(), NULL, 0, NULL, NULL, 'Hourly', NOW());

INSERT INTO backup_events (backup_id, event_type, message, bytes_uploaded, created_at) VALUES
(1, 'COMPLETE', 'Backup completed', 1048576, NOW()),
(2, 'FAIL', 'Connection lost', 0, NOW());
