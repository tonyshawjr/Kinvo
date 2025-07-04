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
        // Get current year and month
        $currentYear = date('Y');
        $currentMonth = date('m');
        $yearMonthPrefix = $currentYear . $currentMonth;
        
        // Get all existing invoice numbers for the current month/year
        $stmt = $pdo->query("
            SELECT invoice_number 
            FROM invoices 
            WHERE invoice_number IS NOT NULL 
            AND invoice_number LIKE '{$yearMonthPrefix}%'
            ORDER BY invoice_number DESC
        ");
        $invoices = $stmt->fetchAll();
        
        $maxIncrement = 0;
        
        foreach ($invoices as $invoice) {
            $invoiceNum = $invoice['invoice_number'];
            
            // Extract increment from YYYYMM## format
            if (preg_match('/^' . preg_quote($yearMonthPrefix) . '(\d+)$/', $invoiceNum, $matches)) {
                $maxIncrement = max($maxIncrement, intval($matches[1]));
            }
        }
        
        // Increment to get next number
        $nextIncrement = $maxIncrement + 1;
        
        return $yearMonthPrefix . $nextIncrement;
        
    } catch (Exception $e) {
        // Fallback: use current year/month with timestamp
        return date('Ym') . date('His');
    }
}

function generateUniqueId() {
    return md5(uniqid(rand(), true));
}

function verifyAdminPassword($password, $pdo) {
    try {
        // First, try to get password from database
        $stmt = $pdo->query("SELECT admin_password FROM business_settings LIMIT 1");
        $result = $stmt->fetch();
        
        if ($result && !empty($result['admin_password'])) {
            // Database password exists, verify against it
            return password_verify($password, $result['admin_password']);
        } else {
            // Fall back to config file password
            return $password === ADMIN_PASSWORD;
        }
    } catch (Exception $e) {
        // If database error, fall back to config file
        return $password === ADMIN_PASSWORD;
    }
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