<?php
session_start();
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(false, true);

requireClientLogin();

$customer_id = $_SESSION['client_id'];
$customer_name = $_SESSION['client_name'];
$success = '';
$error = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    try {
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Name is required.');
            }
            
            // Update customer information
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $customer_id]);
            
            // Update session
            $_SESSION['client_name'] = $name;
            
            logClientActivity($pdo, $customer_id, 'profile_updated', 'Profile information updated');
            $success = 'Profile updated successfully!';
        }
        
        if (isset($_POST['update_preferences'])) {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $invoice_reminders = isset($_POST['invoice_reminders']) ? 1 : 0;
            $payment_confirmations = isset($_POST['payment_confirmations']) ? 1 : 0;
            
            // Update or insert preferences
            $stmt = $pdo->prepare("
                INSERT INTO client_preferences (customer_id, email_notifications, invoice_reminders, payment_confirmations) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                invoice_reminders = VALUES(invoice_reminders),
                payment_confirmations = VALUES(payment_confirmations)
            ");
            $stmt->execute([$customer_id, $email_notifications, $invoice_reminders, $payment_confirmations]);
            
            logClientActivity($pdo, $customer_id, 'preferences_updated', 'Notification preferences updated');
            $success = 'Preferences updated successfully!';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Get preferences
$stmt = $pdo->prepare("SELECT * FROM client_preferences WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$preferences = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT * FROM client_activity 
    WHERE customer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$customer_id]);
$recent_activity = $stmt->fetchAll();

$businessSettings = getBusinessSettings($pdo);

// Log activity
logClientActivity($pdo, $customer_id, 'profile_view', 'Viewed profile page');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Profile Settings</h2>
                <p class="text-gray-600 mt-1">Manage your account information and preferences</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-green-900">Success</h4>
                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-exclamation-triangle text-red-600 mt-1"></i>
                    <div>
                        <h4 class="font-semibold text-red-900">Error</h4>
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-user mr-3 text-gray-600"></i>
                                Personal Information
                            </h3>
                        </div>
                        
                        <form method="POST" class="p-6 space-y-6">
                            <?php echo getCSRFTokenField(); ?>
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($customer['name']); ?>"
                                       required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       value="<?php echo htmlspecialchars($customer['email']); ?>"
                                       disabled
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-500">
                                <p class="mt-1 text-xs text-gray-500">Email cannot be changed. Contact support if needed.</p>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number
                                </label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit" name="update_profile" class="px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                                    <i class="fas fa-save mr-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mt-8">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-bell mr-3 text-gray-600"></i>
                                Notification Preferences
                            </h3>
                        </div>
                        
                        <form method="POST" class="p-6">
                            <?php echo getCSRFTokenField(); ?>
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="email_notifications" 
                                           name="email_notifications" 
                                           <?php echo ($preferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                    <label for="email_notifications" class="ml-3 block text-sm text-gray-900">
                                        Email notifications
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="invoice_reminders" 
                                           name="invoice_reminders" 
                                           <?php echo ($preferences['invoice_reminders'] ?? 1) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                    <label for="invoice_reminders" class="ml-3 block text-sm text-gray-900">
                                        Invoice reminders
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="payment_confirmations" 
                                           name="payment_confirmations" 
                                           <?php echo ($preferences['payment_confirmations'] ?? 1) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-gray-600 focus:ring-gray-500 border-gray-300 rounded">
                                    <label for="payment_confirmations" class="ml-3 block text-sm text-gray-900">
                                        Payment confirmations
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200 mt-6">
                                <button type="submit" name="update_preferences" class="px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                                    <i class="fas fa-save mr-2"></i>Update Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Account Summary -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                            Account Summary
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Customer Since:</span>
                                <span class="font-medium"><?php echo date('M Y', strtotime($customer['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Customer ID:</span>
                                <span class="font-medium">#<?php echo $customer['id']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-history mr-2 text-gray-600"></i>
                            Recent Activity
                        </h3>
                        <div class="space-y-3">
                            <?php foreach (array_slice($recent_activity, 0, 5) as $activity): ?>
                                <div class="text-sm">
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($activity['action']); ?></p>
                                    <p class="text-gray-500"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($recent_activity)): ?>
                                <p class="text-sm text-gray-500">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>