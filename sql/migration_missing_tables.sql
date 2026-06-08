USE hrmmanagement;

-- Add missing columns to employees
ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS address TEXT NULL,
    ADD COLUMN IF NOT EXISTS employee_type ENUM('regular','intern') DEFAULT 'regular',
    ADD COLUMN IF NOT EXISTS is_subject_to_statutory TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) DEFAULT 'Malaysian',
    ADD COLUMN IF NOT EXISTS passport_no VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS employment_status VARCHAR(20) DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS is_terminated TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS termination_id INT NULL,
    ADD COLUMN IF NOT EXISTS resignation_id INT NULL,
    ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL;

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    announcement_type ENUM('info','warning','success','danger') DEFAULT 'info',
    target_role ENUM('all','employee','admin') DEFAULT 'all',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Leave types
CREATE TABLE IF NOT EXISTS leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    leave_name VARCHAR(100) NOT NULL,
    leave_code VARCHAR(20),
    days_per_year INT DEFAULT 14,
    is_paid TINYINT(1) DEFAULT 1,
    requires_attachment TINYINT(1) DEFAULT 0,
    max_consecutive_days INT DEFAULT 30,
    color_code VARCHAR(10) DEFAULT '#3B82F6',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing columns to leave_types (if table already existed without them)
ALTER TABLE leave_types
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS requires_attachment TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS max_consecutive_days INT DEFAULT 30,
    ADD COLUMN IF NOT EXISTS color_code VARCHAR(10) DEFAULT '#3B82F6';

-- Asset categories
CREATE TABLE IF NOT EXISTS asset_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assets
CREATE TABLE IF NOT EXISTS assets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_code VARCHAR(50) UNIQUE,
    asset_name VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 0,
    available_quantity INT DEFAULT 0,
    category_id INT NULL,
    brand VARCHAR(50),
    model VARCHAR(50),
    serial_number VARCHAR(100),
    purchase_date DATE NULL,
    purchase_price DECIMAL(10,2) DEFAULT 0,
    location VARCHAR(100),
    status ENUM('available','assigned','maintenance','retired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id) ON DELETE SET NULL
);

-- Asset requests
CREATE TABLE IF NOT EXISTS asset_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    asset_id INT NOT NULL,
    purpose TEXT,
    start_date DATE NULL,
    end_date DATE NULL,
    request_date DATE DEFAULT (CURDATE()),
    quantity INT DEFAULT 1,
    status ENUM('pending','approved','rejected','returned') DEFAULT 'pending',
    approved_by INT NULL,
    approved_date DATE NULL,
    returned_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Asset assignment history
CREATE TABLE IF NOT EXISTS asset_assignment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    employee_id INT NOT NULL,
    assigned_date DATE,
    returned_date DATE NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Claim attachments
CREATE TABLE IF NOT EXISTS claim_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_id INT NOT NULL,
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
);

-- Gallery
CREATE TABLE IF NOT EXISTS gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    image_path VARCHAR(255),
    caption TEXT,
    activity_date DATE NULL,
    status ENUM('active','hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Employee resignations (submitted by employee via self-service)
CREATE TABLE IF NOT EXISTS employee_resignations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    requested_date DATE,
    last_working_date DATE,
    reason TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_notes TEXT,
    approved_by INT NULL,
    approved_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Resignations (submitted by admin on behalf of employee)
CREATE TABLE IF NOT EXISTS resignations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    resignation_date DATE,
    last_working_date DATE,
    reason TEXT,
    type VARCHAR(50),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Terminations
CREATE TABLE IF NOT EXISTS terminations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    termination_date DATE,
    effective_date DATE,
    reason TEXT,
    termination_type VARCHAR(50),
    notice_period_days INT DEFAULT 0,
    severance_pay DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'approved',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Employee documents
CREATE TABLE IF NOT EXISTS employee_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    document_title VARCHAR(200),
    document_type VARCHAR(50),
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    file_size INT DEFAULT 0,
    upload_date DATE,
    notes TEXT,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES employees(id) ON DELETE SET NULL
);

-- Seed default asset categories
INSERT IGNORE INTO asset_categories (id, category_name) VALUES
(1, 'Laptop'),
(2, 'Mobile Phone'),
(3, 'Monitor'),
(4, 'Networking Equipment'),
(5, 'Office Furniture'),
(6, 'Other');

-- Seed default leave types
INSERT IGNORE INTO leave_types (leave_name, leave_code, days_per_year, is_paid, requires_attachment, color_code) VALUES
('Annual Leave',    'AL',  14, 1, 0, '#3B82F6'),
('Medical Leave',   'ML',  14, 1, 1, '#EF4444'),
('Emergency Leave', 'EML',  3, 1, 0, '#F59E0B'),
('Unpaid Leave',    'UL',  30, 0, 0, '#6B7280');

-- Employee warnings / disciplinary records
CREATE TABLE IF NOT EXISTS employee_warnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    warning_type ENUM('verbal','written','final','suspension','counselling') NOT NULL DEFAULT 'verbal',
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    issued_by INT NULL,
    issued_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES employees(id) ON DELETE SET NULL
);

SELECT 'Migration complete.' AS status;
