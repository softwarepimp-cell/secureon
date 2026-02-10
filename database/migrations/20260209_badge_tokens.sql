USE secureon;

SET @has_token_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_tokens' AND COLUMN_NAME = 'token_type'
);
SET @sql := IF(@has_token_type = 0,
  'ALTER TABLE system_tokens ADD COLUMN token_type ENUM(''agent'',''badge'') NOT NULL DEFAULT ''agent'' AFTER token_prefix',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_label := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_tokens' AND COLUMN_NAME = 'label'
);
SET @sql := IF(@has_label = 0,
  'ALTER TABLE system_tokens ADD COLUMN label VARCHAR(50) NULL AFTER token_type',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_last_ua := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_tokens' AND COLUMN_NAME = 'last_used_user_agent'
);
SET @sql := IF(@has_last_ua = 0,
  'ALTER TABLE system_tokens ADD COLUMN last_used_user_agent VARCHAR(255) NULL AFTER last_used_ip',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_tokens' AND INDEX_NAME = 'idx_system_tokens_system_type_revoked'
);
SET @sql := IF(@has_idx = 0,
  'CREATE INDEX idx_system_tokens_system_type_revoked ON system_tokens (system_id, token_type, revoked_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
