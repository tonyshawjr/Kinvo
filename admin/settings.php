<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$success = false;
$error = '';

// Get current settings
$businessSettings = getBusinessSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if business_ein column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'business_ein'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN business_ein VARCHAR(20) AFTER business_email");
        }
        
        // Check if cashapp_username column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'cashapp_username'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN cashapp_username VARCHAR(50) AFTER business_ein");
        }
        
        // Check if venmo_username column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'venmo_username'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN venmo_username VARCHAR(50) AFTER cashapp_username");
        }
        
        // Check if default_hourly_rate column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'default_hourly_rate'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN default_hourly_rate DECIMAL(8,2) DEFAULT 45.00 AFTER venmo_username");
        }
        
        // Check if mileage_rate column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'mileage_rate'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN mileage_rate DECIMAL(5,3) DEFAULT 0.650 AFTER default_hourly_rate");
        }
        
        // Check if settings exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM business_settings");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE business_settings SET 
                business_name = ?, 
                business_phone = ?, 
                business_email = ?, 
                business_ein = ?,
                cashapp_username = ?,
                venmo_username = ?,
                default_hourly_rate = ?,
                mileage_rate = ?,
                payment_instructions = ?,
                updated_at = NOW()
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO business_settings (business_name, business_phone, business_email, business_ein, cashapp_username, venmo_username, default_hourly_rate, mileage_rate, payment_instructions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        $stmt->execute([
            $_POST['business_name'],
            $_POST['business_phone'],
            $_POST['business_email'],
            $_POST['business_ein'],
            $_POST['cashapp_username'],
            $_POST['venmo_username'],
            $_POST['default_hourly_rate'],
            $_POST['mileage_rate'],
            $_POST['payment_instructions']
        ]);
        
        $success = true;
        
        // Refresh settings
        $businessSettings = getBusinessSettings($pdo);
        
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Settings<?php 
    $businessSettings = getBusinessSettings($pdo);
    $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
        ? ' - ' . $businessSettings['business_name'] 
        : '';
    echo htmlspecialchars($appName);
    ?></title>
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
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Business Settings</h2>
            <p class="text-gray-600 mt-1">Configure your business information and payment instructions</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Settings Updated Successfully!</h3>
                    <p class="text-green-700">Your business settings have been saved and will appear on all new invoices.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Updating Settings</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Settings Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-building mr-3 text-blue-600"></i>
                            Business Information
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-store mr-1"></i>Business Name *
                                </label>
                                <input type="text" name="business_name" required
                                       value="<?php echo htmlspecialchars($businessSettings['business_name']); ?>"
                                       placeholder="Your Business Name"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                <p class="mt-1 text-sm text-gray-500">This will appear prominently on all invoices</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-phone mr-1"></i>Business Phone *
                                </label>
                                <input type="tel" name="business_phone" required
                                       value="<?php echo htmlspecialchars($businessSettings['business_phone']); ?>"
                                       placeholder="(555) 123-4567"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                <p class="mt-1 text-sm text-gray-500">Include area code for professional appearance</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1"></i>Business Email *
                            </label>
                            <input type="email" name="business_email" required
                                   value="<?php echo htmlspecialchars($businessSettings['business_email']); ?>"
                                   placeholder="business@example.com"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <p class="mt-1 text-sm text-gray-500">Contact email for customer inquiries and support</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-1"></i>Business EIN (Optional)
                            </label>
                            <input type="text" name="business_ein"
                                   value="<?php echo htmlspecialchars($businessSettings['business_ein'] ?? ''); ?>"
                                   placeholder="12-3456789"
                                   maxlength="20"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <p class="mt-1 text-sm text-gray-500">Employer Identification Number - will appear on invoices if provided</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-mobile-alt mr-1"></i>Cash App Username (Optional)
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="text" name="cashapp_username"
                                           value="<?php echo htmlspecialchars($businessSettings['cashapp_username'] ?? ''); ?>"
                                           placeholder="username"
                                           maxlength="50"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Creates direct payment link: cash.app/$username</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fab fa-venmo mr-1"></i>Venmo Username (Optional)
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">@</span>
                                    <input type="text" name="venmo_username"
                                           value="<?php echo htmlspecialchars($businessSettings['venmo_username'] ?? ''); ?>"
                                           placeholder="username"
                                           maxlength="50"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Creates direct payment link with invoice amount pre-filled</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-clock mr-1"></i>Default Hourly Rate *
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="number" name="default_hourly_rate" step="0.01" min="0" required
                                           value="<?php echo htmlspecialchars($businessSettings['default_hourly_rate'] ?? '45.00'); ?>"
                                           placeholder="45.00"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Default rate per hour - can be overridden per customer</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-car mr-1"></i>Mileage Rate *
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="number" name="mileage_rate" step="0.001" min="0" required
                                           value="<?php echo htmlspecialchars($businessSettings['mileage_rate'] ?? '0.650'); ?>"
                                           placeholder="0.650"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                    <span class="inline-flex items-center px-3 rounded-r-xl border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">per mile</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Standard IRS rate is $0.650 per mile (2023)</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-credit-card mr-1"></i>Payment Instructions *
                            </label>
                            <textarea name="payment_instructions" rows="6" required
                                      class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all resize-none"
                                      placeholder="Example: Payment is due within 7 days. Please pay via:&#10;• Zelle: 555-123-4567&#10;• Venmo: @yourusername&#10;• Cash App: $yourusername"><?php echo htmlspecialchars($businessSettings['payment_instructions']); ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                These instructions will appear on every invoice. Include your preferred payment methods with account details.
                            </p>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="dashboard.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="submit" class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all font-medium shadow-lg">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="space-y-6">
                <!-- Tips -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-lightbulb mr-3 text-purple-600"></i>
                            Pro Tips
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                <p class="text-sm text-gray-700">Use a professional business name that customers will recognize</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-pink-500 rounded-full mt-2 flex-shrink-0"></div>
                                <p class="text-sm text-gray-700">Include multiple payment options to make it easier for customers to pay</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                <p class="text-sm text-gray-700">Be specific with payment details (account numbers, usernames, etc.)</p>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-pink-500 rounded-full mt-2 flex-shrink-0"></div>
                                <p class="text-sm text-gray-700">Test your payment methods to ensure they work correctly</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-info-circle mr-3 text-blue-600"></i>
                            System Information
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">PHP Version:</span>
                                <span class="text-gray-900 font-medium"><?php echo PHP_VERSION; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Database:</span>
                                <span class="text-gray-900 font-medium">MySQL/MariaDB</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Site URL:</span>
                                <span class="text-gray-900 font-medium"><?php echo SITE_URL; ?></span>
                            </div>
                            <div class="border-t pt-4 mt-4">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <div class="flex items-start space-x-2">
                                        <i class="fas fa-key text-yellow-600 mt-0.5"></i>
                                        <div>
                                            <p class="text-sm font-medium text-yellow-800">Admin Password</p>
                                            <p class="text-xs text-yellow-700">Change in includes/config.php</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Setup -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-database mr-3 text-green-600"></i>
                            Database Setup
                        </h3>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-700 mb-4 text-sm">
                            If you haven't set up your database yet, import the schema file into your MySQL database.
                        </p>
                        <div class="bg-gray-100 p-4 rounded-xl">
                            <code class="text-sm text-gray-800 break-all">
                                mysql -u <?php echo DB_USER; ?> -p <?php echo DB_NAME; ?> < database_schema.sql
                            </code>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Run this command from your project directory after creating the database.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>