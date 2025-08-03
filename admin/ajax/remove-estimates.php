<?php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/estimate-functions.php';

requireAdmin();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['confirm']) || $input['confirm'] !== true) {
    http_response_code(400);
    echo json_encode(['error' => 'Confirmation required']);
    exit;
}

try {
    $result = removeEstimatesFeature($pdo);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to remove estimates feature']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}