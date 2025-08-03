<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

requireAdmin();

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM estimates");
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => (int)$count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get estimates count']);
}