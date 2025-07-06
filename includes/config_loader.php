<?php
/**
 * Secure Configuration Loader
 * This file loads configuration from outside the web root for security
 */

// Prevent direct access
if (!defined('SECURE_CONFIG_LOADER')) {
    die('Direct access not allowed');
}

// Try multiple config locations in order of preference
$configPaths = [
    __DIR__ . '/../../SimpleInvoice_Config/config.php',  // Secure location (outside web root)
    __DIR__ . '/config.php',                             // Local fallback
    __DIR__ . '/../config.php'                           // Alternative fallback
];

$configLoaded = false;

foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    // Create a detailed error message for debugging
    $attemptedPaths = implode('<br>- ', $configPaths);
    die("Configuration file not found. Attempted paths:<br>- $attemptedPaths<br><br>Please run the installer or contact support.");
}