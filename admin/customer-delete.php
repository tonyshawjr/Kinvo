<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$customerId = $_GET['id'] ?? null;

if (!$customerId) {
    header('Location: customers.php');
    exit;
}

// Verify customer exists and access is authorized
requireResourceOwnership($pdo, 'customer', $customerId);

// Get customer information
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Check if customer has any invoices
$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ?");
$stmt->execute([$customerId]);
$invoiceCount = $stmt->fetchColumn();

if ($invoiceCount > 0) {
    // Customer has invoices, cannot delete
    header('Location: customer-detail.php?id=' . $customerId . '&error=cannot_delete');
    exit;
}

// If we get here, customer can be safely deleted
try {
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    
    // Redirect to customers list with success message
    header('Location: customers.php?deleted=1&name=' . urlencode($customer['name']));
    exit;
    
} catch (Exception $e) {
    // Error deleting customer
    header('Location: customer-detail.php?id=' . $customerId . '&error=delete_failed');
    exit;
}
?>