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

function adminLogout($pdo) {
    // Clear remember token from database
    $stmt = $pdo->prepare("UPDATE business_settings SET remember_token = NULL, remember_expires = NULL");
    $stmt->execute();
    
    // Clear session
    session_unset();
    session_destroy();
    
    // Clear remember cookie
    setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
}

function generateInvoiceNumber($pdo) {
    try {
        // Get current year and month
        $currentYear = date('Y');
        $currentMonth = date('m');
        $yearMonthPrefix = $currentYear . $currentMonth;
        
        // Get all existing invoice numbers for the current month/year
        $stmt = $pdo->prepare("
            SELECT invoice_number 
            FROM invoices 
            WHERE invoice_number IS NOT NULL 
            AND invoice_number LIKE CONCAT(?, '%')
            ORDER BY invoice_number DESC
        ");
        $stmt->execute([$yearMonthPrefix]);
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
    // Use the new secure PIN generation
    return generateSecureClientPIN();
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

// CSRF Protection Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function requireCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

// Password strength validation functions
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

function validatePINStrength($pin) {
    $errors = [];
    
    if (strlen($pin) < 6) {
        $errors[] = 'PIN must be at least 6 digits long';
    }
    
    if (!preg_match('/^\d+$/', $pin)) {
        $errors[] = 'PIN must contain only numbers';
    }
    
    // Check for common weak patterns
    if (preg_match('/^(\d)\1+$/', $pin)) {
        $errors[] = 'PIN cannot be all the same digit';
    }
    
    if (preg_match('/^(123456|654321|111111|000000|999999)/', $pin)) {
        $errors[] = 'PIN cannot be a common pattern';
    }
    
    return $errors;
}

// Enhanced client PIN functions
function generateSecureClientPIN() {
    // Generate a 6-digit PIN that's not a common pattern
    do {
        $pin = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    } while (count(validatePINStrength($pin)) > 0);
    
    return $pin;
}

// Authorization and Access Control Functions
function requireResourceOwnership($pdo, $resourceType, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        http_response_code(400);
        die('Invalid resource ID.');
    }
    
    $resourceId = (int)$resourceId;
    
    switch($resourceType) {
        case 'customer':
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
            $stmt->execute([$resourceId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                die('Customer not found.');
            }
            break;
            
        case 'invoice':
            $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ?");
            $stmt->execute([$resourceId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                die('Invoice not found.');
            }
            break;
            
        case 'payment':
            $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ?");
            $stmt->execute([$resourceId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                die('Payment not found.');
            }
            break;
            
        default:
            http_response_code(400);
            die('Invalid resource type.');
    }
}

function requireInvoiceOwnership($pdo, $invoiceId, $customerId = null) {
    if (empty($invoiceId) || !is_numeric($invoiceId)) {
        http_response_code(400);
        die('Invalid invoice ID.');
    }
    
    $invoiceId = (int)$invoiceId;
    
    if ($customerId) {
        // Verify invoice belongs to the specified customer
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND customer_id = ?");
        $stmt->execute([$invoiceId, $customerId]);
    } else {
        // Just verify invoice exists (for admin access)
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
    }
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        die($customerId ? 'Invoice not found or access denied.' : 'Invoice not found.');
    }
}

function requirePaymentOwnership($pdo, $paymentId, $customerId = null) {
    if (empty($paymentId) || !is_numeric($paymentId)) {
        http_response_code(400);
        die('Invalid payment ID.');
    }
    
    $paymentId = (int)$paymentId;
    
    if ($customerId) {
        // Verify payment belongs to customer through invoice relationship
        $stmt = $pdo->prepare("
            SELECT p.id 
            FROM payments p 
            JOIN invoices i ON p.invoice_id = i.id 
            WHERE p.id = ? AND i.customer_id = ?
        ");
        $stmt->execute([$paymentId, $customerId]);
    } else {
        // Just verify payment exists (for admin access)
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
    }
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        die($customerId ? 'Payment not found or access denied.' : 'Payment not found.');
    }
}

function requireClientAccess($pdo, $resourceType, $resourceId, $clientId) {
    if (empty($resourceId) || !is_numeric($resourceId) || empty($clientId) || !is_numeric($clientId)) {
        http_response_code(400);
        die('Invalid parameters.');
    }
    
    $resourceId = (int)$resourceId;
    $clientId = (int)$clientId;
    
    switch($resourceType) {
        case 'invoice':
            requireInvoiceOwnership($pdo, $resourceId, $clientId);
            break;
            
        case 'payment':
            requirePaymentOwnership($pdo, $resourceId, $clientId);
            break;
            
        default:
            http_response_code(400);
            die('Invalid resource type.');
    }
}

// Secure Error Handling Functions
function logSecureError($message, $details = null, $severity = 'ERROR') {
    $timestamp = date('Y-m-d H:i:s');
    $userInfo = isAdmin() ? 'Admin' : (isClientLoggedIn() ? 'Client' : 'Anonymous');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $logEntry = "[{$timestamp}] [{$severity}] [{$userInfo}] [{$ip}] {$message}";
    if ($details && defined('APP_DEBUG') && APP_DEBUG) {
        $logEntry .= " | Details: " . (is_array($details) ? json_encode($details) : $details);
    }
    $logEntry .= " | User-Agent: {$userAgent}" . PHP_EOL;
    
    // Log to file (ensure directory exists and is writable)
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    @error_log($logEntry, 3, $logFile);
}

function handleSecureError($userMessage, $technicalDetails = null, $httpCode = 500) {
    // Log the technical details securely
    logSecureError($userMessage, $technicalDetails, 'ERROR');
    
    // Return generic message to user
    http_response_code($httpCode);
    
    // Don't expose technical details in production
    $isProduction = !defined('APP_DEBUG') || !APP_DEBUG;
    
    if ($isProduction) {
        die($userMessage);
    } else {
        // In development, show more details (but still controlled)
        $debugInfo = is_string($technicalDetails) ? $technicalDetails : 'Check error logs for details';
        die($userMessage . ' (Debug: ' . $debugInfo . ')');
    }
}

function handleDatabaseError($operation, $exception, $userContext = 'operation') {
    $userMessage = "An error occurred during {$userContext}. Please try again or contact support.";
    $technicalDetails = [
        'operation' => $operation,
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    
    handleSecureError($userMessage, $technicalDetails, 500);
}

function validateInput($input, $type, $fieldName = 'field') {
    switch($type) {
        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                handleSecureError("Invalid email format for {$fieldName}.", "Email validation failed: {$input}", 400);
            }
            break;
            
        case 'numeric':
            if (!is_numeric($input)) {
                handleSecureError("Invalid numeric value for {$fieldName}.", "Numeric validation failed: {$input}", 400);
            }
            break;
            
        case 'required':
            if (empty(trim($input))) {
                handleSecureError("{$fieldName} is required.", "Required field validation failed", 400);
            }
            break;
            
        case 'positive_number':
            if (!is_numeric($input) || floatval($input) <= 0) {
                handleSecureError("Invalid positive number for {$fieldName}.", "Positive number validation failed: {$input}", 400);
            }
            break;
    }
    
    return trim($input);
}

function secureRedirect($location, $message = null) {
    // Validate redirect location to prevent open redirects
    $allowedPaths = ['/admin/', '/client/', '/public/', '/'];
    $isAllowed = false;
    
    foreach ($allowedPaths as $path) {
        if (strpos($location, $path) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed) {
        logSecureError("Attempted redirect to unauthorized location: {$location}", null, 'WARNING');
        $location = '/admin/dashboard.php'; // Default safe location
    }
    
    if ($message) {
        $_SESSION['flash_message'] = $message;
    }
    
    header("Location: {$location}");
    exit;
}

// Flash message system for user-friendly error handling
function setFlashMessage($message, $type = 'error') {
    $_SESSION['flash_message'] = [
        'text' => $message,
        'type' => $type, // error, success, warning, info
        'timestamp' => time()
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        // Only return messages that are less than 5 minutes old
        if (time() - $message['timestamp'] < 300) {
            return $message;
        }
    }
    return null;
}

function displayFlashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $typeClasses = [
            'error' => 'bg-red-50 border-red-200 text-red-700',
            'success' => 'bg-green-50 border-green-200 text-green-700',
            'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-700',
            'info' => 'bg-blue-50 border-blue-200 text-blue-700'
        ];
        
        $iconClasses = [
            'error' => 'fas fa-exclamation-circle text-red-500',
            'success' => 'fas fa-check-circle text-green-500',
            'warning' => 'fas fa-exclamation-triangle text-yellow-500',
            'info' => 'fas fa-info-circle text-blue-500'
        ];
        
        $classes = $typeClasses[$message['type']] ?? $typeClasses['info'];
        $icon = $iconClasses[$message['type']] ?? $iconClasses['info'];
        
        echo '<div class="mb-4 p-3 border rounded-lg ' . $classes . ' flex items-center">';
        echo '<i class="' . $icon . ' mr-2"></i>';
        echo '<span>' . htmlspecialchars($message['text']) . '</span>';
        echo '</div>';
    }
}

// Rate Limiting Functions
function createRateLimitTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            attempts INT DEFAULT 1,
            first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            blocked_until TIMESTAMP NULL,
            INDEX idx_identifier_endpoint (identifier, endpoint),
            INDEX idx_blocked_until (blocked_until)
        )";
        $pdo->exec($sql);
    } catch (Exception $e) {
        logSecureError("Failed to create rate_limits table", $e->getMessage(), 'ERROR');
    }
}

function getRateLimitIdentifier() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Use X-Forwarded-For if behind a proxy, but validate it
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $clientIp = trim($forwardedIps[0]);
        
        // Basic validation for IP format
        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ip = $clientIp;
        }
    }
    
    return $ip;
}

function checkRateLimit($pdo, $endpoint, $maxAttempts = 5, $windowMinutes = 15, $blockMinutes = 30) {
    // Ensure rate limit table exists
    createRateLimitTable($pdo);
    
    $identifier = getRateLimitIdentifier();
    $now = date('Y-m-d H:i:s');
    
    try {
        // Clean up old entries (older than 24 hours)
        $cleanupTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE first_attempt < ?");
        $stmt->execute([$cleanupTime]);
        
        // Check if currently blocked
        $stmt = $pdo->prepare("
            SELECT blocked_until, attempts 
            FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND blocked_until > ?
        ");
        $stmt->execute([$identifier, $endpoint, $now]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            $blockedUntil = strtotime($blocked['blocked_until']);
            $remainingMinutes = ceil(($blockedUntil - time()) / 60);
            
            logSecureError("Rate limit block active for {$endpoint}", [
                'identifier' => $identifier,
                'attempts' => $blocked['attempts'],
                'remaining_minutes' => $remainingMinutes
            ], 'WARNING');
            
            handleSecureError(
                "Too many failed attempts. Please try again in {$remainingMinutes} minute(s).",
                "Rate limit exceeded for {$endpoint}",
                429
            );
        }
        
        // Get current attempts within the window
        $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        $stmt = $pdo->prepare("
            SELECT attempts, first_attempt 
            FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND first_attempt >= ?
        ");
        $stmt->execute([$identifier, $endpoint, $windowStart]);
        $current = $stmt->fetch();
        
        if ($current) {
            $attempts = $current['attempts'];
            
            if ($attempts >= $maxAttempts) {
                // Block the user
                $blockUntil = date('Y-m-d H:i:s', strtotime("+{$blockMinutes} minutes"));
                $stmt = $pdo->prepare("
                    UPDATE rate_limits 
                    SET attempts = attempts + 1, blocked_until = ?, last_attempt = ?
                    WHERE identifier = ? AND endpoint = ?
                ");
                $stmt->execute([$blockUntil, $now, $identifier, $endpoint]);
                
                logSecureError("Rate limit exceeded for {$endpoint}", [
                    'identifier' => $identifier,
                    'attempts' => $attempts + 1,
                    'blocked_until' => $blockUntil
                ], 'WARNING');
                
                handleSecureError(
                    "Too many failed attempts. Please try again in {$blockMinutes} minute(s).",
                    "Rate limit exceeded for {$endpoint}",
                    429
                );
            }
        }
        
        return true; // Rate limit check passed
        
    } catch (Exception $e) {
        logSecureError("Rate limit check failed for {$endpoint}", $e->getMessage(), 'ERROR');
        // Don't block access if rate limiting fails - fail open for availability
        return true;
    }
}

function recordFailedAttempt($pdo, $endpoint) {
    createRateLimitTable($pdo);
    
    $identifier = getRateLimitIdentifier();
    $now = date('Y-m-d H:i:s');
    $windowStart = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    try {
        // Check if there's an existing record within the window
        $stmt = $pdo->prepare("
            SELECT id FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND first_attempt >= ?
        ");
        $stmt->execute([$identifier, $endpoint, $windowStart]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE rate_limits 
                SET attempts = attempts + 1, last_attempt = ?
                WHERE id = ?
            ");
            $stmt->execute([$now, $existing['id']]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (identifier, endpoint, attempts, first_attempt, last_attempt)
                VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([$identifier, $endpoint, $now, $now]);
        }
        
        logSecureError("Failed attempt recorded for {$endpoint}", [
            'identifier' => $identifier
        ], 'INFO');
        
    } catch (Exception $e) {
        logSecureError("Failed to record rate limit attempt for {$endpoint}", $e->getMessage(), 'ERROR');
    }
}

function recordSuccessfulAttempt($pdo, $endpoint) {
    createRateLimitTable($pdo);
    
    $identifier = getRateLimitIdentifier();
    
    try {
        // Clear rate limiting records for successful authentication
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ? AND endpoint = ?");
        $stmt->execute([$identifier, $endpoint]);
        
        logSecureError("Successful attempt - rate limits cleared for {$endpoint}", [
            'identifier' => $identifier
        ], 'INFO');
        
    } catch (Exception $e) {
        logSecureError("Failed to clear rate limits for {$endpoint}", $e->getMessage(), 'ERROR');
    }
}

/**
 * Enhanced Input Validation and Sanitization Functions
 */

function validateAndSanitizeString($input, $maxLength = 255, $fieldName = 'field', $required = true) {
    $input = trim($input);
    
    if ($required && empty($input)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (strlen($input) > $maxLength) {
        throw new InvalidArgumentException("{$fieldName} must be {$maxLength} characters or less.");
    }
    
    // Remove any null bytes and control characters
    $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
    
    return $input;
}

function validateEmail($email, $fieldName = 'Email', $required = true) {
    $email = trim($email);
    
    if ($required && empty($email)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("{$fieldName} must be a valid email address.");
    }
    
    if (strlen($email) > 255) {
        throw new InvalidArgumentException("{$fieldName} must be 255 characters or less.");
    }
    
    return $email;
}

function validatePhone($phone, $fieldName = 'Phone', $required = false) {
    $phone = trim($phone);
    
    if ($required && empty($phone)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($phone)) {
        // Remove all non-numeric characters for validation
        $numericPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($numericPhone) < 10 || strlen($numericPhone) > 15) {
            throw new InvalidArgumentException("{$fieldName} must be between 10 and 15 digits.");
        }
        
        // Allow formatted phone numbers (with spaces, dashes, parentheses)
        if (!preg_match('/^[\d\s\-\(\)\+\.]+$/', $phone)) {
            throw new InvalidArgumentException("{$fieldName} contains invalid characters.");
        }
    }
    
    return $phone;
}

function validateCurrency($amount, $fieldName = 'Amount', $required = true, $allowZero = false) {
    $amount = trim($amount);
    
    if ($required && empty($amount)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($amount)) {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException("{$fieldName} must be a valid number.");
        }
        
        $numericAmount = floatval($amount);
        
        if (!$allowZero && $numericAmount <= 0) {
            throw new InvalidArgumentException("{$fieldName} must be greater than zero.");
        }
        
        if ($numericAmount < 0) {
            throw new InvalidArgumentException("{$fieldName} cannot be negative.");
        }
        
        if ($numericAmount > 999999.99) {
            throw new InvalidArgumentException("{$fieldName} cannot exceed $999,999.99.");
        }
        
        // Round to 2 decimal places
        return round($numericAmount, 2);
    }
    
    return $allowZero ? 0 : null;
}

function validateInteger($value, $fieldName = 'Value', $required = true, $min = 0, $max = PHP_INT_MAX) {
    $value = trim($value);
    
    if ($required && empty($value)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($value)) {
        if (!is_numeric($value) || !ctype_digit(str_replace('-', '', $value))) {
            throw new InvalidArgumentException("{$fieldName} must be a valid integer.");
        }
        
        $intValue = intval($value);
        
        if ($intValue < $min) {
            throw new InvalidArgumentException("{$fieldName} must be at least {$min}.");
        }
        
        if ($intValue > $max) {
            throw new InvalidArgumentException("{$fieldName} cannot exceed {$max}.");
        }
        
        return $intValue;
    }
    
    return null;
}

function validateDate($date, $fieldName = 'Date', $required = true, $format = 'Y-m-d') {
    $date = trim($date);
    
    if ($required && empty($date)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($date)) {
        $dateObj = DateTime::createFromFormat($format, $date);
        
        if (!$dateObj || $dateObj->format($format) !== $date) {
            throw new InvalidArgumentException("{$fieldName} must be a valid date in format {$format}.");
        }
        
        return $date;
    }
    
    return null;
}

function validateSelect($value, $allowedValues, $fieldName = 'Selection', $required = true) {
    $value = trim($value);
    
    if ($required && empty($value)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($value) && !in_array($value, $allowedValues, true)) {
        throw new InvalidArgumentException("{$fieldName} must be one of: " . implode(', ', $allowedValues));
    }
    
    return $value;
}

function validateInvoiceNumber($invoiceNumber, $fieldName = 'Invoice Number', $required = true) {
    $invoiceNumber = trim($invoiceNumber);
    
    if ($required && empty($invoiceNumber)) {
        throw new InvalidArgumentException("{$fieldName} is required.");
    }
    
    if (!empty($invoiceNumber)) {
        // Allow alphanumeric characters, dashes, and underscores
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $invoiceNumber)) {
            throw new InvalidArgumentException("{$fieldName} can only contain letters, numbers, dashes, and underscores.");
        }
        
        if (strlen($invoiceNumber) > 50) {
            throw new InvalidArgumentException("{$fieldName} must be 50 characters or less.");
        }
    }
    
    return $invoiceNumber;
}

function sanitizeHtml($input, $allowedTags = '') {
    // Remove null bytes and control characters
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Strip tags except allowed ones
    $input = strip_tags($input, $allowedTags);
    
    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $input;
}

function validateCustomerData($data) {
    $errors = [];
    $validatedData = [];
    
    try {
        $validatedData['name'] = validateAndSanitizeString($data['name'] ?? '', 255, 'Customer Name', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['email'] = validateEmail($data['email'] ?? '', 'Email', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['phone'] = validatePhone($data['phone'] ?? '', 'Phone', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['address'] = validateAndSanitizeString($data['address'] ?? '', 500, 'Address', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    
    return $validatedData;
}

function validateInvoiceData($data) {
    $errors = [];
    $validatedData = [];
    
    try {
        $validatedData['customer_id'] = validateInteger($data['customer_id'] ?? '', 'Customer ID', true, 1);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['invoice_number'] = validateInvoiceNumber($data['invoice_number'] ?? '', 'Invoice Number', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['date'] = validateDate($data['date'] ?? '', 'Invoice Date', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['due_date'] = validateDate($data['due_date'] ?? '', 'Due Date', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['subtotal'] = validateCurrency($data['subtotal'] ?? '', 'Subtotal', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['tax_rate'] = validateCurrency($data['tax_rate'] ?? '0', 'Tax Rate', false, true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['tax_amount'] = validateCurrency($data['tax_amount'] ?? '0', 'Tax Amount', false, true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['total'] = validateCurrency($data['total'] ?? '', 'Total', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['notes'] = validateAndSanitizeString($data['notes'] ?? '', 1000, 'Notes', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    
    return $validatedData;
}

function validatePaymentData($data) {
    $errors = [];
    $validatedData = [];
    
    try {
        $validatedData['invoice_id'] = validateInteger($data['invoice_id'] ?? '', 'Invoice ID', true, 1);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['amount'] = validateCurrency($data['amount'] ?? '', 'Payment Amount', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['payment_date'] = validateDate($data['payment_date'] ?? '', 'Payment Date', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $allowedMethods = ['Cash', 'Check', 'Credit Card', 'Bank Transfer', 'PayPal', 'Venmo', 'CashApp', 'Other'];
        $validatedData['method'] = validateSelect($data['method'] ?? '', $allowedMethods, 'Payment Method', true);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    try {
        $validatedData['notes'] = validateAndSanitizeString($data['notes'] ?? '', 500, 'Payment Notes', false);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    
    return $validatedData;
}

/**
 * Security Headers Implementation
 */

function setSecurityHeaders($isAdminPage = false, $allowInlineStyles = false) {
    // Only set headers if they haven't been sent yet
    if (headers_sent()) {
        return;
    }
    
    // Content Security Policy
    $csp = buildContentSecurityPolicy($isAdminPage, $allowInlineStyles);
    header("Content-Security-Policy: $csp");
    
    // Clickjacking Protection
    header("X-Frame-Options: SAMEORIGIN");
    header("Frame-Options: SAMEORIGIN"); // Backup for older browsers
    
    // MIME Type Protection
    header("X-Content-Type-Options: nosniff");
    
    // XSS Protection (for legacy browsers)
    header("X-XSS-Protection: 1; mode=block");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // HTTPS Enforcement (HSTS) - only if HTTPS is enabled
    if (isHTTPS()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
    
    // Permissions Policy (control browser features)
    $permissionsPolicy = [
        'camera=()' => 'No camera access',
        'microphone=()' => 'No microphone access', 
        'geolocation=(self)' => 'Geolocation only from same origin',
        'payment=()' => 'No payment API access',
        'usb=()' => 'No USB access',
        'magnetometer=()' => 'No magnetometer access',
        'accelerometer=()' => 'No accelerometer access',
        'gyroscope=()' => 'No gyroscope access'
    ];
    header("Permissions-Policy: " . implode(', ', array_keys($permissionsPolicy)));
    
    // Cross-Origin Policies for enhanced security
    header("Cross-Origin-Embedder-Policy: require-corp");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-origin");
    
    // Cache Control for sensitive pages
    if ($isAdminPage) {
        header("Cache-Control: no-cache, no-store, must-revalidate, private");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
    
    // Additional security headers
    header("X-Permitted-Cross-Domain-Policies: none");
    header("X-Download-Options: noopen");
}

function buildContentSecurityPolicy($isAdminPage = false, $allowInlineStyles = false) {
    $csp = [];
    
    // Default source - restrict to self
    $csp[] = "default-src 'self'";
    
    // Script sources
    $scriptSources = ["'self'"];
    // Allow CDN sources that the app uses
    $scriptSources[] = "https://cdn.tailwindcss.com";
    $scriptSources[] = "https://cdn.jsdelivr.net";
    $scriptSources[] = "https://cdnjs.cloudflare.com";
    
    // Allow inline scripts for onclick handlers
    $scriptSources[] = "'unsafe-inline'";
    
    // For development/debugging - can be removed in production
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $scriptSources[] = "'unsafe-eval'";
    }
    
    $csp[] = "script-src " . implode(' ', $scriptSources);
    
    // Style sources
    $styleSources = ["'self'"];
    $styleSources[] = "https://cdn.tailwindcss.com";
    $styleSources[] = "https://cdnjs.cloudflare.com";
    
    // Allow inline styles for Tailwind CSS if needed
    if ($allowInlineStyles) {
        $styleSources[] = "'unsafe-inline'";
    }
    
    $csp[] = "style-src " . implode(' ', $styleSources);
    
    // Image sources
    $csp[] = "img-src 'self' data: https:";
    
    // Font sources
    $csp[] = "font-src 'self' https://cdnjs.cloudflare.com";
    
    // Connect sources (for AJAX requests)
    $csp[] = "connect-src 'self'";
    
    // Form actions - restrict to same origin
    $csp[] = "form-action 'self'";
    
    // Frame sources - no framing allowed
    $csp[] = "frame-src 'none'";
    
    // Object and embed sources
    $csp[] = "object-src 'none'";
    $csp[] = "embed-src 'none'";
    
    // Media sources
    $csp[] = "media-src 'self'";
    
    // Base URI restriction
    $csp[] = "base-uri 'self'";
    
    // Manifest source
    $csp[] = "manifest-src 'self'";
    
    return implode('; ', $csp);
}

function isHTTPS() {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
}

function setSecureSessionHeaders() {
    // Additional headers for session security
    if (!headers_sent()) {
        header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet");
        
        // Prevent page from being displayed in a frame/iframe
        header("X-Frame-Options: DENY");
    }
}

function setCacheHeaders($cacheType = 'no-cache') {
    if (headers_sent()) {
        return;
    }
    
    switch ($cacheType) {
        case 'no-cache':
            header("Cache-Control: no-cache, no-store, must-revalidate, private");
            header("Pragma: no-cache");
            header("Expires: 0");
            break;
            
        case 'public':
            header("Cache-Control: public, max-age=3600");
            header("Expires: " . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
            break;
            
        case 'private':
            header("Cache-Control: private, max-age=300");
            header("Expires: " . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');
            break;
    }
}

function setContentTypeHeader($contentType = 'text/html', $charset = 'UTF-8') {
    if (!headers_sent()) {
        header("Content-Type: $contentType; charset=$charset");
    }
}