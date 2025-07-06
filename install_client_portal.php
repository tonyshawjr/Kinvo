<?php
/**
 * Client Portal Tables Installation
 * Run this file directly to install only the client portal tables
 */

require_once 'includes/config.php';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Installing Client Portal Tables...</h2>";
    
    // Create client_auth table
    echo "Creating client_auth table... ";
    $sql = "CREATE TABLE IF NOT EXISTS client_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        pin VARCHAR(255) NOT NULL,
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
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        INDEX idx_client_auth_email (email),
        INDEX idx_client_auth_customer (customer_id)
    )";
    $pdo->exec($sql);
    echo "✓<br>";
    
    // Create client_activity table
    echo "Creating client_activity table... ";
    $sql = "CREATE TABLE IF NOT EXISTS client_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        INDEX idx_client_activity_customer (customer_id),
        INDEX idx_client_activity_date (created_at)
    )";
    $pdo->exec($sql);
    echo "✓<br>";
    
    // Create client_documents table
    echo "Creating client_documents table... ";
    $sql = "CREATE TABLE IF NOT EXISTS client_documents (
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
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        INDEX idx_client_documents_customer (customer_id),
        INDEX idx_client_documents_invoice (invoice_id)
    )";
    $pdo->exec($sql);
    echo "✓<br>";
    
    // Create client_payment_methods table
    echo "Creating client_payment_methods table... ";
    $sql = "CREATE TABLE IF NOT EXISTS client_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        method_type ENUM('Cash App', 'Venmo', 'Zelle', 'PayPal', 'Bank Transfer', 'Other') NOT NULL,
        account_info VARCHAR(255),
        is_preferred BOOLEAN DEFAULT 0,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        INDEX idx_client_payment_methods_customer (customer_id)
    )";
    $pdo->exec($sql);
    echo "✓<br>";
    
    // Create client_preferences table
    echo "Creating client_preferences table... ";
    $sql = "CREATE TABLE IF NOT EXISTS client_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        email_notifications BOOLEAN DEFAULT 1,
        sms_notifications BOOLEAN DEFAULT 0,
        invoice_reminders BOOLEAN DEFAULT 1,
        payment_confirmations BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        INDEX idx_client_preferences_customer (customer_id)
    )";
    $pdo->exec($sql);
    echo "✓<br>";
    
    echo "<h3 style='color: green;'>✓ All client portal tables created successfully!</h3>";
    echo "<p>You can now continue with the installation process.</p>";
    echo "<p><a href='install.php?step=4'>Continue to Business Configuration</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Error creating tables:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>