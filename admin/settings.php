<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$success = false;
$error = '';

// Get current settings
$businessSettings = getBusinessSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken(); // Validate CSRF token
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
        
        // Check if admin_password column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'admin_password'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN admin_password VARCHAR(255) AFTER payment_instructions");
        }
        
        // Check if remember token columns exist, add them if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'remember_token'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN remember_token VARCHAR(64) NULL AFTER admin_password");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM business_settings LIKE 'remember_expires'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE business_settings ADD COLUMN remember_expires DATETIME NULL AFTER remember_token");
        }
        
        // Check if admin password is empty - admin must set their own password
        $stmt = $pdo->query("SELECT admin_password FROM business_settings LIMIT 1");
        $result = $stmt->fetch();
        if (!$result || empty($result['admin_password'])) {
            // Don't set a default password - force admin to set one through the settings page
            $error = 'Admin password must be set. Please set your password below.';
        }
        
        // Handle password change if provided
        $passwordChanged = false;
        if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
            // Verify current password
            if (!verifyAdminPassword($_POST['current_password'], $pdo)) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Validate new password
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception('New passwords do not match.');
            }
            
            // Use comprehensive password strength validation
            $passwordErrors = validatePasswordStrength($_POST['new_password']);
            if (!empty($passwordErrors)) {
                throw new Exception('Password requirements not met: ' . implode(', ', $passwordErrors));
            }
            
            // Hash the new password
            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $passwordChanged = true;
        }
        
        // Check if settings exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM business_settings");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            if ($passwordChanged) {
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
                    admin_password = ?,
                    updated_at = NOW()
                ");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['business_phone'],
                    $_POST['business_email'],
                    $_POST['business_ein'],
                    $_POST['cashapp_username'],
                    $_POST['venmo_username'],
                    $_POST['default_hourly_rate'],
                    $_POST['mileage_rate'],
                    $_POST['payment_instructions'],
                    $hashedPassword
                ]);
            } else {
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
            }
        } else {
            if ($passwordChanged) {
                $stmt = $pdo->prepare("
                    INSERT INTO business_settings (business_name, business_phone, business_email, business_ein, cashapp_username, venmo_username, default_hourly_rate, mileage_rate, payment_instructions, admin_password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['business_phone'],
                    $_POST['business_email'],
                    $_POST['business_ein'],
                    $_POST['cashapp_username'],
                    $_POST['venmo_username'],
                    $_POST['default_hourly_rate'],
                    $_POST['mileage_rate'],
                    $_POST['payment_instructions'],
                    $hashedPassword
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO business_settings (business_name, business_phone, business_email, business_ein, cashapp_username, venmo_username, default_hourly_rate, mileage_rate, payment_instructions) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
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
            }
        }
        
        $success = $passwordChanged ? "Settings updated successfully! Admin password has been changed." : "Settings updated successfully!";
        
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Business Settings</h2>
            <p class="text-gray-600 mt-1">Configure your business information and payment instructions</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Settings Updated Successfully!</h3>
                    <p class="text-gray-600">Your business settings have been saved and will appear on all new invoices.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Error Updating Settings</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Settings Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-building mr-3 text-gray-600"></i>
                            Business Information
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <?php echo getCSRFTokenField(); ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-store mr-1"></i>Business Name *
                                </label>
                                <input type="text" name="business_name" required
                                       value="<?php echo htmlspecialchars($businessSettings['business_name']); ?>"
                                       placeholder="Your Business Name"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                <p class="mt-1 text-sm text-gray-500">This will appear prominently on all invoices</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-phone mr-1"></i>Business Phone *
                                </label>
                                <input type="tel" name="business_phone" required
                                       value="<?php echo htmlspecialchars($businessSettings['business_phone']); ?>"
                                       placeholder="(555) 123-4567"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
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
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
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
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <p class="mt-1 text-sm text-gray-500">Employer Identification Number - will appear on invoices if provided</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-mobile-alt mr-1"></i>Cash App Username (Optional)
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="text" name="cashapp_username"
                                           value="<?php echo htmlspecialchars($businessSettings['cashapp_username'] ?? ''); ?>"
                                           placeholder="username"
                                           maxlength="50"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Creates direct payment link: cash.app/$username</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fab fa-venmo mr-1"></i>Venmo Username (Optional)
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">@</span>
                                    <input type="text" name="venmo_username"
                                           value="<?php echo htmlspecialchars($businessSettings['venmo_username'] ?? ''); ?>"
                                           placeholder="username"
                                           maxlength="50"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
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
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="number" name="default_hourly_rate" step="0.01" min="0" required
                                           value="<?php echo htmlspecialchars($businessSettings['default_hourly_rate'] ?? '45.00'); ?>"
                                           placeholder="45.00"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Default rate per hour - can be overridden per customer</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-car mr-1"></i>Mileage Rate *
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                    <input type="number" name="mileage_rate" step="0.001" min="0" required
                                           value="<?php echo htmlspecialchars($businessSettings['mileage_rate'] ?? '0.650'); ?>"
                                           placeholder="0.650"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                    <span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">per mile</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Standard IRS rate is $0.650 per mile (2023)</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-credit-card mr-1"></i>Payment Instructions *
                            </label>
                            <textarea name="payment_instructions" rows="6" required
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all resize-none"
                                      placeholder="Example: Payment is due within 7 days. Please pay via:&#10;• Zelle: 555-123-4567&#10;• Venmo: @yourusername&#10;• Cash App: $yourusername"><?php echo htmlspecialchars($businessSettings['payment_instructions']); ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                These instructions will appear on every invoice. Include your preferred payment methods with account details.
                            </p>
                        </div>

                        <!-- Password Change Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-lock mr-3 text-gray-600"></i>
                                Change Admin Password
                            </h4>
                            <p class="text-sm text-gray-600 mb-4">Leave blank to keep current password unchanged.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Current Password
                                    </label>
                                    <input type="password" name="current_password"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                                           placeholder="Enter current password">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        New Password
                                    </label>
                                    <input type="password" name="new_password"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                                           placeholder="Enter new password">
                                    <p class="mt-1 text-sm text-gray-500">Minimum 6 characters</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Confirm New Password
                                    </label>
                                    <input type="password" name="confirm_password"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                                           placeholder="Confirm new password">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="dashboard.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="submit" class="px-8 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Panel -->
            <div class="space-y-6">
                <!-- Tips -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-info-circle mr-3 text-gray-600"></i>
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
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-white rounded-lg shadow-sm border border-red-200 overflow-hidden">
                    <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                        <h3 class="text-lg font-semibold text-red-900 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-3 text-red-600"></i>
                            Danger Zone
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div class="pb-6 border-b border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-2">Estimates Feature Management</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    The estimates feature allows you to create quotes that can be converted to invoices. If you no longer need this feature, you can remove it completely.
                                </p>
                                <button type="button" onclick="confirmRemoveEstimates()" 
                                   class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                                    <i class="fas fa-file-invoice mr-2"></i>
                                    Remove Estimates Feature
                                </button>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Reset Installation</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    Permanently delete all data and start fresh. This action cannot be undone.
                                </p>
                                <a href="reset-installation.php" 
                                   class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                                    <i class="fas fa-trash-alt mr-2"></i>
                                    Reset Installation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script>
    function confirmRemoveEstimates() {
        // Get estimate count first
        fetch('ajax/get-estimates-count.php')
            .then(response => response.json())
            .then(data => {
                const message = data.count > 0 
                    ? `Are you sure you want to remove the estimates feature? This will permanently delete ${data.count} estimate(s) and cannot be undone.` 
                    : 'Are you sure you want to remove the estimates feature? This action cannot be undone.';
                
                if (confirm(message)) {
                    const confirmText = prompt('Type "DELETE ESTIMATES" to confirm:');
                    if (confirmText === 'DELETE ESTIMATES') {
                        // Remove estimates
                        fetch('ajax/remove-estimates.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                confirm: true
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('Estimates feature has been removed successfully.');
                                window.location.reload();
                            } else {
                                alert('Error removing estimates: ' + (result.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            alert('Error: ' + error);
                        });
                    }
                }
            })
            .catch(error => {
                if (confirm('Are you sure you want to remove the estimates feature? This action cannot be undone.')) {
                    const confirmText = prompt('Type "DELETE ESTIMATES" to confirm:');
                    if (confirmText === 'DELETE ESTIMATES') {
                        // Proceed with removal even if count check fails
                        fetch('ajax/remove-estimates.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                confirm: true
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('Estimates feature has been removed successfully.');
                                window.location.reload();
                            } else {
                                alert('Error removing estimates: ' + (result.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            alert('Error: ' + error);
                        });
                    }
                }
            });
    }
    </script>
</body>
</html>