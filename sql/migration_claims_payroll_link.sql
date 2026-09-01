-- ADD COLUMN IF NOT EXISTS is a MariaDB-only extension — plain MySQL (incl. RDS)
-- rejects it with a syntax error, so both columns below are added via an
-- INFORMATION_SCHEMA existence check + prepared statement instead, matching the
-- pattern already used for the foreign key further down.

-- reviewed_at was already in use by the app code but was never added via a tracked
-- migration in this repo (it exists on production already) — included here so a
-- fresh database stays in sync.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'claims'
    AND COLUMN_NAME = 'reviewed_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE claims ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT "reviewed_at already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tracks which payroll run actually paid out a claim. Payroll generation now sweeps
-- in every approved claim with payroll_id IS NULL (the "unpaid" pool) instead of
-- matching claims to a payroll by calendar month, so a claim approved late in one
-- month is picked up by the next payroll actually generated, whichever month that is,
-- rather than being silently skipped if that exact month's payroll is never run.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'claims'
    AND COLUMN_NAME = 'payroll_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE claims ADD COLUMN payroll_id INT NULL DEFAULT NULL',
    'SELECT "payroll_id already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'claims'
    AND CONSTRAINT_NAME = 'fk_claims_payroll'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE claims ADD CONSTRAINT fk_claims_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE SET NULL',
    'SELECT "fk_claims_payroll already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration complete.' AS status;
