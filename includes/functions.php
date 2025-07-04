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

// Client authentication functions
function generateClientPIN() {
    return str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}

function hashClientPIN($pin) {
    return password_hash($pin, PASSWORD_DEFAULT);
}

function verifyClientPIN($pin, $hash) {
    return password_verify($pin, $hash);
}

function isClientLoggedIn() {
    return isset($_SESSION['client_id']) && isset($_SESSION['client_email']);
}

function requireClientLogin() {
    if (!isClientLoggedIn()) {
        header('Location: /client/login.php');
        exit;
    }
}

function logClientActivity($pdo, $customer_id, $action, $description = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO client_activity (customer_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $customer_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log client activity: " . $e->getMessage());
    }
}

function getClientByEmail($pdo, $email) {
    $stmt = $pdo->prepare("
        SELECT c.*, ca.pin, ca.login_attempts, ca.locked_until, ca.is_active
        FROM customers c
        JOIN client_auth ca ON c.id = ca.customer_id
        WHERE ca.email = ?
    ");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function createClientAuth($pdo, $customer_id, $email, $pin) {
    $hashedPin = hashClientPIN($pin);
    $stmt = $pdo->prepare("INSERT INTO client_auth (customer_id, email, pin) VALUES (?, ?, ?)");
    return $stmt->execute([$customer_id, $email, $hashedPin]);
}

function updateClientLastLogin($pdo, $customer_id) {
    $stmt = $pdo->prepare("UPDATE client_auth SET last_login = CURRENT_TIMESTAMP, login_attempts = 0 WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
}

function incrementLoginAttempts($pdo, $email) {
    $stmt = $pdo->prepare("UPDATE client_auth SET login_attempts = login_attempts + 1 WHERE email = ?");
    $stmt->execute([$email]);
}

function lockClientAccount($pdo, $email, $minutes = 30) {
    $stmt = $pdo->prepare("UPDATE client_auth SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE email = ?");
    $stmt->execute([$minutes, $email]);
}

function generateRememberToken($pdo, $customer_id) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $pdo->prepare("UPDATE client_auth SET remember_token = ?, remember_expires = ? WHERE customer_id = ?");
    $stmt->execute([$token, $expires, $customer_id]);
    
    return $token;
}

function validateRememberToken($pdo, $token) {
    $stmt = $pdo->prepare("
        SELECT c.*, ca.customer_id
        FROM client_auth ca
        JOIN customers c ON c.id = ca.customer_id
        WHERE ca.remember_token = ? AND ca.remember_expires > NOW() AND ca.is_active = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}