-- Database schema for Kinvo - Business Management System

-- Create database (optional, if you need to create it)
-- CREATE DATABASE business_manager;
-- USE business_manager;

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    custom_hourly_rate DECIMAL(8,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_number VARCHAR(20) UNIQUE,
    date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Unpaid', 'Partial', 'Paid') DEFAULT 'Unpaid',
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5, 2) DEFAULT 0,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    notes TEXT,
    unique_id VARCHAR(32) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Invoice items table
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    method ENUM('Cash', 'Check', 'Credit Card', 'Bank Transfer', 'Cash App', 'Venmo', 'Zelle', 'PayPal', 'Other') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

-- Customer properties table (for AirBnB properties, job sites, etc.)
CREATE TABLE IF NOT EXISTS customer_properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    property_name VARCHAR(255) NOT NULL,
    address TEXT,
    property_type ENUM('AirBnB', 'Personal Home', 'Rental Property', 'Business', 'Other') DEFAULT 'AirBnB',
    notes TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Add property_id to invoices table for tracking work location
ALTER TABLE invoices ADD COLUMN property_id INT NULL AFTER customer_id;
ALTER TABLE invoices ADD FOREIGN KEY (property_id) REFERENCES customer_properties(id) ON DELETE SET NULL;

-- Business settings table (optional)
CREATE TABLE IF NOT EXISTS business_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255),
    business_phone VARCHAR(20),
    business_email VARCHAR(255),
    business_ein VARCHAR(20),
    cashapp_username VARCHAR(50),
    venmo_username VARCHAR(50),
    default_hourly_rate DECIMAL(8,2) DEFAULT 45.00,
    mileage_rate DECIMAL(5,3) DEFAULT 0.650,
    payment_instructions TEXT,
    admin_password VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Business settings will be populated by the installation wizard

-- CLIENT PORTAL TABLES
-- ===================

-- Client authentication table
CREATE TABLE IF NOT EXISTS client_auth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    pin VARCHAR(255) NOT NULL, -- Hashed 4-digit PIN
    pin_reset_token VARCHAR(64) NULL,
    pin_reset_expires DATETIME NULL,
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    remember_token VARCHAR(64) NULL,
    remember_expires TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Client activity log
CREATE TABLE IF NOT EXISTS client_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Client documents table
CREATE TABLE IF NOT EXISTS client_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by ENUM('client', 'admin') DEFAULT 'client',
    is_visible_to_client BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Client payment methods (for quick access)
CREATE TABLE IF NOT EXISTS client_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    method_type ENUM('Cash App', 'Venmo', 'Zelle', 'PayPal', 'Bank Transfer', 'Other') NOT NULL,
    account_info VARCHAR(255), -- Username or last 4 digits, etc.
    is_preferred BOOLEAN DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Client preferences
CREATE TABLE IF NOT EXISTS client_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    email_notifications BOOLEAN DEFAULT 1,
    sms_notifications BOOLEAN DEFAULT 0,
    invoice_reminders BOOLEAN DEFAULT 1,
    payment_confirmations BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Add indexes for performance
CREATE INDEX idx_client_auth_email ON client_auth(email);
CREATE INDEX idx_client_auth_customer ON client_auth(customer_id);
CREATE INDEX idx_client_activity_customer ON client_activity(customer_id);
CREATE INDEX idx_client_activity_date ON client_activity(created_at);
CREATE INDEX idx_client_documents_customer ON client_documents(customer_id);
CREATE INDEX idx_client_documents_invoice ON client_documents(invoice_id);
CREATE INDEX idx_client_payment_methods_customer ON client_payment_methods(customer_id);
CREATE INDEX idx_client_preferences_customer ON client_preferences(customer_id);