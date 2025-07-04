<?php
// Define security constant for config loader
define('SECURE_CONFIG_LOADER', true);
require_once 'config_loader.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                   DB_USER, 
                   DB_PASS);
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Log the actual error securely
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [CRITICAL] Database connection failed: " . $e->getMessage() . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    
    // Log to file
    $logFile = $logDir . '/db_errors_' . date('Y-m-d') . '.log';
    @error_log($logEntry, 3, $logFile);
    
    // Show generic message to user
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die("Database connection failed. Check error logs for details. Error: " . $e->getMessage());
    } else {
        die("Database temporarily unavailable. Please try again later or contact support.");
    }
}