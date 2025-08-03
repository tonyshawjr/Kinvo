<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

$customerId = validateInteger($_GET['customer_id'] ?? '', 'Customer ID', true, 1);

try {
    $stmt = $pdo->prepare("
        SELECT id, property_name, property_type, address 
        FROM customer_properties 
        WHERE customer_id = ? AND is_active = 1 
        ORDER BY property_name
    ");
    $stmt->execute([$customerId]);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['properties' => $properties]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load properties']);
}