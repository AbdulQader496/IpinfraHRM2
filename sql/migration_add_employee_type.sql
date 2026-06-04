USE hrmmanagement;

ALTER TABLE employees ADD COLUMN employee_type ENUM('intern', 'regular') DEFAULT 'regular' AFTER role;
ALTER TABLE payroll ADD COLUMN approved_claims DECIMAL(10,2) DEFAULT 0 AFTER allowances;

UPDATE employees SET employee_type = 'regular' WHERE employee_type IS NULL;

-- Department management table
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed from existing employee data
INSERT IGNORE INTO departments (name)
    SELECT DISTINCT department FROM employees
    WHERE department IS NOT NULL AND department != '';

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT DEFAULT 0,
    action      VARCHAR(50),
    description TEXT,
    target_type VARCHAR(50),
    target_id   INT DEFAULT 0,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
);

SELECT 'Done.' AS status;
