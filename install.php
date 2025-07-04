<?php
/**
 * Kinvo Installation Wizard
 * Professional multi-step installation process
 */

session_start();

// Secure error handling for installation
define('APP_DEBUG', false); // Set to true only during development
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Include security functions for CSRF protection
require_once 'includes/functions.php';

// Check if already installed
if (file_exists('includes/config.php') && file_exists('includes/.installed')) {
    require_once 'includes/config.php';
    if (defined('DB_HOST')) {
        header('Location: admin/login.php');
        exit;
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';
$warnings = [];

// Function to check PHP extensions
function checkExtension($name) {
    return extension_loaded($name);
}

// Function to check if a directory is writable
function checkWritable($path) {
    return is_writable($path);
}

// Function to get PHP version info
function getPHPVersionInfo() {
    $version = PHP_VERSION;
    $versionParts = explode('.', $version);
    return [
        'full' => $version,
        'major' => (int)$versionParts[0],
        'minor' => (int)$versionParts[1],
        'isSupported' => version_compare($version, '7.4.0', '>=')
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    requireCSRFToken();
    
    switch ($step) {
        case 1:
            // Requirements check - just move to next step
            header('Location: ?step=2');
            exit;
            break;
            
        case 2:
            // Database connection
            try {
                $host = trim($_POST['db_host']);
                $name = trim($_POST['db_name']);
                $user = trim($_POST['db_user']);
                $pass = $_POST['db_pass'];
                $prefix = ''; // No table prefix support
                
                // Test connection
                $dsn = "mysql:host=$host";
                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if database exists
                $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
                $stmt->execute([$name]);
                if ($stmt->rowCount() == 0) {
                    // Validate database name (only allow alphanumeric and underscore)
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                        throw new Exception('Invalid database name. Only alphanumeric characters and underscores are allowed.');
                    }
                    // Try to create database - using identifier quoting since we can't parameterize DDL
                    $quotedName = '`' . str_replace('`', '``', $name) . '`';
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS $quotedName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                
                // Connect to the database
                $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Store in session
                $_SESSION['db_config'] = [
                    'host' => $host,
                    'name' => $name,
                    'user' => $user,
                    'pass' => $pass,
                    'prefix' => $prefix
                ];
                
                header('Location: ?step=3');
                exit;
                
            } catch (Exception $e) {
                // Log the actual error securely
                $timestamp = date('Y-m-d H:i:s');
                $logEntry = "[{$timestamp}] [ERROR] Installation database connection failed: " . $e->getMessage() . PHP_EOL;
                
                // Create logs directory if it doesn't exist
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0750, true);
                }
                
                // Log to file
                $logFile = $logDir . '/install_errors_' . date('Y-m-d') . '.log';
                @error_log($logEntry, 3, $logFile);
                
                // Show generic message to user
                if (APP_DEBUG) {
                    $error = "Database connection failed: " . $e->getMessage();
                } else {
                    $error = "Database connection failed. Please check your connection settings and try again.";
                }
            }
            break;
            
        case 3:
            // Install database tables
            try {
                $db = $_SESSION['db_config'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if tables already exist
                $requiredTables = ['customers', 'invoices', 'invoice_items', 'payments', 'customer_properties', 'business_settings'];
                $existingTables = [];
                $missingTables = [];
                
                foreach ($requiredTables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        $existingTables[] = $table;
                    } else {
                        $missingTables[] = $table;
                    }
                }
                
                // Store table status in session for display
                $_SESSION['existing_tables'] = $existingTables;
                $_SESSION['missing_tables'] = $missingTables;
                
                // If some tables exist, only create missing ones
                if (!empty($missingTables)) {
                    // Read and execute schema
                    $schema = file_get_contents('database_schema.sql');
                    if ($schema === false) {
                        throw new Exception("Unable to read database schema file.");
                    }
                    
                    // Split by semicolon and execute each statement
                    $statements = array_filter(array_map('trim', explode(';', $schema)));
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                }
                
                $_SESSION['db_installed'] = true;
                header('Location: ?step=4');
                exit;
                
            } catch (Exception $e) {
                // Log the actual error securely
                $timestamp = date('Y-m-d H:i:s');
                $logEntry = "[{$timestamp}] [ERROR] Database installation failed: " . $e->getMessage() . PHP_EOL;
                
                // Create logs directory if it doesn't exist
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0750, true);
                }
                
                // Log to file
                $logFile = $logDir . '/install_errors_' . date('Y-m-d') . '.log';
                @error_log($logEntry, 3, $logFile);
                
                // Show generic message to user
                if (APP_DEBUG) {
                    $error = "Database installation failed: " . $e->getMessage();
                } else {
                    $error = "Database installation failed. Please check the database schema file and try again.";
                }
            }
            break;
            
        case 4:
            // Business configuration
            try {
                $db = $_SESSION['db_config'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Store site URL in session
                $_SESSION['site_url'] = trim($_POST['site_url']);
                
                // Prepare data
                $business_name = trim($_POST['business_name']) ?: 'Your Business Name';
                $business_phone = trim($_POST['business_phone']) ?: '';
                $business_email = trim($_POST['business_email']) ?: '';
                $business_ein = trim($_POST['business_ein']) ?: '';
                $default_hourly_rate = floatval($_POST['default_hourly_rate']) ?: 45.00;
                $mileage_rate = floatval($_POST['mileage_rate']) ?: 0.650;
                $payment_instructions = trim($_POST['payment_instructions']) ?: 'Payment is due within 30 days.';
                
                // Check if business settings already exist
                $stmt = $pdo->query("SELECT COUNT(*) FROM business_settings");
                $exists = $stmt->fetchColumn() > 0;
                
                if ($exists) {
                    // Update existing
                    $stmt = $pdo->prepare("UPDATE business_settings SET 
                        business_name = ?, business_phone = ?, business_email = ?, 
                        business_ein = ?, default_hourly_rate = ?, mileage_rate = ?, 
                        payment_instructions = ? WHERE 1");
                    $stmt->execute([
                        $business_name, $business_phone, $business_email,
                        $business_ein, $default_hourly_rate, $mileage_rate,
                        $payment_instructions
                    ]);
                } else {
                    // Insert new
                    $stmt = $pdo->prepare("INSERT INTO business_settings 
                        (business_name, business_phone, business_email, business_ein, 
                         default_hourly_rate, mileage_rate, payment_instructions) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $business_name, $business_phone, $business_email,
                        $business_ein, $default_hourly_rate, $mileage_rate,
                        $payment_instructions
                    ]);
                }
                
                $_SESSION['business_configured'] = true;
                header('Location: ?step=5');
                exit;
                
            } catch (Exception $e) {
                // Log the actual error securely
                $timestamp = date('Y-m-d H:i:s');
                $logEntry = "[{$timestamp}] [ERROR] Business configuration failed: " . $e->getMessage() . PHP_EOL;
                
                // Create logs directory if it doesn't exist
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0750, true);
                }
                
                // Log to file
                $logFile = $logDir . '/install_errors_' . date('Y-m-d') . '.log';
                @error_log($logEntry, 3, $logFile);
                
                // Show generic message to user
                if (APP_DEBUG) {
                    $error = "Business configuration failed: " . $e->getMessage();
                } else {
                    $error = "Business configuration failed. Please check your input and try again.";
                }
            }
            break;
            
        case 5:
            // Admin account setup
            try {
                $db = $_SESSION['db_config'];
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $admin_password = $_POST['admin_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (strlen($admin_password) < 6) {
                    throw new Exception("Password must be at least 6 characters long.");
                }
                
                if ($admin_password !== $confirm_password) {
                    throw new Exception("Passwords do not match.");
                }
                
                // Hash password and update
                $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE business_settings SET admin_password = ?");
                $stmt->execute([$hashed]);
                
                // Create config file
                $siteUrl = $_SESSION['site_url'] ?? ('http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
                $siteUrl = rtrim($siteUrl, '/');
                
                $config_content = "<?php\n";
                $config_content .= "/**\n";
                $config_content .= " * Kinvo Configuration File\n";
                $config_content .= " * Generated by installer on " . date('Y-m-d H:i:s') . "\n";
                $config_content .= " */\n\n";
                $config_content .= "// Database Configuration\n";
                $config_content .= "define('DB_HOST', '" . addslashes($db['host']) . "');\n";
                $config_content .= "define('DB_NAME', '" . addslashes($db['name']) . "');\n";
                $config_content .= "define('DB_USER', '" . addslashes($db['user']) . "');\n";
                $config_content .= "define('DB_PASS', '" . addslashes($db['pass']) . "');\n\n";
                $config_content .= "// Site Configuration\n";
                $config_content .= "define('SITE_NAME', 'Kinvo');\n";
                $config_content .= "define('SITE_URL', '" . addslashes($siteUrl) . "');\n";
                $config_content .= "define('ADMIN_PASSWORD', 'deprecated'); // Now stored in database\n\n";
                $config_content .= "// Error Reporting (set to 0 in production)\n";
                $config_content .= "error_reporting(0);\n";
                $config_content .= "ini_set('display_errors', 0);\n\n";
                $config_content .= "// Timezone\n";
                $config_content .= "date_default_timezone_set('America/New_York');\n\n";
                $config_content .= "// Session Configuration\n";
                $config_content .= "if (session_status() === PHP_SESSION_NONE) {\n";
                $config_content .= "    session_start();\n";
                $config_content .= "}\n";
                
                // Write config file
                if (!file_put_contents('includes/config.php', $config_content)) {
                    throw new Exception("Could not write configuration file. Please check directory permissions.");
                }
                
                // Copy config to secure location outside web root
                $secureConfigDir = __DIR__ . '/../SimpleInvoice_Config';
                $secureConfigPath = $secureConfigDir . '/config.php';
                
                // Create secure config directory if it doesn't exist
                if (!is_dir($secureConfigDir)) {
                    if (!mkdir($secureConfigDir, 0750, true)) {
                        // Log warning but don't fail installation
                        error_log("Warning: Could not create secure config directory: $secureConfigDir");
                    }
                }
                
                // Copy config to secure location
                if (is_dir($secureConfigDir)) {
                    if (!copy('includes/config.php', $secureConfigPath)) {
                        // Log warning but don't fail installation
                        error_log("Warning: Could not copy config to secure location: $secureConfigPath");
                    } else {
                        // Set secure permissions on the config file
                        @chmod($secureConfigPath, 0640);
                    }
                }
                
                // Create installed marker
                if (!file_put_contents('includes/.installed', date('Y-m-d H:i:s'))) {
                    throw new Exception("Could not create installation marker file. Please check directory permissions.");
                }
                
                // Clear session
                session_destroy();
                
                header('Location: ?step=6');
                exit;
                
            } catch (Exception $e) {
                // Log the actual error securely
                $timestamp = date('Y-m-d H:i:s');
                $logEntry = "[{$timestamp}] [ERROR] Admin account setup failed: " . $e->getMessage() . PHP_EOL;
                
                // Create logs directory if it doesn't exist
                $logDir = __DIR__ . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0750, true);
                }
                
                // Log to file
                $logFile = $logDir . '/install_errors_' . date('Y-m-d') . '.log';
                @error_log($logEntry, 3, $logFile);
                
                // Show generic message to user
                if (APP_DEBUG) {
                    $error = $e->getMessage();
                } else {
                    // For password validation errors, we can be more specific since they're user input validation
                    if (strpos($e->getMessage(), 'Password must be') !== false || 
                        strpos($e->getMessage(), 'Passwords do not match') !== false) {
                        $error = $e->getMessage();
                    } else {
                        $error = "Admin account setup failed. Please check your input and try again.";
                    }
                }
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
    <title>Install Kinvo - Professional Invoice Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-900">
                            Kinvo Installation
                        </h1>
                    </div>
                    <span class="text-sm text-gray-500">Version 1.0</span>
                </div>
            </div>
        </header>

        <!-- Progress Bar -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-4">
                <div class="flex items-center justify-between text-sm">
                    <?php
                    $steps = [
                        1 => ['title' => 'Requirements'],
                        2 => ['title' => 'Database'],
                        3 => ['title' => 'Install'],
                        4 => ['title' => 'Business'],
                        5 => ['title' => 'Admin'],
                        6 => ['title' => 'Complete']
                    ];
                    
                    foreach ($steps as $num => $info):
                        $isActive = $num == $step;
                        $isCompleted = $num < $step;
                    ?>
                    <div class="flex items-center <?php echo $num < 6 ? 'flex-1' : ''; ?>">
                        <div class="flex items-center">
                            <div class="<?php echo $isCompleted ? 'bg-gray-900' : ($isActive ? 'bg-gray-600' : 'bg-gray-300'); ?> rounded-full w-8 h-8 flex items-center justify-center text-white font-semibold text-sm">
                                <?php echo $num; ?>
                            </div>
                            <span class="ml-2 <?php echo $isActive ? 'font-semibold text-gray-900' : 'text-gray-500'; ?>">
                                <?php echo $info['title']; ?>
                            </span>
                        </div>
                        <?php if ($num < 6): ?>
                        <div class="flex-1 mx-4">
                            <div class="h-1 bg-gray-200 rounded">
                                <div class="h-1 <?php echo $isCompleted ? 'bg-gray-900' : 'bg-gray-200'; ?> rounded"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="max-w-4xl mx-auto px-4 py-8">
                
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div>
                        <h3 class="font-semibold text-red-900">Installation Error</h3>
                        <p class="text-red-700 mt-1"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                <!-- Step 1: Requirements Check -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">System Requirements Check</h2>
                    
                    <?php
                    $phpVersion = getPHPVersionInfo();
                    $requirements = [
                        [
                            'name' => 'PHP Version',
                            'required' => '7.4+',
                            'current' => $phpVersion['full'],
                            'status' => $phpVersion['isSupported'],
                            'critical' => true
                        ],
                        [
                            'name' => 'PDO Extension',
                            'required' => 'Enabled',
                            'current' => checkExtension('pdo') ? 'Enabled' : 'Disabled',
                            'status' => checkExtension('pdo'),
                            'critical' => true
                        ],
                        [
                            'name' => 'PDO MySQL',
                            'required' => 'Enabled',
                            'current' => checkExtension('pdo_mysql') ? 'Enabled' : 'Disabled',
                            'status' => checkExtension('pdo_mysql'),
                            'critical' => true
                        ],
                        [
                            'name' => 'Session Support',
                            'required' => 'Enabled',
                            'current' => function_exists('session_start') ? 'Enabled' : 'Disabled',
                            'status' => function_exists('session_start'),
                            'critical' => true
                        ],
                        [
                            'name' => 'JSON Support',
                            'required' => 'Enabled',
                            'current' => checkExtension('json') ? 'Enabled' : 'Disabled',
                            'status' => checkExtension('json'),
                            'critical' => false
                        ]
                    ];
                    
                    $canContinue = true;
                    ?>
                    
                    <div class="space-y-3 mb-6">
                        <?php foreach ($requirements as $req): ?>
                        <?php if (!$req['status'] && $req['critical']) $canContinue = false; ?>
                        <div class="flex items-center justify-between p-3 rounded-lg <?php echo $req['status'] ? 'bg-green-50' : 'bg-red-50'; ?>">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full <?php echo $req['status'] ? 'bg-green-600' : 'bg-red-600'; ?> flex items-center justify-center mr-3">
                                    <span class="text-white font-bold text-xs"><?php echo $req['status'] ? '✓' : '✗'; ?></span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-900"><?php echo $req['name']; ?></span>
                                    <span class="text-sm text-gray-500 ml-2">(Required: <?php echo $req['required']; ?>)</span>
                                </div>
                            </div>
                            <span class="text-sm font-medium <?php echo $req['status'] ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $req['current']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">File Permissions</h3>
                    <?php
                    $permissions = [
                        ['path' => 'includes/', 'name' => 'Includes Directory'],
                        ['path' => 'includes/config.php', 'name' => 'Config File', 'create' => true]
                    ];
                    ?>
                    
                    <div class="space-y-3 mb-6">
                        <?php foreach ($permissions as $perm): ?>
                        <?php 
                        $isWritable = isset($perm['create']) ? checkWritable(dirname($perm['path'])) : checkWritable($perm['path']);
                        if (!$isWritable) $canContinue = false;
                        ?>
                        <div class="flex items-center justify-between p-3 rounded-lg <?php echo $isWritable ? 'bg-green-50' : 'bg-red-50'; ?>">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full <?php echo $isWritable ? 'bg-green-600' : 'bg-red-600'; ?> flex items-center justify-center mr-3">
                                    <span class="text-white font-bold text-xs"><?php echo $isWritable ? '✓' : '✗'; ?></span>
                                </div>
                                <span class="font-medium text-gray-900"><?php echo $perm['name']; ?></span>
                            </div>
                            <span class="text-sm font-medium <?php echo $isWritable ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $isWritable ? 'Writable' : 'Not Writable'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$canContinue): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div>
                            <h4 class="font-semibold text-yellow-900">Action Required</h4>
                            <p class="text-yellow-700 mt-1">Please fix the above issues before continuing. Contact your hosting provider if you need assistance.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-end">
                        <form method="POST">
                            <?php echo getCSRFTokenField(); ?>
                            <button type="submit" <?php echo !$canContinue ? 'disabled' : ''; ?>
                                    class="<?php echo $canContinue ? 'bg-gray-900 hover:bg-gray-800' : 'bg-gray-400 cursor-not-allowed'; ?> text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Continue
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($step == 2): ?>
                <!-- Step 2: Database Configuration -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Database Configuration</h2>
                    <p class="text-gray-600 mb-6">Enter your MySQL database connection details. You can find these in your hosting control panel.</p>
                    
                    <form method="POST" class="space-y-4">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Database Host
                                </label>
                                <input type="text" name="db_host" value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                <p class="text-xs text-gray-500 mt-1">Usually "localhost" for shared hosting</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Database Name
                                </label>
                                <input type="text" name="db_name" value="<?php echo $_POST['db_name'] ?? ''; ?>" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                <p class="text-xs text-gray-500 mt-1">The name of your MySQL database</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Database Username
                                </label>
                                <input type="text" name="db_user" value="<?php echo $_POST['db_user'] ?? ''; ?>" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Database Password
                                </label>
                                <input type="password" name="db_pass" value="<?php echo $_POST['db_pass'] ?? ''; ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            </div>
                            
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="text-sm text-blue-700">
                                <p>If the database doesn't exist, the installer will try to create it.</p>
                                <p class="mt-1">Make sure your database user has CREATE privileges.</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="?step=1" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                Back
                            </a>
                            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                                Test Connection
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($step == 3): ?>
                <!-- Step 3: Database Installation -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Database Installation</h2>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <div class="w-6 h-6 rounded-full bg-green-600 flex items-center justify-center mr-3">
                                <span class="text-white font-bold text-xs">✓</span>
                            </div>
                            <span class="text-green-800">Database connection successful!</span>
                        </div>
                    </div>
                    
                    <?php
                    // Check for existing tables when first visiting step 3
                    if (!isset($_SESSION['existing_tables']) && isset($_SESSION['db_config'])) {
                        try {
                            $db = $_SESSION['db_config'];
                            $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            
                            $requiredTables = ['customers', 'invoices', 'invoice_items', 'payments', 'customer_properties', 'business_settings'];
                            $existingTables = [];
                            
                            foreach ($requiredTables as $table) {
                                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                                if ($stmt->rowCount() > 0) {
                                    $existingTables[] = $table;
                                }
                            }
                            
                            $_SESSION['existing_tables'] = $existingTables;
                        } catch (Exception $e) {
                            // Log the actual error securely
                            $timestamp = date('Y-m-d H:i:s');
                            $logEntry = "[{$timestamp}] [ERROR] Failed to check existing tables: " . $e->getMessage() . PHP_EOL;
                            
                            // Create logs directory if it doesn't exist
                            $logDir = __DIR__ . '/logs';
                            if (!is_dir($logDir)) {
                                @mkdir($logDir, 0750, true);
                            }
                            
                            // Log to file
                            $logFile = $logDir . '/install_errors_' . date('Y-m-d') . '.log';
                            @error_log($logEntry, 3, $logFile);
                            
                            // Set empty array to continue with fresh installation
                            $_SESSION['existing_tables'] = [];
                        }
                    }
                    
                    // Check if we have existing table information
                    $existingTables = $_SESSION['existing_tables'] ?? [];
                    $missingTables = $_SESSION['missing_tables'] ?? [];
                    $hasExistingTables = !empty($existingTables);
                    
                    $tables = [
                        'customers' => 'Customer information and contacts',
                        'invoices' => 'Invoice records and details', 
                        'invoice_items' => 'Individual line items for invoices',
                        'payments' => 'Payment tracking and history',
                        'customer_properties' => 'Customer locations and properties',
                        'business_settings' => 'Business configuration and settings'
                    ];
                    ?>
                    
                    <?php if ($hasExistingTables): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <p class="text-blue-800 font-medium">Existing database detected! The installer will use existing tables and only create missing ones.</p>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-600 mb-6">The installer will now create the necessary database tables.</p>
                    <?php endif; ?>
                    
                    <div class="space-y-3 mb-6">
                        <h3 class="font-semibold text-gray-900">Database Tables:</h3>
                        <?php foreach ($tables as $table => $desc): ?>
                        <?php 
                        $tableExists = in_array($table, $existingTables);
                        ?>
                        <div class="flex items-center p-3 rounded-lg <?php echo $tableExists ? 'bg-green-50' : 'bg-gray-50'; ?>">
                            <div class="w-6 h-6 rounded flex items-center justify-center mr-3 <?php echo $tableExists ? 'bg-green-600' : 'bg-gray-600'; ?>">
                                <span class="text-white font-bold text-xs"><?php echo $tableExists ? '✓' : 'T'; ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900"><?php echo $table; ?></span>
                                <span class="text-sm text-gray-500 ml-2">- <?php echo $desc; ?></span>
                                <?php if ($tableExists): ?>
                                <span class="ml-2 text-xs text-green-600 font-medium">(Exists)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="flex justify-between">
                            <a href="?step=2" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                Back
                            </a>
                            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                                <?php echo $hasExistingTables ? 'Continue with Database' : 'Install Database'; ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($step == 4): ?>
                <!-- Step 4: Business Configuration -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Business Configuration</h2>
                    <p class="text-gray-600 mb-6">Set up your business information. You can change these settings later.</p>
                    
                    <form method="POST" class="space-y-6">
                        <?php echo getCSRFTokenField(); ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Site Configuration</h3>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Site URL
                                </label>
                                <?php 
                                $detectedUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                                $detectedUrl = rtrim($detectedUrl, '/');
                                ?>
                                <input type="url" name="site_url" value="<?php echo $_POST['site_url'] ?? $detectedUrl; ?>" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                <p class="text-xs text-gray-500 mt-1">This URL will be used for invoice links and system paths</p>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Business Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Business Name
                                    </label>
                                    <input type="text" name="business_name" value="<?php echo $_POST['business_name'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Business Phone
                                    </label>
                                    <input type="tel" name="business_phone" value="<?php echo $_POST['business_phone'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Business Email
                                    </label>
                                    <input type="email" name="business_email" value="<?php echo $_POST['business_email'] ?? ''; ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Business EIN
                                    </label>
                                    <input type="text" name="business_ein" value="<?php echo $_POST['business_ein'] ?? ''; ?>"
                                           placeholder="12-3456789"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Default Rates</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Default Hourly Rate
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="default_hourly_rate" value="<?php echo $_POST['default_hourly_rate'] ?? '45.00'; ?>" 
                                               step="0.01" min="0"
                                               class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Mileage Rate
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="mileage_rate" value="<?php echo $_POST['mileage_rate'] ?? '0.650'; ?>" 
                                               step="0.001" min="0"
                                               class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                                        <span class="absolute right-3 top-2 text-gray-500">per mile</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Payment Instructions
                            </label>
                            <textarea name="payment_instructions" rows="4"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900"><?php echo $_POST['payment_instructions'] ?? 'Payment is due within 30 days. Please contact us for payment methods.'; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">These instructions will appear on all invoices</p>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="?step=3" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                Back
                            </a>
                            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                                Continue
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($step == 5): ?>
                <!-- Step 5: Admin Account -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Admin Account Setup</h2>
                    <p class="text-gray-600 mb-6">Create a secure password for the admin account.</p>
                    
                    <form method="POST" class="space-y-4">
                        <?php echo getCSRFTokenField(); ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Admin Password
                            </label>
                            <input type="password" name="admin_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="text-sm text-yellow-700">
                                <p class="font-semibold">Important Security Notice</p>
                                <p class="mt-1">Choose a strong password and keep it secure. This is the only account that can access the admin panel.</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between">
                            <a href="?step=4" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                Back
                            </a>
                            <button type="submit" class="px-6 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                                Complete Installation
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($step == 6): ?>
                <!-- Step 6: Installation Complete -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                            <span class="text-green-600 text-3xl font-bold">✓</span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Installation Complete!</h2>
                        <p class="text-gray-600 mb-8">Kinvo has been successfully installed and configured.</p>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6 text-left max-w-2xl mx-auto">
                            <h3 class="font-semibold text-green-900 mb-3">Next Steps:</h3>
                            <ul class="space-y-2 text-sm text-green-800">
                                <li class="flex items-start">
                                    <div class="w-4 h-4 rounded-full bg-green-600 flex items-center justify-center mt-0.5 mr-2">
                                        <span class="text-white text-xs font-bold">✓</span>
                                    </div>
                                    <span>Delete the install.php file for security</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-4 h-4 rounded-full bg-green-600 flex items-center justify-center mt-0.5 mr-2">
                                        <span class="text-white text-xs font-bold">✓</span>
                                    </div>
                                    <span>Login to your admin dashboard</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-4 h-4 rounded-full bg-green-600 flex items-center justify-center mt-0.5 mr-2">
                                        <span class="text-white text-xs font-bold">✓</span>
                                    </div>
                                    <span>Add your first customer</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="w-4 h-4 rounded-full bg-green-600 flex items-center justify-center mt-0.5 mr-2">
                                        <span class="text-white text-xs font-bold">✓</span>
                                    </div>
                                    <span>Create your first invoice</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 max-w-2xl mx-auto">
                            <div class="flex items-center">
                                <div class="w-6 h-6 rounded-full bg-red-600 flex items-center justify-center mr-3">
                                    <span class="text-white font-bold text-xs">!</span>
                                </div>
                                <div class="text-left">
                                    <p class="font-semibold text-red-900">Security Warning</p>
                                    <p class="text-sm text-red-700">Please delete install.php immediately to prevent unauthorized access.</p>
                                </div>
                            </div>
                        </div>
                        
                        <a href="admin/login.php" class="inline-flex items-center px-8 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium text-lg">
                            Go to Admin Login
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-4xl mx-auto px-4 py-6 text-center text-sm text-gray-500">
                Kinvo v1.0 - Professional Invoice Management System
            </div>
        </footer>
    </div>
</body>
</html>