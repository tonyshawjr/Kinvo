<?php
session_start();
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(false, true);

// Log logout activity if user is logged in
if (isClientLoggedIn()) {
    logClientActivity($pdo, $_SESSION['client_id'], 'logout', 'User logged out');
}

// Clear session
session_destroy();

// Clear remember token cookie
if (isset($_COOKIE['client_remember'])) {
    setcookie('client_remember', '', time() - 3600, '/client/');
}

// Redirect to login
header('Location: /client/login.php');
exit;
?>