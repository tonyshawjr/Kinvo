<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

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