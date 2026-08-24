-- Fixes half-day leave balances rounding incorrectly.
-- employees.used_annual_leave / used_medical_leave were INT, so adding a
-- single 0.5-day (half day) leave rounded UP to a full day immediately,
-- and two half days ended up deducting 2 full days instead of 1.
-- Widening to DECIMAL(5,2) lets 0.5 increments accumulate correctly
-- (0.5 + 0.5 = 1.0), matching leaves.total_days which is already DECIMAL(5,2).
ALTER TABLE employees
    MODIFY annual_leave_entitlement DECIMAL(5,2) DEFAULT 14,
    MODIFY medical_leave_entitlement DECIMAL(5,2) DEFAULT 14,
    MODIFY used_annual_leave DECIMAL(5,2) DEFAULT 0,
    MODIFY used_medical_leave DECIMAL(5,2) DEFAULT 0;

-- Deactivates the "Halfday" leave type. It was a duplicate/mistaken entry —
-- half-day leave is already handled by the half_day field (first_half/second_half)
-- on a real leave type (Annual/Medical/etc). Because leaves.leave_type is a
-- MySQL ENUM('annual','medical','emergency','unpaid'), selecting "Halfday"
-- (leave_code = 'halfday') silently saved as an empty leave_type on submit.
UPDATE leave_types SET status = 'inactive' WHERE leave_code = 'halfday';
