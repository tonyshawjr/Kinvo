<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken(); // Validate CSRF token
    try {
        // Validate customer data using comprehensive validation
        $validatedData = validateCustomerData($_POST);
        
        $customerName = $validatedData['name'];
        $customerEmail = $validatedData['email'];
        $customerPhone = $validatedData['phone'];
        
        // Check if customer already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = ?");
        $stmt->execute([$customerName]);
        if ($stmt->fetch()) {
            throw new Exception('A customer with this name already exists.');
        }
        
        // Create customer
        $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$customerName, $customerEmail, $customerPhone]);
        
        $customerId = $pdo->lastInsertId();
        
        // Create client portal access if email provided
        $clientPin = null;
        if (!empty($customerEmail)) {
            $clientPin = generateClientPIN();
            $created = createClientAuth($pdo, $customerId, $customerEmail, $clientPin);
            
            if ($created) {
                // Create default preferences
                $stmt = $pdo->prepare("INSERT INTO client_preferences (customer_id) VALUES (?)");
                $stmt->execute([$customerId]);
                
                // Log the portal creation
                logClientActivity($pdo, $customerId, 'account_created', 'Client portal access created by admin');
            }
        }
        
        $success = true;
        
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        handleDatabaseError('customer creation', $e, 'customer management');
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Create New Customer</h2>
                <p class="text-gray-600 mt-1">Add a new customer to your system</p>
            </div>
            <a href="customers.php" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                <i class="fas fa-arrow-left mr-2"></i>Back to Customers
            </a>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Customer Created Successfully!</h3>
                    <p class="text-gray-600 mb-4">Customer "<?php echo htmlspecialchars($_POST['name']); ?>" has been added to your system.</p>
                    
                    <?php if ($clientPin): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-user-shield text-blue-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold text-blue-900">Client Portal Access Created</h4>
                                <p class="text-sm text-blue-700 mt-1">
                                    A client portal has been created for this customer.
                                </p>
                                <div class="mt-2 p-3 bg-white rounded border border-blue-200">
                                    <p class="text-sm"><strong>Email:</strong> <?php echo htmlspecialchars($_POST['email']); ?></p>
                                    <p class="text-sm"><strong>PIN:</strong> <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?php echo $clientPin; ?></span></p>
                                    <p class="text-sm"><strong>Login URL:</strong> <a href="/client/login.php?email=<?php echo urlencode($_POST['email']); ?>" class="text-blue-600 hover:text-blue-500" target="_blank">/client/login.php</a></p>
                                </div>
                                <p class="text-xs text-blue-600 mt-2">
                                    <i class="fas fa-info-circle"></i> 
                                    Send these credentials to your customer so they can access their invoices and payment history.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-4">
                        <a href="create-invoice.php?customer_id=<?php echo $customerId; ?>" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-semibold">
                            <i class="fas fa-file-invoice mr-2"></i>Create Invoice
                        </a>
                        <a href="customers.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-semibold">
                            <i class="fas fa-list mr-2"></i>View All Customers
                        </a>
                        <a href="create-customer.php" class="inline-flex items-center px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-semibold">
                            <i class="fas fa-plus mr-2"></i>Add Another Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Error Creating Customer</h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user-plus mr-3 text-gray-600"></i>
                    Customer Information
                </h3>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <?php echo getCSRFTokenField(); ?>
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Customer Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="customer_name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           required 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                           placeholder="Enter customer name">
                </div>

                <div>
                    <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-2">
                        Email Address
                    </label>
                    <input type="email" 
                           id="customer_email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                           placeholder="Enter email address">
                </div>

                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number
                    </label>
                    <input type="tel" 
                           id="customer_phone" 
                           name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all"
                           placeholder="Enter phone number">
                </div>

                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="customers.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-save mr-2"></i>Create Customer
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>