<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/service-functions.php';

requireAdmin();

header('Content-Type: application/json');

$customerId = $_GET['customer_id'] ?? null;
$propertyId = !empty($_GET['property_id']) ? $_GET['property_id'] : null;

if (!$customerId || !ctype_digit((string) $customerId)) {
    echo json_encode(['found' => false]);
    exit;
}

requireResourceOwnership($pdo, 'customer', $customerId);

try {
    $invoice = getLastInvoiceForProperty($pdo, $customerId, $propertyId);

    if (!$invoice || empty($invoice['items'])) {
        echo json_encode(['found' => false]);
        exit;
    }

    $items = [];
    foreach ($invoice['items'] as $item) {
        $items[] = [
            'description' => $item['description'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
        ];
    }

    echo json_encode([
        'found' => true,
        'invoice_number' => $invoice['invoice_number'],
        'date' => $invoice['date'],
        'date_label' => date('M j, Y', strtotime($invoice['date'])),
        'total' => (float) $invoice['total'],
        'items' => $items,
    ]);
} catch (Exception $e) {
    echo json_encode(['found' => false]);
}
