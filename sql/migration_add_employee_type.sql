USE hrmmanagement;

ALTER TABLE employees ADD COLUMN employee_type ENUM('intern', 'regular') DEFAULT 'regular' AFTER role;
ALTER TABLE payroll ADD COLUMN approved_claims DECIMAL(10,2) DEFAULT 0 AFTER allowances;

UPDATE employees SET employee_type = 'regular' WHERE employee_type IS NULL;

SELECT 'Done.' AS status;
