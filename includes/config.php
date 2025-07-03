<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'simpleinvoice');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Admin password (change this!)
define('ADMIN_PASSWORD', 'Ts5h7a2w6!');

// Site settings
define('SITE_NAME', 'Kinvo');
define('SITE_URL', 'https://localhost'); // Update this with your actual domain

// Timezone
date_default_timezone_set('America/New_York');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for admin authentication
session_start();