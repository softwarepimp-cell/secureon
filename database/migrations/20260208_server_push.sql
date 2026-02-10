ALTER TABLE systems
  ADD COLUMN trigger_url VARCHAR(500) NULL,
  ADD COLUMN trigger_path VARCHAR(120) NULL,
  ADD COLUMN last_trigger_at DATETIME NULL,
  ADD COLUMN last_trigger_status VARCHAR(30) NULL,
  ADD COLUMN last_trigger_http_code INT NULL,
  ADD COLUMN last_trigger_latency_ms INT NULL,
  ADD COLUMN last_trigger_message TEXT NULL,
  ADD COLUMN expected_interval_minutes INT NULL,
  ADD COLUMN agent_ip_allowlist TEXT NULL,
  ADD COLUMN agent_last_seen_at DATETIME NULL,
  ADD COLUMN agent_last_ip VARCHAR(64) NULL,
  ADD COLUMN last_trigger_nonce VARCHAR(64) NULL;

UPDATE systems SET trigger_path = CONCAT('trigger-', SUBSTRING(MD5(RAND()),1,12)) WHERE trigger_path IS NULL;

ALTER TABLE systems MODIFY trigger_path VARCHAR(120) NOT NULL;
