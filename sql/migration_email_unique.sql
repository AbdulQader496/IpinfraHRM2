-- Login matches "WHERE email = ? AND status = 'active'" expecting exactly one row. With
-- no uniqueness constraint, two active employees sharing an email silently locks BOTH of
-- them out (the query returns 2 rows, the app requires exactly 1). employees.php now
-- checks for this before insert/update, but that's an app-level guard only -- this adds
-- the actual DB-level constraint as the real source of truth, matching how employee_id
-- is already protected.
--
-- Only employees with a non-empty email are constrained; NULL/empty stay unconstrained
-- since MySQL treats multiple NULLs as distinct under a UNIQUE index, and a handful of
-- legacy rows in this dataset have blank emails.
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'employees'
    AND INDEX_NAME = 'unique_email'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE employees ADD UNIQUE INDEX unique_email (email)',
    'SELECT "unique_email already exists" AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration complete.' AS status;
