<?php
/**
 * Kinvo Configuration File - Example
 * Copy this file to config.php and update with your settings
 * Or run the installer at /install.php
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Site Configuration
define('SITE_NAME', 'Kinvo');
define('SITE_URL', 'https://yourdomain.com');
define('ADMIN_PASSWORD', 'deprecated'); // Now stored in database

// Error Reporting (set to 0 in production)
error_reporting(0);
ini_set('display_errors', 0);

// Timezone
date_default_timezone_set('America/New_York');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}