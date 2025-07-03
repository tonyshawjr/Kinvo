<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$customerId = $_GET['id'] ?? null;
$success = false;
$error = '';

if (!$customerId) {
    header('Location: customers.php');
    exit;
}

// Get customer information
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if custom_hourly_rate column exists, add it if missing
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'custom_hourly_rate'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN custom_hourly_rate DECIMAL(8,2) NULL AFTER phone");
        }
        
        // Check if updated_at column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'updated_at'");
        $hasUpdatedAt = $stmt->rowCount() > 0;
        
        if ($hasUpdatedAt) {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, email = ?, phone = ?, custom_hourly_rate = ?, updated_at = NOW() 
                WHERE id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET name = ?, email = ?, phone = ?, custom_hourly_rate = ? 
                WHERE id = ?
            ");
        }
        
        $customHourlyRate = !empty($_POST['custom_hourly_rate']) ? $_POST['custom_hourly_rate'] : null;
        
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $customHourlyRate,
            $customerId
        ]);
        
        $success = true;
        
        // Refresh customer data
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = "Error updating customer: " . $e->getMessage();
    }
}

// Get customer statistics for context
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(invoice_stats.total_invoices, 0) as total_invoices,
        COALESCE(invoice_stats.total_invoiced, 0) as total_invoiced,
        COALESCE(payment_stats.total_paid, 0) as total_paid
    FROM (SELECT 1) dummy
    LEFT JOIN (
        SELECT 
            COUNT(*) as total_invoices,
            SUM(total) as total_invoiced
        FROM invoices 
        WHERE customer_id = ?
    ) invoice_stats ON 1=1
    LEFT JOIN (
        SELECT 
            SUM(p.amount) as total_paid
        FROM invoices i
        JOIN payments p ON i.id = p.invoice_id
        WHERE i.customer_id = ?
    ) payment_stats ON 1=1
");
$stmt->execute([$customerId, $customerId]);
$stats = $stmt->fetch();

// Get business settings for default hourly rate
$businessSettings = getBusinessSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer - <?php echo htmlspecialchars($customer['name']); ?><?php 
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
            <div class="flex items-center space-x-4">
                <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Edit Customer</h2>
                    <p class="text-gray-600 mt-1">Update customer information and contact details</p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Customer Updated Successfully!</h3>
                    <p class="text-green-700">The customer information has been saved.</p>
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
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Updating Customer</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Edit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-user-edit mr-3 text-blue-600"></i>
                            Customer Information
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-1"></i>Customer Name *
                            </label>
                            <input type="text" name="name" required
                                   value="<?php echo htmlspecialchars($customer['name']); ?>"
                                   placeholder="Customer's full name"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <p class="mt-1 text-sm text-gray-500">The name that will appear on invoices</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1"></i>Email Address
                            </label>
                            <input type="email" name="email"
                                   value="<?php echo htmlspecialchars($customer['email']); ?>"
                                   placeholder="customer@example.com"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <p class="mt-1 text-sm text-gray-500">Optional - for sending invoices and communication</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone mr-1"></i>Phone Number
                            </label>
                            <input type="tel" name="phone"
                                   value="<?php echo htmlspecialchars($customer['phone']); ?>"
                                   placeholder="(555) 123-4567"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            <p class="mt-1 text-sm text-gray-500">Optional - for follow-ups and communication</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-1"></i>Custom Hourly Rate (Optional)
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">$</span>
                                <input type="number" name="custom_hourly_rate" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($customer['custom_hourly_rate'] ?? ''); ?>"
                                       placeholder="<?php echo htmlspecialchars($businessSettings['default_hourly_rate']); ?>"
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-r-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Leave empty to use default rate ($<?php echo htmlspecialchars($businessSettings['default_hourly_rate']); ?>/hr). 
                                Override only if this customer has a special rate.
                            </p>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="submit" class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all font-medium shadow-lg">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customer Info Sidebar -->
            <div class="space-y-6">
                <!-- Customer Stats -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-bar mr-3 text-purple-600"></i>
                            Customer Statistics
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Invoices:</span>
                                <span class="font-semibold text-gray-900"><?php echo $stats['total_invoices']; ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Invoiced:</span>
                                <span class="font-semibold text-gray-900"><?php echo formatCurrency($stats['total_invoiced']); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Paid:</span>
                                <span class="font-semibold text-green-600"><?php echo formatCurrency($stats['total_paid']); ?></span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Outstanding:</span>
                                    <span class="font-semibold text-red-600"><?php echo formatCurrency($stats['total_invoiced'] - $stats['total_paid']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Timeline -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-clock mr-3 text-green-600"></i>
                            Customer Timeline
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Customer added:</span>
                                <span class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></span>
                            </div>
                            <?php if (isset($customer['updated_at']) && $customer['updated_at'] && $customer['updated_at'] !== $customer['created_at']): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last updated:</span>
                                <span class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($customer['updated_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <?php if ($stats['total_invoices'] == 0): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-red-50 to-pink-50 px-6 py-4 border-b border-red-200">
                        <h3 class="text-lg font-semibold text-red-900 flex items-center">
                            <i class="fas fa-exclamation-triangle mr-3 text-red-600"></i>
                            Danger Zone
                        </h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-700 mb-4">
                            This customer has no invoices. You can safely delete this customer if needed.
                        </p>
                        <button onclick="deleteCustomer()" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                            <i class="fas fa-trash mr-2"></i>Delete Customer
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-shield-alt mr-3 text-gray-600"></i>
                            Protected Customer
                        </h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-gray-700">
                            This customer has invoices and cannot be deleted. You can only update their information.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2 text-blue-600"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="create-invoice.php?customer_id=<?php echo $customer['id']; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-blue-700">Create New Invoice</span>
                        </a>
                        <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-eye text-blue-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-blue-700">View Customer Details</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function deleteCustomer() {
            if (confirm(`Are you sure you want to delete customer "${<?php echo json_encode($customer['name']); ?>}"? This action cannot be undone.`)) {
                window.location.href = `customer-delete.php?id=<?php echo $customer['id']; ?>`;
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>