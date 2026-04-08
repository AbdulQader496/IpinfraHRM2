CREATE DATABASE IF NOT EXISTS hrmmanagement;
USE hrmmanagement;

-- Employees table
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE,
    name VARCHAR(100),
    ic_number VARCHAR(20),
    email VARCHAR(100),
    password VARCHAR(255),
    role ENUM('admin', 'employee') DEFAULT 'employee',
    department VARCHAR(50),
    position VARCHAR(50),
    basic_salary DECIMAL(10,2),
    epf_no VARCHAR(20),
    socso_no VARCHAR(20),
    bank_name VARCHAR(50),
    bank_account VARCHAR(30),
    phone VARCHAR(15),
    address TEXT,
    join_date DATE,
    annual_leave_entitlement INT DEFAULT 14,
    medical_leave_entitlement INT DEFAULT 14,
    used_annual_leave INT DEFAULT 0,
    used_medical_leave INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    date DATE,
    clock_in TIME,
    clock_out TIME,
    status ENUM('present', 'late', 'absent', 'half_day') DEFAULT 'present',
    notes TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Leaves table
CREATE TABLE leaves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    leave_type ENUM('annual', 'medical', 'emergency', 'unpaid'),
    start_date DATE,
    end_date DATE,
    reason TEXT,
    attachment VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Claims table
CREATE TABLE claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    claim_type ENUM('travel', 'meal', 'medical', 'toll', 'parking', 'other'),
    amount DECIMAL(10,2),
    description TEXT,
    attachment VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Payroll table
CREATE TABLE payroll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    month_year VARCHAR(7),
    basic_salary DECIMAL(10,2),
    allowances DECIMAL(10,2) DEFAULT 0,
    overtime_pay DECIMAL(10,2) DEFAULT 0,
    unpaid_deduction DECIMAL(10,2) DEFAULT 0,
    epf_employee DECIMAL(10,2),
    epf_employer DECIMAL(10,2),
    socso_employee DECIMAL(10,2),
    socso_employer DECIMAL(10,2),
    eis_employee DECIMAL(10,2),
    eis_employer DECIMAL(10,2),
    pcb DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2),
    payslip_path VARCHAR(255),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll (employee_id, month_year)
);

-- Holidays table
CREATE TABLE holidays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_date DATE,
    holiday_name VARCHAR(100),
    type ENUM('public', 'company') DEFAULT 'public'
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    title VARCHAR(100),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Insert sample data
INSERT INTO employees (employee_id, name, ic_number, email, password, role, department, position, basic_salary, join_date) VALUES
('ADMIN001', 'Admin Ipinfra', '800101-01-1234', 'admin@ipinfra.com', 'password123', 'admin', 'Management', 'HR Manager', 8000.00, '2020-01-01'),
('EMP001', 'Ahmad Faiz', '900101-01-5678', 'ahmad@ipinfra.com', 'password123', 'employee', 'IT', 'Network Engineer', 4500.00, '2021-03-15'),
('EMP002', 'Siti Nuraini', '910202-01-9012', 'siti@ipinfra.com', 'password123', 'employee', 'Admin', 'Admin Executive', 3800.00, '2021-06-20'),
('EMP003', 'Raj Kumar', '880303-01-3456', 'raj@ipinfra.com', 'password123', 'employee', 'IT', 'System Analyst', 5200.00, '2020-09-10');

INSERT INTO holidays (holiday_date, holiday_name) VALUES
('2026-01-01', 'New Year'),
('2026-02-01', 'Federal Territory Day'),
('2026-02-17', 'Chinese New Year'),
('2026-05-01', 'Labour Day'),
('2026-05-13', 'Hari Raya Aidilfitri'),
('2026-08-31', 'Merdeka Day'),
('2026-09-16', 'Malaysia Day'),
('2026-12-25', 'Christmas');

INSERT INTO notifications (employee_id, title, message) VALUES
(2, 'Welcome', 'Welcome to Ipinfra Networks HR System'),
(3, 'Welcome', 'Welcome to Ipinfra Networks HR System'),
(4, 'Welcome', 'Welcome to Ipinfra Networks HR System');

SHOW TABLES;