<?php
/**
 * Kinvo - Professional Invoice Management System
 * Main entry point
 */

// Check if installed
if (!file_exists('includes/config.php') || !file_exists('includes/.installed')) {
    // Redirect to installer
    header('Location: install.php');
    exit;
}

// If installed, redirect to admin login (not dashboard, as user needs to login first)
header('Location: admin/login.php');
exit;