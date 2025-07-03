<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = '';
$remember_duration = 30 * 24 * 60 * 60; // 30 days

// Check if already logged in via remember cookie
if (!isset($_SESSION['admin']) && isset($_COOKIE['admin_remember'])) {
    $token = $_COOKIE['admin_remember'];
    // Simple token validation - in production, use proper token storage
    if (hash('sha256', ADMIN_PASSWORD . 'remember_salt') === $token) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        
        // Handle remember me
        if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
            $token = hash('sha256', ADMIN_PASSWORD . 'remember_salt');
            setcookie('admin_remember', $token, time() + $remember_duration, '/', '', true, true);
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
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