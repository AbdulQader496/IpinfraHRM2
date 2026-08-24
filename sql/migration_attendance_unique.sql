-- Fixes double clock-in: clock.php checks "does today's attendance row exist?"
-- then inserts if not, with no locking — two near-simultaneous clock-in
-- requests (double-click, replayed request) can both pass the check and both
-- insert, producing two attendance rows for the same employee on the same day.
--
-- Before adding the constraint, remove any duplicate (employee_id, date) rows
-- that already exist, keeping the most recently created one (highest id) per pair.
-- Review this on production first if there's meaningful attendance history —
-- this deletes the OLDER duplicate row, not the newer one.
DELETE a1 FROM attendance a1
INNER JOIN attendance a2
    ON a1.employee_id = a2.employee_id
   AND a1.date = a2.date
   AND a1.id < a2.id;

ALTER TABLE attendance ADD UNIQUE KEY unique_attendance (employee_id, date);
