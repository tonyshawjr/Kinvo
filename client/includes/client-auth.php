<?php
/**
 * Client Authentication Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if client is logged in
 */
function isClientLoggedIn() {
    return isset($_SESSION['client_logged_in']) && 
           $_SESSION['client_logged_in'] === true && 
           isset($_SESSION['client_customer_id']);
}

/**
 * Require client login
 */
function requireClientLogin() {
    if (!isClientLoggedIn()) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Get current client customer ID
 */
function getClientCustomerId() {
    if (isClientLoggedIn()) {
        return $_SESSION['client_customer_id'];
    }
    return null;
}

/**
 * Get current client customer name
 */
function getClientCustomerName() {
    if (isClientLoggedIn() && isset($_SESSION['client_customer_name'])) {
        return $_SESSION['client_customer_name'];
    }
    return null;
}

/**
 * Client logout
 */
function clientLogout() {
    // Clear client session variables
    unset($_SESSION['client_logged_in']);
    unset($_SESSION['client_customer_id']);
    unset($_SESSION['client_customer_name']);
    
    // Redirect to login
    header('Location: login.php');
    exit;
}
?>