-- Jewellery ERP Management System - Database Schema
-- Database: jewellery_erp

CREATE DATABASE IF NOT EXISTS jewellery_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jewellery_erp;

-- ============================================================
-- 1. SETTINGS
-- ============================================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_group (setting_group)
) ENGINE=InnoDB;

-- Default settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('shop_name', 'Jewellery Shop', 'business'),
('shop_address', '123 Main Street, City', 'business'),
('shop_phone', '9876543210', 'business'),
('shop_email', 'info@jewelleryshop.com', 'business'),
('shop_gstin', '', 'business'),
('shop_logo', '', 'business'),
('currency_symbol', '₹', 'general'),
('gst_rate', '3', 'tax'),
('gst_rate_making', '5', 'tax'),
('invoice_prefix', 'INV', 'billing'),
('invoice_start_number', '1001', 'billing'),
('low_stock_threshold', '5', 'inventory'),
('session_timeout', '30', 'security'),
('date_format', 'd-m-Y', 'general'),
('fiscal_year_start', '04', 'finance');

-- ============================================================
-- 2. USERS / EMPLOYEES
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    employee_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) DEFAULT '',
    email VARCHAR(150) DEFAULT '',
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    designation VARCHAR(100) DEFAULT '',
    department VARCHAR(100) DEFAULT '',
    joining_date DATE DEFAULT NULL,
    salary DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    photo VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_status (status),
    INDEX idx_name (first_name, last_name)
) ENGINE=InnoDB;

-- Link users to employees
ALTER TABLE users ADD FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL;

-- Default admin user (password: admin123)
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$12$OzGO5B8QfilmyPgaGWMwzOqyiKdAwB8V6JbXIdOhr/0OR2hSaaGhS', 'admin');

-- ============================================================
-- 3. CUSTOMERS
-- ============================================================
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) DEFAULT '',
    address TEXT,
    city VARCHAR(100) DEFAULT '',
    state VARCHAR(100) DEFAULT '',
    pincode VARCHAR(10) DEFAULT '',
    gstin VARCHAR(20) DEFAULT '',
    pan VARCHAR(15) DEFAULT '',
    total_purchases DECIMAL(14,2) DEFAULT 0.00,
    outstanding_balance DECIMAL(14,2) DEFAULT 0.00,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_name (name),
    INDEX idx_gstin (gstin),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- 4. CATEGORIES & SUPPLIERS
-- ============================================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    metal_type ENUM('gold', 'silver', 'diamond', 'platinum', 'other') DEFAULT 'gold',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_metal_type (metal_type)
) ENGINE=InnoDB;

INSERT INTO categories (name, metal_type) VALUES
('Gold Necklace', 'gold'),
('Gold Ring', 'gold'),
('Gold Bangle', 'gold'),
('Gold Earring', 'gold'),
('Gold Chain', 'gold'),
('Gold Pendant', 'gold'),
('Gold Bracelet', 'gold'),
('Silver Necklace', 'silver'),
('Silver Ring', 'silver'),
('Silver Bangle', 'silver'),
('Diamond Ring', 'diamond'),
('Diamond Necklace', 'diamond'),
('Diamond Earring', 'diamond'),
('Platinum Ring', 'platinum'),
('Platinum Chain', 'platinum');

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    company VARCHAR(200) DEFAULT '',
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) DEFAULT '',
    address TEXT,
    gstin VARCHAR(20) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone)
) ENGINE=InnoDB;

-- ============================================================
-- 5. METAL RATES
-- ============================================================
CREATE TABLE metal_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metal_type ENUM('gold_24k', 'gold_22k', 'gold_18k', 'silver_999', 'silver_925', 'platinum', 'diamond') NOT NULL,
    rate_per_gram DECIMAL(10,2) NOT NULL,
    effective_date DATE NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_metal_date (metal_type, effective_date),
    INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB;

-- Default rates
INSERT INTO metal_rates (metal_type, rate_per_gram, effective_date) VALUES
('gold_24k', 7500.00, CURDATE()),
('gold_22k', 6900.00, CURDATE()),
('gold_18k', 5625.00, CURDATE()),
('silver_999', 95.00, CURDATE()),
('silver_925', 88.00, CURDATE()),
('platinum', 3200.00, CURDATE());

-- ============================================================
-- 6. PRODUCTS
-- ============================================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) DEFAULT '',
    name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    metal_type ENUM('gold', 'silver', 'diamond', 'platinum', 'other') DEFAULT 'gold',
    purity VARCHAR(20) DEFAULT '22K',
    gross_weight DECIMAL(10,3) DEFAULT 0.000,
    net_weight DECIMAL(10,3) DEFAULT 0.000,
    stone_weight DECIMAL(10,3) DEFAULT 0.000,
    stone_charges DECIMAL(10,2) DEFAULT 0.00,
    making_charges_type ENUM('per_gram', 'percentage', 'fixed') DEFAULT 'per_gram',
    making_charges DECIMAL(10,2) DEFAULT 0.00,
    wastage_percentage DECIMAL(5,2) DEFAULT 0.00,
    purchase_price DECIMAL(12,2) DEFAULT 0.00,
    selling_price DECIMAL(12,2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 1,
    min_stock_level INT DEFAULT 1,
    supplier_id INT DEFAULT NULL,
    description TEXT,
    image VARCHAR(255) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_product_code (product_code),
    INDEX idx_barcode (barcode),
    INDEX idx_name (name),
    INDEX idx_category (category_id),
    INDEX idx_metal_type (metal_type),
    INDEX idx_stock (stock_quantity),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- 7. INVENTORY TRANSACTIONS
-- ============================================================
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'sale', 'return', 'adjustment', 'cancel_reversal') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT DEFAULT 0,
    new_stock INT DEFAULT 0,
    reference_type VARCHAR(50) DEFAULT '',
    reference_id INT DEFAULT NULL,
    notes TEXT,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_type (transaction_type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 8. INVOICES
-- ============================================================
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_type ENUM('tax_invoice', 'estimate') DEFAULT 'tax_invoice',
    invoice_date DATE NOT NULL,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(200) DEFAULT '',
    customer_phone VARCHAR(20) DEFAULT '',
    customer_address TEXT,
    customer_gstin VARCHAR(20) DEFAULT '',
    
    -- Rate used in this invoice
    gold_rate_24k DECIMAL(10,2) DEFAULT 0.00,
    gold_rate_22k DECIMAL(10,2) DEFAULT 0.00,
    gold_rate_18k DECIMAL(10,2) DEFAULT 0.00,
    silver_rate DECIMAL(10,2) DEFAULT 0.00,
    
    -- Totals
    subtotal DECIMAL(14,2) DEFAULT 0.00,
    total_making_charges DECIMAL(12,2) DEFAULT 0.00,
    total_stone_charges DECIMAL(12,2) DEFAULT 0.00,
    total_wastage_charges DECIMAL(12,2) DEFAULT 0.00,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    taxable_amount DECIMAL(14,2) DEFAULT 0.00,
    cgst_rate DECIMAL(5,2) DEFAULT 1.50,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_rate DECIMAL(5,2) DEFAULT 1.50,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_rate DECIMAL(5,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    total_tax DECIMAL(12,2) DEFAULT 0.00,
    grand_total DECIMAL(14,2) DEFAULT 0.00,
    round_off DECIMAL(5,2) DEFAULT 0.00,
    final_amount DECIMAL(14,2) DEFAULT 0.00,
    amount_in_words VARCHAR(500) DEFAULT '',
    
    -- Payment
    paid_amount DECIMAL(14,2) DEFAULT 0.00,
    due_amount DECIMAL(14,2) DEFAULT 0.00,
    payment_status ENUM('paid', 'partial', 'unpaid') DEFAULT 'unpaid',
    
    -- Status
    status ENUM('draft', 'finalized', 'cancelled') DEFAULT 'draft',
    cancel_reason TEXT,
    cancelled_at DATETIME DEFAULT NULL,
    cancelled_by INT DEFAULT NULL,
    
    notes TEXT,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_customer_id (customer_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 9. INVOICE ITEMS
-- ============================================================
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_code VARCHAR(50) DEFAULT '',
    product_name VARCHAR(200) NOT NULL,
    category VARCHAR(100) DEFAULT '',
    metal_type VARCHAR(20) DEFAULT '',
    purity VARCHAR(20) DEFAULT '',
    hsn_code VARCHAR(20) DEFAULT '7113',
    quantity INT DEFAULT 1,
    gross_weight DECIMAL(10,3) DEFAULT 0.000,
    net_weight DECIMAL(10,3) DEFAULT 0.000,
    stone_weight DECIMAL(10,3) DEFAULT 0.000,
    rate_per_gram DECIMAL(10,2) DEFAULT 0.00,
    metal_value DECIMAL(12,2) DEFAULT 0.00,
    making_charges_type ENUM('per_gram', 'percentage', 'fixed') DEFAULT 'per_gram',
    making_charges_rate DECIMAL(10,2) DEFAULT 0.00,
    making_charges DECIMAL(12,2) DEFAULT 0.00,
    wastage_percentage DECIMAL(5,2) DEFAULT 0.00,
    wastage_charges DECIMAL(12,2) DEFAULT 0.00,
    stone_charges DECIMAL(10,2) DEFAULT 0.00,
    item_total DECIMAL(12,2) DEFAULT 0.00,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. PAYMENTS
-- ============================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT '',
    notes TEXT,
    is_advance TINYINT(1) DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_method (payment_method)
) ENGINE=InnoDB;

-- ============================================================
-- 11. EXPENSE CATEGORIES & EXPENSES
-- ============================================================
CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

INSERT INTO expense_categories (name) VALUES
('Rent'),
('Salary'),
('Electricity'),
('Maintenance'),
('Purchase'),
('Transport'),
('Office Supplies'),
('Marketing'),
('Insurance'),
('Other');

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT '',
    description TEXT,
    receipt_image VARCHAR(255) DEFAULT '',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_expense_date (expense_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 12. REVENUE (Non-sales revenue)
-- ============================================================
CREATE TABLE revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    revenue_date DATE NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash',
    reference_number VARCHAR(100) DEFAULT '',
    description TEXT,
    is_advance TINYINT(1) DEFAULT 0,
    customer_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_revenue_date (revenue_date),
    INDEX idx_source (source)
) ENGINE=InnoDB;

-- ============================================================
-- 13. ATTENDANCE
-- ============================================================
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'half_day', 'leave') DEFAULT 'present',
    check_in TIME DEFAULT NULL,
    check_out TIME DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_employee_date (employee_id, attendance_date)
) ENGINE=InnoDB;

-- ============================================================
-- 14. AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT '',
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_module (module),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 15. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;



-- ============================================================
-- 16. DEMO SEED DATA
-- ============================================================

-- Employees
INSERT INTO employees (id, first_name, last_name, email, phone, address, designation, department, joining_date, salary, status) VALUES
(1, 'Admin', 'User', 'admin@jewelleryshop.com', '9876543210', '123 Market Road, City', 'Store Manager', 'Management', '2023-01-01', 50000.00, 'active'),
(2, 'Rahul', 'Sharma', 'rahul@jewelleryshop.com', '9876543211', '45 MG Road, City', 'Senior Sales Executive', 'Sales', '2023-03-15', 30000.00, 'active'),
(3, 'Pooja', 'Verma', 'pooja@jewelleryshop.com', '9876543212', '78 Ring Road, City', 'Cashier & Accounts', 'Accounts', '2023-06-01', 25000.00, 'active');

-- Link default admin user with employee #1
UPDATE users SET employee_id = 1 WHERE id = 1;

-- Add employee user (username: employee, password: password123)
INSERT INTO users (username, password, role, employee_id, is_active) VALUES
('employee', '$2y$12$alo.3xJmnJJEeCF8iB9gGu8PTI1QhG6qXGoEely0zG2aosQxoF7Eu', 'employee', 2, 1);

-- Suppliers
INSERT INTO suppliers (id, name, company, phone, email, address, gstin) VALUES
(1, 'Vikram Mehta', 'Swarna Bullion Merchants', '9822011223', 'swarna@bullion.com', 'Bullion Market, Zaveri Bazaar, Mumbai', '27AABCS1429B1ZB'),
(2, 'Dinesh Shah', 'Shanti Diamond Wholesalers', '9822033445', 'info@shantidiamond.com', 'Opera House, Surat', '24AABCS5521C1ZC'),
(3, 'Rajesh Agarwal', 'Silver Art Creations', '9822055667', 'sales@silverart.com', 'Silver Street, Rajkot', '24AABCS9912D1ZD');

-- Customers
INSERT INTO customers (id, name, phone, email, address, city, state, pincode, gstin, total_purchases, outstanding_balance) VALUES
(1, 'Priya Patel', '9898012345', 'priya.patel@gmail.com', 'A-102, Gokul Heights, Satellite', 'Ahmedabad', 'Gujarat', '380015', '', 145000.00, 0.00),
(2, 'Rajesh Kumar', '9876501234', 'rajesh.kumar@outlook.com', '14 Sunrise Apartments, Indiranagar', 'Bengaluru', 'Karnataka', '560038', '29ABCDE1234F1Z5', 210500.00, 15000.00),
(3, 'Sunita Sharma', '9811122334', 'sunita.sharma@yahoo.com', '452 Sector 21', 'Chandigarh', 'Punjab', '160022', '', 88000.00, 0.00),
(4, 'Amit Joshi', '9922334455', 'amit.joshi@gmail.com', 'B-12, Laxmi Nagar', 'Pune', 'Maharashtra', '411030', '', 320000.00, 45000.00),
(5, 'Neha Singhal', '9711223344', 'neha.singhal@hotmail.com', 'C-44, Civil Lines', 'Jaipur', 'Rajasthan', '302006', '', 65000.00, 0.00);

-- Products
INSERT INTO products (id, product_code, barcode, name, category_id, metal_type, purity, gross_weight, net_weight, stone_weight, stone_charges, making_charges_type, making_charges, wastage_percentage, purchase_price, selling_price, stock_quantity, min_stock_level, supplier_id, description) VALUES
(1, 'GN-001', '8901234001', 'Traditional Royal Gold Necklace', 1, 'gold', '22K', 28.500, 27.200, 1.300, 4500.00, 'per_gram', 450.00, 2.50, 175000.00, 215000.00, 4, 2, 1, 'Exquisite handcrafted bridal gold necklace in 22 Karat gold with subtle ruby highlights.'),
(2, 'GR-002', '8901234002', 'Floral Pattern 22K Gold Ring', 2, 'gold', '22K', 6.200, 6.200, 0.000, 0.00, 'per_gram', 400.00, 2.00, 38000.00, 47500.00, 8, 3, 1, 'Elegant floral daily-wear gold ring for women.'),
(3, 'GB-003', '8901234003', 'Kada Style Gold Bangle (Pair)', 3, 'gold', '22K', 42.000, 42.000, 0.000, 0.00, 'per_gram', 380.00, 2.00, 260000.00, 320000.00, 3, 2, 1, 'Solid heavy finish pair of gold kadas with fine laser engraving.'),
(4, 'GE-004', '8901234004', 'Peacock Jhumka Gold Earrings', 4, 'gold', '22K', 14.800, 14.200, 0.600, 2000.00, 'per_gram', 450.00, 2.50, 92000.00, 115000.00, 5, 2, 1, 'Classic peacock motif jhumkas with hanging pearl drops.'),
(5, 'GC-005', '8901234005', 'Men Solid Rope Gold Chain', 5, 'gold', '22K', 18.500, 18.500, 0.000, 0.00, 'per_gram', 350.00, 1.50, 118000.00, 142000.00, 6, 2, 1, 'Durable Italian rope design gold chain suitable for daily wear.'),
(6, 'DR-006', '8901234006', 'Solitaire Diamond Engagement Ring', 11, 'diamond', '18K', 4.500, 4.300, 0.200, 35000.00, 'fixed', 5000.00, 0.00, 52000.00, 72000.00, 3, 1, 2, 'Certified VVS-EF 0.50 ct solitaire diamond ring mounted on 18K white gold.'),
(7, 'DN-007', '8901234007', 'Diamond Cascade Tennis Necklace', 12, 'diamond', '18K', 32.000, 29.500, 2.500, 120000.00, 'fixed', 15000.00, 0.00, 280000.00, 385000.00, 2, 1, 2, 'Signature bridal diamond necklace with 4.2 carats total diamond weight.'),
(8, 'SR-008', '8901234008', 'Oxidised Silver Antique Ring', 9, 'silver', '925 Silver', 8.500, 8.500, 0.000, 0.00, 'fixed', 350.00, 0.00, 950.00, 1650.00, 15, 5, 3, 'Tribal motifs carved 925 sterling silver adjustable ring.'),
(9, 'SB-009', '8901234009', 'Pure Silver Pooja Plate (Thali)', 10, 'silver', '999 Silver', 250.000, 250.000, 0.000, 0.00, 'per_gram', 15.00, 1.00, 23000.00, 29500.00, 4, 2, 3, 'Auspicious pure 999 silver engraved religious thali for gifting and ceremonies.'),
(10, 'PR-010', '8901234010', 'Platinum & Rose Gold Couple Bands', 14, 'platinum', '950 Platinum', 11.200, 11.200, 0.000, 0.00, 'per_gram', 650.00, 0.00, 44000.00, 58000.00, 2, 1, 2, 'Contemporary twin-tone platinum band set with subtle satin brush finish.');

-- Initial Stock Transactions
INSERT INTO inventory_transactions (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes) VALUES
(1, 'stock_in', 5, 0, 5, 'initial_stock', 'Initial opening stock entry'),
(2, 'stock_in', 10, 0, 10, 'initial_stock', 'Initial opening stock entry'),
(3, 'stock_in', 4, 0, 4, 'initial_stock', 'Initial opening stock entry'),
(4, 'stock_in', 6, 0, 6, 'initial_stock', 'Initial opening stock entry'),
(5, 'stock_in', 7, 0, 7, 'initial_stock', 'Initial opening stock entry'),
(6, 'stock_in', 4, 0, 4, 'initial_stock', 'Initial opening stock entry'),
(7, 'stock_in', 2, 0, 2, 'initial_stock', 'Initial opening stock entry'),
(8, 'stock_in', 15, 0, 15, 'initial_stock', 'Initial opening stock entry'),
(9, 'stock_in', 4, 0, 4, 'initial_stock', 'Initial opening stock entry'),
(10, 'stock_in', 2, 0, 2, 'initial_stock', 'Initial opening stock entry');

-- Sample Completed Invoice #1
INSERT INTO invoices (id, invoice_number, invoice_date, customer_id, customer_name, customer_phone, customer_address, 
                      gold_rate_24k, gold_rate_22k, gold_rate_18k, silver_rate,
                      subtotal, total_making_charges, total_stone_charges, total_wastage_charges,
                      discount_type, discount_value, discount_amount, taxable_amount,
                      cgst_rate, cgst_amount, sgst_rate, sgst_amount, igst_rate, igst_amount, total_tax,
                      grand_total, round_off, final_amount, amount_in_words,
                      paid_amount, due_amount, payment_status, status, notes, created_by)
VALUES (
    1, 'INV-001001', CURDATE(), 1, 'Priya Patel', '9898012345', 'A-102, Gokul Heights, Satellite, Ahmedabad',
    7500.00, 6900.00, 5625.00, 95.00,
    140760.00, 6390.00, 0.00, 2111.40,
    'fixed', 2000.00, 2000.00, 147261.40,
    1.50, 2208.92, 1.50, 2208.92, 0.00, 0.00, 4417.84,
    151679.24, -0.24, 151679.00, 'One Lakh Fifty One Thousand Six Hundred and Seventy Nine Rupees Only',
    151679.00, 0.00, 'paid', 'finalized', 'Thank you for your purchase!', 1
);

INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, category, metal_type, purity, hsn_code, quantity, gross_weight, net_weight, stone_weight, rate_per_gram, metal_value, making_charges_type, making_charges_rate, making_charges, wastage_percentage, wastage_charges, stone_charges, item_total)
VALUES (
    1, 2, 'GR-002', 'Floral Pattern 22K Gold Ring', 'Gold Ring', 'gold', '22K', '7113', 2, 12.400, 12.400, 0.000, 6900.00, 85560.00, 'per_gram', 400.00, 4960.00, 2.00, 1711.20, 0.00, 92231.20
);

INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by)
VALUES (1, CURDATE(), 151679.00, 'upi', 'UPI-REF-909281273', 'Full payment received via Google Pay', 1);

-- Sample Invoice #2 (Partial payment)
INSERT INTO invoices (id, invoice_number, invoice_date, customer_id, customer_name, customer_phone, customer_address, 
                      gold_rate_24k, gold_rate_22k, gold_rate_18k, silver_rate,
                      subtotal, total_making_charges, total_stone_charges, total_wastage_charges,
                      discount_type, discount_value, discount_amount, taxable_amount,
                      cgst_rate, cgst_amount, sgst_rate, sgst_amount, igst_rate, igst_amount, total_tax,
                      grand_total, round_off, final_amount, amount_in_words,
                      paid_amount, due_amount, payment_status, status, notes, created_by)
VALUES (
    2, 'INV-001002', CURDATE(), 2, 'Rajesh Kumar', '9876501234', '14 Sunrise Apartments, Indiranagar, Bengaluru',
    7500.00, 6900.00, 5625.00, 95.00,
    187680.00, 12240.00, 4500.00, 4692.00,
    'percentage', 2.00, 4182.24, 204929.76,
    1.50, 3073.95, 1.50, 3073.95, 0.00, 0.00, 6147.90,
    211077.66, 0.34, 211078.00, 'Two Lakh Eleven Thousand and Seventy Eight Rupees Only',
    196078.00, 15000.00, 'partial', 'finalized', 'Balance of ₹15,000 to be cleared next week.', 1
);

INSERT INTO invoice_items (invoice_id, product_id, product_code, product_name, category, metal_type, purity, hsn_code, quantity, gross_weight, net_weight, stone_weight, rate_per_gram, metal_value, making_charges_type, making_charges_rate, making_charges, wastage_percentage, wastage_charges, stone_charges, item_total)
VALUES (
    2, 1, 'GN-001', 'Traditional Royal Gold Necklace', 'Gold Necklace', 'gold', '22K', '7113', 1, 28.500, 27.200, 1.300, 6900.00, 187680.00, 'per_gram', 450.00, 12240.00, 2.50, 4692.00, 4500.00, 209112.00
);

INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by)
VALUES (2, CURDATE(), 196078.00, 'card', 'POS-TXN-449182', 'Partial card swipe payment', 1);

-- Sample Expenses
INSERT INTO expenses (category_id, amount, expense_date, payment_method, reference_number, description, created_by) VALUES
(1, 45000.00, CURDATE(), 'bank_transfer', 'NEFT-RENT-001', 'Shop monthly lease rent payment', 1),
(3, 8500.00, CURDATE(), 'upi', 'EB-BILL-4412', 'Showroom electricity and air conditioning bill', 1),
(4, 2500.00, CURDATE(), 'cash', 'VCH-091', 'Jewellery display lighting fixture repairs', 1),
(7, 1200.00, CURDATE(), 'cash', 'VCH-092', 'Velvet jewellery presentation boxes and packaging paper', 1);

-- Sample Attendance
INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, notes) VALUES
(1, CURDATE(), 'present', '09:30:00', '19:30:00', 'Regular shift'),
(2, CURDATE(), 'present', '09:45:00', '19:30:00', 'Regular shift'),
(3, CURDATE(), 'present', '10:00:00', '19:30:00', 'Regular shift');

-- Notifications
INSERT INTO notifications (user_id, title, message, type, is_read, link) VALUES
(NULL, 'Low Stock Warning', 'Product Solitaire Diamond Engagement Ring (DR-006) stock is at 3 units.', 'warning', 0, 'modules/inventory/index.php'),
(NULL, 'New Bill Generated', 'Invoice INV-001002 generated for Rajesh Kumar (Amount: ₹2,11,078.00).', 'info', 0, 'modules/bill-history/view.php?id=2');
