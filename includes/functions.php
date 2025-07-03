<?php

function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function generateInvoiceNumber($pdo) {
    try {
        // Get all existing invoice numbers to find the highest
        $stmt = $pdo->query("
            SELECT invoice_number 
            FROM invoices 
            WHERE invoice_number IS NOT NULL 
            ORDER BY id DESC
        ");
        $invoices = $stmt->fetchAll();
        
        $maxNum = 0;
        
        foreach ($invoices as $invoice) {
            $invoiceNum = $invoice['invoice_number'];
            
            // Extract number from various formats
            if (preg_match('/INV-(\d+)/', $invoiceNum, $matches)) {
                $maxNum = max($maxNum, intval($matches[1]));
            } elseif (preg_match('/^(\d+)$/', $invoiceNum, $matches)) {
                $maxNum = max($maxNum, intval($matches[1]));
            } elseif (preg_match('/\d+/', $invoiceNum, $matches)) {
                $maxNum = max($maxNum, intval($matches[0]));
            }
        }
        
        // Increment to get next number
        $nextNum = $maxNum + 1;
        
        return sprintf('INV-%04d', $nextNum);
        
    } catch (Exception $e) {
        // Fallback: use current timestamp
        return 'INV-' . date('ymdHis');
    }
}

function generateUniqueId() {
    return md5(uniqid(rand(), true));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getInvoiceStatus($invoice, $pdo) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoice['id']]);
    $totalPaid = $stmt->fetchColumn();
    
    if ($totalPaid >= $invoice['total']) {
        return 'Paid';
    } elseif ($totalPaid > 0) {
        return 'Partial';
    } else {
        return 'Unpaid';
    }
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'Paid':
            return 'bg-green-100 text-green-800';
        case 'Partial':
            return 'bg-yellow-100 text-yellow-800';
        case 'Unpaid':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getBusinessSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM business_settings LIMIT 1");
    return $stmt->fetch() ?: [
        'business_name' => 'Your Business Name',
        'business_phone' => '910-XXX-XXXX',
        'business_email' => 'youremail@example.com',
        'business_ein' => '',
        'cashapp_username' => '',
        'venmo_username' => '',
        'default_hourly_rate' => '45.00',
        'mileage_rate' => '0.650',
        'payment_instructions' => 'Pay via Zelle to 910-XXX-XXXX'
    ];
}