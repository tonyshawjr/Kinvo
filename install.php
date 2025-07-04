<?php
/**
 * Kinvo Installation Wizard
 * Simple installation process for shared hosting
 */

session_start();

// Check if already installed
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
    if (defined('DB_HOST') && file_exists('includes/.installed')) {
        header('Location: admin/login.php');
        exit;
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Database connection test
            try {
                $host = $_POST['db_host'];
                $name = $_POST['db_name'];
                $user = $_POST['db_user'];
                $pass = $_POST['db_pass'];
                
                $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Store in session for next step
                $_SESSION['db_config'] = [
                    'host' => $host,
                    'name' => $name,
                    'user' => $user,
                    'pass' => $pass
                ];
                
                $success = "Database connection successful!";
                $step = 3;
            } catch (Exception $e) {
                $error = "Database connection failed: " . $e->getMessage();
            }
            break;
            
        case 3:
            // Create tables and config
            try {
                $db = $_SESSION['db_config'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create tables
                $sql = file_get_contents('database_schema.sql');
                $pdo->exec($sql);
                
                // Create config file
                $config_content = "<?php\n";
                $config_content .= "// Database Configuration\n";
                $config_content .= "define('DB_HOST', '{$db['host']}');\n";
                $config_content .= "define('DB_NAME', '{$db['name']}');\n";
                $config_content .= "define('DB_USER', '{$db['user']}');\n";
                $config_content .= "define('DB_PASS', '{$db['pass']}');\n\n";
                $config_content .= "// Site Configuration\n";
                $config_content .= "define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));\n";
                $config_content .= "define('ADMIN_PASSWORD', 'admin123'); // Will be migrated to database\n\n";
                $config_content .= "// Start session\n";
                $config_content .= "if (session_status() === PHP_SESSION_NONE) {\n";
                $config_content .= "    session_start();\n";
                $config_content .= "}\n";
                
                file_put_contents('includes/config.php', $config_content);
                
                // Setup business settings
                $business_name = $_POST['business_name'] ?: 'Your Business Name';
                $business_phone = $_POST['business_phone'] ?: '';
                $business_email = $_POST['business_email'] ?: '';
                $admin_password = password_hash($_POST['admin_password'] ?: 'admin123', PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO business_settings 
                    (business_name, business_phone, business_email, payment_instructions, admin_password) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $business_name,
                    $business_phone, 
                    $business_email,
                    "Payment is due within 30 days. Please contact us for payment methods.",
                    $admin_password
                ]);
                
                // Create install marker
                file_put_contents('includes/.installed', date('Y-m-d H:i:s'));
                
                // Clear session
                unset($_SESSION['db_config']);
                
                $step = 4;
                $success = "Installation completed successfully!";
                
            } catch (Exception $e) {
                $error = "Installation failed: " . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Kinvo - Invoice Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-file-invoice text-blue-600 mr-3"></i>Kinvo
            </h1>
            <p class="text-gray-600">Professional Invoice Management System</p>
            <p class="text-sm text-gray-500 mt-2">Installation Wizard</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">Installation Progress</span>
                <span class="text-sm font-medium text-gray-700"><?php echo min($step, 4); ?>/4</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo (min($step, 4) / 4) * 100; ?>%"></div>
            </div>
        </div>

        <!-- Installation Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            
            <?php if ($step == 1): ?>
            <!-- Step 1: Welcome -->
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-home text-blue-600 mr-2"></i>Welcome to Kinvo
                </h2>
                <p class="text-gray-600 mb-6">This installation wizard will help you set up Kinvo on your shared hosting account.</p>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-900 mb-2">Before you begin:</h3>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li>• Make sure you have your database credentials ready</li>
                        <li>• Ensure PHP 7.4+ and MySQL 5.7+ are available</li>
                        <li>• Have your business information prepared</li>
                    </ul>
                </div>
                
                <div class="flex justify-end">
                    <a href="?step=2" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                        Get Started <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
            
            <?php elseif ($step == 2): ?>
            <!-- Step 2: Database Setup -->
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-database text-blue-600 mr-2"></i>Database Configuration
                </h2>
                <p class="text-gray-600 mb-6">Enter your database connection details. You can find these in your hosting control panel.</p>
                
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                        <span class="text-red-800"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="text-green-800"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>
                            <input type="text" name="db_host" value="localhost" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Usually "localhost"</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                            <input type="text" name="db_name" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>
                            <input type="text" name="db_user" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                            <input type="password" name="db_pass" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-between pt-4">
                        <a href="?step=1" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                            Test Connection <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($step == 3): ?>
            <!-- Step 3: Business Setup -->
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-building text-blue-600 mr-2"></i>Business Information
                </h2>
                <p class="text-gray-600 mb-6">Set up your business details and admin account.</p>
                
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                        <span class="text-red-800"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Business Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Business Name</label>
                                <input type="text" name="business_name" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Business Phone</label>
                                <input type="tel" name="business_phone" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Business Email</label>
                                <input type="email" name="business_email" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin Account</h3>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                            <input type="password" name="admin_password" value="admin123" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Default is "admin123" - you can change this later</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between pt-4">
                        <a href="?step=2" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Back
                        </a>
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                            Install Kinvo <i class="fas fa-check ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($step == 4): ?>
            <!-- Step 4: Complete -->
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Installation Complete!</h2>
                <p class="text-gray-600 mb-6">Kinvo has been successfully installed and configured.</p>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-semibold text-green-900 mb-2">What's Next?</h3>
                    <ul class="text-sm text-green-800 space-y-1">
                        <li>• Login to your admin dashboard</li>
                        <li>• Complete your business settings</li>
                        <li>• Create your first customer</li>
                        <li>• Generate your first invoice</li>
                    </ul>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                        <div class="text-left">
                            <p class="font-semibold text-yellow-900">Security Reminder</p>
                            <p class="text-sm text-yellow-800">For security, consider deleting this install.php file after installation.</p>
                        </div>
                    </div>
                </div>
                
                <a href="admin/login.php" class="inline-flex items-center px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold text-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to Kinvo
                </a>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-sm text-gray-500">
                Kinvo v1.0 - Professional Invoice Management System
            </p>
        </div>
    </div>
</body>
</html>