<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

$customerId = $_GET['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode([]);
    exit;
}

// Add authorization check for customer ownership
requireResourceOwnership($pdo, 'customer', $customerId);

try {
    $stmt = $pdo->prepare("SELECT * FROM customer_properties WHERE customer_id = ? AND is_active = 1 ORDER BY property_name");
    $stmt->execute([$customerId]);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($properties);
} catch (Exception $e) {
    // Properties table might not exist yet
    echo json_encode([]);
}
?>