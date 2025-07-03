<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen">
    <div class="min-h-screen flex">
        <!-- Left Side - Branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 relative overflow-hidden">
            <div class="absolute inset-0 bg-black opacity-20"></div>
            <div class="relative z-10 flex flex-col justify-center items-center text-white p-12">
                <div class="w-24 h-24 bg-white/20 backdrop-blur-lg rounded-3xl flex items-center justify-center mb-8">
                    <i class="fas fa-receipt text-white text-4xl"></i>
                </div>
                <?php
                require_once '../includes/functions.php';
                $businessSettings = getBusinessSettings($pdo);
                $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
                    ? $businessSettings['business_name'] 
                    : 'Kinvo';
                ?>
                <h1 class="text-4xl font-bold mb-4 text-center"><?php echo htmlspecialchars($appName); ?></h1>
                <p class="text-xl text-blue-100 text-center mb-8">Professional Business Management</p>
                <div class="space-y-4 text-blue-100">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-green-400"></i>
                        <span>Create professional invoices in minutes</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-green-400"></i>
                        <span>Track payments across multiple methods</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-green-400"></i>
                        <span>Manage customers and payment history</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-green-400"></i>
                        <span>Mobile-friendly and print-ready</span>
                    </div>
                </div>
            </div>
            <!-- Decorative elements -->
            <div class="absolute top-10 right-10 w-32 h-32 bg-white/10 rounded-full"></div>
            <div class="absolute bottom-20 left-10 w-24 h-24 bg-white/10 rounded-full"></div>
            <div class="absolute top-1/2 right-20 w-16 h-16 bg-white/10 rounded-full"></div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="flex-1 flex items-center justify-center p-8 lg:p-12">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">
                    <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-receipt text-white text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">Kinvo</h1>
                </div>

                <!-- Welcome Message -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                    <p class="text-gray-600">Sign in to access your dashboard</p>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-red-900">Authentication Failed</h3>
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                required 
                                class="w-full pl-12 pr-4 py-4 border border-gray-300 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all text-lg"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                            >
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-lg transform hover:scale-105"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </form>

                <!-- Additional Info -->
                <div class="mt-8 text-center">
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-2xl p-6 border border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-2 flex items-center justify-center">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                            Need Help?
                        </h3>
                        <p class="text-sm text-gray-600 mb-3">
                            If you've forgotten your password, check your configuration file or contact your system administrator.
                        </p>
                        <div class="text-xs text-gray-500">
                            <p><strong>Config file:</strong> includes/config.php</p>
                            <p><strong>Setting:</strong> ADMIN_PASSWORD</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-8 text-center text-sm text-gray-500">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appName); ?>. Secure login portal.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Focus on password field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').focus();
        });

        // Add enter key handler
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>