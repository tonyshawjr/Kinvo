<?php
/**
 * Secure Configuration Loader
 * This file loads configuration from outside the web root for security
 */

// Prevent direct access
if (!defined('SECURE_CONFIG_LOADER')) {
    die('Direct access not allowed');
}

// Define the secure config path (outside web root)
$secureConfigPath = __DIR__ . '/../../SimpleInvoice_Config/config.php';

// Verify the secure config file exists
if (!file_exists($secureConfigPath)) {
    // Fallback to local config if secure config doesn't exist (for backward compatibility)
    $fallbackConfigPath = __DIR__ . '/config.php';
    if (file_exists($fallbackConfigPath)) {
        require_once $fallbackConfigPath;
        return;
    }
    
    // If neither exists, show error
    die('Configuration file not found. Please run the installer or contact support.');
}

// Load the secure configuration
require_once $secureConfigPath;