<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerName = trim($_POST['customer_name']);
        $customerEmail = trim($_POST['customer_email']);
        $customerPhone = trim($_POST['customer_phone']);
        
        // Validate required fields
        if (empty($customerName)) {
            throw new Exception('Customer name is required.');
        }
        
        // Check if customer already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ?");
        $stmt->execute([$customerName]);
        if ($stmt->fetch()) {
            throw new Exception('A customer with this name already exists.');
        }
        
        // Create customer
        $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$customerName, $customerEmail, $customerPhone]);
        
        $success = true;
        $customerId = $pdo->lastInsertId();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get business settings
$businessSettings = getBusinessSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Customer<?php 
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

    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Create New Customer</h2>
                <p class="text-gray-600 mt-1">Add a new customer to your system</p>
            </div>
            <a href="customers.php" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Customers
            </a>
        </div>

        <?php if ($success): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Customer Created Successfully!</h3>
                    <p class="text-green-700 mb-4">Customer "<?php echo htmlspecialchars($_POST['customer_name']); ?>" has been added to your system.</p>
                    <div class="flex gap-4">
                        <a href="create-invoice.php?customer_id=<?php echo $customerId; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                            <i class="fas fa-file-invoice mr-2"></i>Create Invoice
                        </a>
                        <a href="customers.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium">
                            <i class="fas fa-list mr-2"></i>View All Customers
                        </a>
                        <a href="create-customer.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>Add Another Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Creating Customer</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user-plus mr-3 text-blue-600"></i>
                    Customer Information
                </h3>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Customer Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="customer_name" 
                           name="customer_name" 
                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                           required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                           placeholder="Enter customer name">
                </div>

                <div>
                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address
                    </label>
                    <input type="email" 
                           id="customer_email" 
                           name="customer_email" 
                           value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                           placeholder="Enter email address">
                </div>

                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number
                    </label>
                    <input type="tel" 
                           id="customer_phone" 
                           name="customer_phone" 
                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                           placeholder="Enter phone number">
                </div>

                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="customers.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors font-medium">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all font-medium">
                        <i class="fas fa-save mr-2"></i>Create Customer
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>