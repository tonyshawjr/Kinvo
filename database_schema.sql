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
    method ENUM('Zelle', 'Venmo', 'Cash App', 'Cash', 'Check') NOT NULL,
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default business settings
INSERT INTO business_settings (business_name, business_phone, business_email, business_ein, cashapp_username, venmo_username, default_hourly_rate, mileage_rate, payment_instructions) 
VALUES ('Your Business Name', '910-XXX-XXXX', 'youremail@example.com', '', '', '', 45.00, 0.650, 'Pay via Zelle to 910-XXX-XXXX or Venmo @yourusername')
ON DUPLICATE KEY UPDATE id=id;