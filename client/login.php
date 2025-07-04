<?php
session_start();
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers for client login
setSecurityHeaders(false, true);

$error = '';
$success = '';
$prefill_email = $_GET['email'] ?? '';

// Check if already logged in
if (isClientLoggedIn()) {
    header('Location: /client/dashboard.php');
    exit;
}

// Check for remember token
if (isset($_COOKIE['client_remember']) && !empty($_COOKIE['client_remember'])) {
    $client = validateRememberToken($pdo, $_COOKIE['client_remember']);
    if ($client) {
        // Regenerate session ID for remember token login
        session_regenerate_id(true);
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client_email'] = $client['email'];
        $_SESSION['client_name'] = $client['name'];
        
        logClientActivity($pdo, $client['id'], 'login', 'Auto-login via remember token');
        updateClientLastLogin($pdo, $client['id']);
        
        header('Location: /client/dashboard.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    // Check rate limiting before processing login
    checkRateLimit($pdo, 'client_login', 5, 15, 30);
    
    $email = trim($_POST['email'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($pin)) {
        recordFailedAttempt($pdo, 'client_login');
        $error = 'Please enter both email and PIN.';
    } else {
        $client = getClientByEmail($pdo, $email);
        
        if (!$client) {
            recordFailedAttempt($pdo, 'client_login');
            $error = 'Invalid email or PIN.';
        } elseif (!$client['is_active']) {
            recordFailedAttempt($pdo, 'client_login');
            $error = 'Account is disabled. Please contact support.';
        } elseif ($client['locked_until'] && strtotime($client['locked_until']) > time()) {
            recordFailedAttempt($pdo, 'client_login');
            $error = 'Account is temporarily locked. Please try again later.';
        } else {
            if (verifyClientPIN($pin, $client['pin'])) {
                // Successful login - regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_name'] = $client['name'];
                
                updateClientLastLogin($pdo, $client['id']);
                logClientActivity($pdo, $client['id'], 'login', 'Successful login');
                
                // Record successful attempt to clear rate limits
                recordSuccessfulAttempt($pdo, 'client_login');
                
                // Set remember token if requested
                if ($remember) {
                    $token = generateRememberToken($pdo, $client['id']);
                    setcookie('client_remember', $token, time() + (30 * 24 * 60 * 60), '/client/'); // 30 days
                }
                
                header('Location: /client/dashboard.php');
                exit;
            } else {
                // Failed login
                recordFailedAttempt($pdo, 'client_login');
                incrementLoginAttempts($pdo, $email);
                logClientActivity($pdo, $client['id'], 'login_failed', 'Invalid PIN');
                
                // Lock account after 5 failed attempts
                if ($client['login_attempts'] >= 4) {
                    lockClientAccount($pdo, $email, 30);
                    $error = 'Too many failed attempts. Account locked for 30 minutes.';
                } else {
                    $remaining = 5 - ($client['login_attempts'] + 1);
                    $error = "Invalid PIN. {$remaining} attempts remaining.";
                }
            }
        }
    }
}

$businessSettings = getBusinessSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Simple Header for Login -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-center items-center h-16">
                <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h1>
                <span class="ml-3 px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Client Portal</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-gray-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-shield text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Welcome Back</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Sign in to access your invoices, payments, and statements
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <?php if ($error): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                        <span class="text-red-800"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                        <span class="text-green-800"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <?php echo getCSRFTokenField(); ?>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($prefill_email); ?>"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="pin" class="block text-sm font-medium text-gray-700">4-Digit PIN</label>
                    <input type="password" id="pin" name="pin" required maxlength="4" pattern="[0-9]{4}"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-center text-lg tracking-widest">
                    <p class="mt-1 text-xs text-gray-500">Enter the 4-digit PIN from your welcome email</p>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me for 30 days</label>
                </div>

                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="/client/forgot-pin.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                    Forgot your PIN?
                </a>
            </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($businessSettings['business_name']); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-format PIN input
        document.getElementById('pin').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>