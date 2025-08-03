<?php
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true); // Admin page with inline styles allowed for Tailwind

$error = '';
$remember_duration = 30 * 24 * 60 * 60; // 30 days

// Check if already logged in via remember cookie
if (!isset($_SESSION['admin']) && isset($_COOKIE['admin_remember'])) {
    $token = $_COOKIE['admin_remember'];
    // Note: Remember me tokens will need to be regenerated after password changes
    // For now, we'll clear invalid tokens and require re-login
    try {
        $stmt = $pdo->query("SELECT admin_password FROM business_settings LIMIT 1");
        $result = $stmt->fetch();
        $validToken = false;
        
        if ($result && !empty($result['admin_password'])) {
            // Use secure remember tokens stored in database instead of password-based tokens
            $stmt = $pdo->prepare("SELECT remember_token FROM business_settings WHERE remember_token = ? AND remember_expires > NOW() LIMIT 1");
            $stmt->execute([$token]);
            $validToken = $stmt->rowCount() > 0;
        } else {
            // No admin password set - invalid token
            $validToken = false;
        }
        
        if ($validToken) {
            // Regenerate session ID for remember token login
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: dashboard.php');
            exit;
        } else {
            // Clear invalid cookie
            setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        // Clear cookie on error
        setcookie('admin_remember', '', time() - 3600, '/', '', true, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken(); // Validate CSRF token
    
    // Check rate limiting before processing login
    checkRateLimit($pdo, 'admin_login', 5, 15, 30);
    
    if (verifyAdminPassword($_POST['password'], $pdo)) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        
        // Handle remember me
        if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + $remember_duration);
            
            // Store token in database
            $stmt = $pdo->prepare("UPDATE business_settings SET remember_token = ?, remember_expires = ?");
            $stmt->execute([$token, $expires]);
            
            setcookie('admin_remember', $token, time() + $remember_duration, '/', '', true, true);
        }
        
        // Record successful attempt to clear rate limits
        recordSuccessfulAttempt($pdo, 'admin_login');
        
        header('Location: dashboard.php');
        exit;
    } else {
        // Record failed attempt for rate limiting
        recordFailedAttempt($pdo, 'admin_login');
        $error = 'Invalid password';
    }
}

// Get business settings for branding
$businessSettings = getBusinessSettings($pdo);
$appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
    ? $businessSettings['business_name'] 
    : 'Kinvo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($appName); ?></title>
    
    <!-- iOS Web App Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/apple-touch-icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="../assets/apple-touch-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="../assets/apple-touch-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/apple-touch-icon-76x76.png">
    
    <!-- Web App Settings -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Kinvo">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <!-- Logo & App Name -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($appName); ?></h1>
            <p class="text-gray-600">Invoice Management System</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 flex items-center space-x-2">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" class="space-y-4">
                <?php echo getCSRFTokenField(); ?>
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors text-base"
                            placeholder="Enter password"
                            autocomplete="current-password"
                        >
                        <button type="button" id="toggle-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center">
                    <input 
                        id="remember" 
                        name="remember" 
                        type="checkbox" 
                        class="h-4 w-4 text-gray-900 focus:ring-gray-900 border-gray-300 rounded"
                    >
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Keep me logged in for 30 days
                    </label>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-gray-900 text-white py-2 px-4 rounded-lg font-semibold hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 transition-colors"
                >
                    Sign In
                </button>
            </form>

        </div>

        <!-- Footer -->
        <p class="mt-8 text-center text-xs text-gray-500">
            &copy; <?php echo date('Y'); ?> Kinvo &middot; Powered by Kinvo
        </p>
    </div>

    <script>
        // Auto-focus password field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').focus();
        });

        // Toggle password visibility
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Quick submit with Cmd/Ctrl + Enter
        document.getElementById('password').addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>