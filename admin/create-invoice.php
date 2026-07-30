<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/service-functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$success = false;
$error = '';

// Get all customers for dropdown with their hourly rates
$stmt = $pdo->query("SELECT id, name, custom_hourly_rate FROM customers ORDER BY name");
$customers = $stmt->fetchAll();

// Pre-selected customer and property from URL parameters
$selectedCustomerId = $_GET['customer_id'] ?? null;
$selectedPropertyId = $_GET['property_id'] ?? null;

// Get properties for selected customer if any
$customerProperties = [];
if ($selectedCustomerId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer_properties WHERE customer_id = ? AND is_active = 1 ORDER BY property_name");
        $stmt->execute([$selectedCustomerId]);
        $customerProperties = $stmt->fetchAll();
    } catch (Exception $e) {
        // Properties table might not exist yet
    }
}

// Get business settings
$businessSettings = getBusinessSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken(); // Validate CSRF token
    try {
        $pdo->beginTransaction();
        
        // Handle customer - create new or use existing
        $customerType = validateSelect($_POST['customer_type'] ?? '', ['new', 'existing'], 'Customer Type', true);
        
        if ($customerType === 'new') {
            // Validate new customer data
            $validatedCustomerData = validateCustomerData($_POST);
            
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)");
            $stmt->execute([
                $validatedCustomerData['name'],
                $validatedCustomerData['email'],
                $validatedCustomerData['phone']
            ]);
            $customerId = $pdo->lastInsertId();
        } else {
            $customerId = validateInteger($_POST['customer_id'] ?? '', 'Customer ID', true, 1);
        }
        
        // Calculate totals
        $subtotal = 0;
        $items = [];
        
        // Validate line items
        if (!isset($_POST['item_description']) || !is_array($_POST['item_description'])) {
            throw new InvalidArgumentException('Invoice must have at least one line item.');
        }
        
        foreach ($_POST['item_description'] as $index => $description) {
            if (!empty($description)) {
                // Validate each line item
                $validatedDescription = validateAndSanitizeString($description, 500, "Item {$index} description", true);
                $validatedQuantity = validateCurrency($_POST['item_quantity'][$index] ?? '', "Item {$index} quantity", true);
                $validatedUnitPrice = validateCurrency($_POST['item_price'][$index] ?? '', "Item {$index} price", true, true);
                
                $lineTotal = $validatedQuantity * $validatedUnitPrice;
                $subtotal += $lineTotal;
                
                $items[] = [
                    'description' => $validatedDescription,
                    'quantity' => $validatedQuantity,
                    'unit_price' => $validatedUnitPrice,
                    'total' => $lineTotal
                ];
            }
        }
        
        if (empty($items)) {
            throw new InvalidArgumentException('Invoice must have at least one line item.');
        }
        
        // Validate tax rate
        $taxRate = validateCurrency($_POST['tax_rate'] ?? '0', 'Tax Rate', false, true);
        if ($taxRate > 100) {
            throw new InvalidArgumentException('Tax rate cannot exceed 100%.');
        }
        
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;
        
        // Create invoice
        $invoiceNumber = generateInvoiceNumber($pdo);
        $uniqueId = generateUniqueId();
        
        // Check if property_id column exists, add it if missing
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE invoices ADD COLUMN property_id INT NULL AFTER customer_id");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
        
        $propertyId = !empty($_POST['property_id']) ? $_POST['property_id'] : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO invoices (customer_id, property_id, invoice_number, date, due_date, subtotal, tax_rate, tax_amount, total, notes, unique_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $customerId,
            $propertyId,
            $invoiceNumber,
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $taxRate,
            $taxAmount,
            $total,
            $_POST['notes'],
            $uniqueId
        ]);
        
        $invoiceId = $pdo->lastInsertId();
        
        // Insert line items
        $stmt = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $invoiceId,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['total']
            ]);
        }

        rememberServiceUsage($pdo, array_column($items, 'description'));

        if (!empty($_POST['save_new_jobs'])) {
            foreach ($items as $item) {
                createServiceIfMissing($pdo, $item['description'], $item['unit_price']);
            }
        }
        
        $pdo->commit();
        $success = true;
        $invoiceUrl = "/public/view-invoice.php?id=" . $uniqueId;
        
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        handleDatabaseError('invoice creation', $e, 'invoice management');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice<?php 
    $businessSettings = getBusinessSettings($pdo);
    $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
        ? ' - ' . $businessSettings['business_name'] 
        : '';
    echo htmlspecialchars($appName);
    ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Enhanced mobile touch targets */
        @media (max-width: 640px) {
            .line-item input[type="number"],
            .line-item input[type="text"] {
                min-height: 48px;
            }
            .line-item button {
                min-height: 48px;
                min-width: 48px;
            }
            /* Improve form readability on mobile */
            .line-item {
                margin-bottom: 1.5rem;
            }
            /* Better spacing for mobile buttons */
            .mobile-action-buttons button {
                min-height: 48px;
                font-size: 0.875rem;
            }
        }
        
        /* Visual feedback for touch interactions */
        .touch-feedback:active {
            transform: scale(0.98);
            transition: transform 0.1s ease;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-6 sm:py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Create New Invoice</h2>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">Generate a professional invoice for your customer</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-700 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Invoice Created Successfully!</h3>
                    <p class="text-green-700 mb-4">Invoice Number: <strong><?php echo htmlspecialchars($invoiceNumber); ?></strong></p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?php echo htmlspecialchars($invoiceUrl); ?>" class="inline-flex items-center px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-eye mr-2"></i>View Invoice
                        </a>
                        <a href="create-invoice.php" class="inline-flex items-center px-4 py-2 bg-white text-green-700 border border-green-300 rounded-lg hover:bg-green-50 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Create Another
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
                    <i class="fas fa-exclamation-circle text-red-700 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Creating Invoice</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 sm:space-y-8">
            <?php echo getCSRFTokenField(); ?>
            <!-- Customer Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-gray-600"></i>
                        Customer Information
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Customer Type</label>
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-6">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="customer_type" value="existing" checked onchange="toggleCustomerFields()" class="w-6 h-6 text-gray-700 border-gray-400 focus:ring-gray-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">Existing Customer</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="customer_type" value="new" onchange="toggleCustomerFields()" class="w-6 h-6 text-gray-700 border-gray-400 focus:ring-gray-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">New Customer</span>
                            </label>
                        </div>
                    </div>

                    <div id="existing-customer">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Customer</label>
                                <select name="customer_id" id="customer-select" aria-label="Customer" onchange="loadCustomerData()" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                    <option value="">Choose a customer...</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            data-hourly-rate="<?php echo htmlspecialchars($customer['custom_hourly_rate'] ?? ''); ?>"
                                            <?php echo $selectedCustomerId == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                        <?php if ($customer['custom_hourly_rate']): ?>
                                            ($<?php echo htmlspecialchars($customer['custom_hourly_rate']); ?>/hr)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="property-selection" style="<?php echo $selectedCustomerId ? 'display: block;' : 'display: none;'; ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Property/Location (Optional)</label>
                                <select name="property_id" id="property-select" onchange="onPropertyChange()" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                    <option value="">No specific property</option>
                                    <?php foreach ($customerProperties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" <?php echo $selectedPropertyId == $property['id'] ? 'selected' : ''; ?>>
                                        <?php 
                                        if (!empty($property['address'])) {
                                            // Show address, replacing line breaks with commas for dropdown display
                                            $address = str_replace(["\r\n", "\n", "\r"], ', ', $property['address']);
                                            // Truncate if longer than 30 characters
                                            if (strlen($address) > 30) {
                                                echo htmlspecialchars(substr($address, 0, 27) . '...');
                                            } else {
                                                echo htmlspecialchars($address);
                                            }
                                        } else {
                                            // Fallback to property name if no address
                                            echo htmlspecialchars($property['property_name']);
                                        }
                                        ?>
                                        <?php if ($property['property_type'] !== 'Other'): ?>
                                            (<?php echo htmlspecialchars($property['property_type']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-sm text-gray-700">Select the property where work will be performed</p>
                            </div>
                        </div>
                    </div>

                    <div id="new-customer" class="grid grid-cols-1 md:grid-cols-3 gap-6" style="display: none;">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="name" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="Customer name" id="name">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="customer@email.com" id="email">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="tel" name="phone" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="(555) 123-4567" id="phone">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-alt mr-3 text-purple-600"></i>
                        Invoice Details
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="invoice-date" class="block text-sm font-medium text-gray-700 mb-2">Invoice Date *</label>
                            <input type="date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" id="invoice-date">
                        </div>
                        <div>
                            <label for="due-date" class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                            <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" id="due-date">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-4 sm:px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between space-y-3 sm:space-y-0">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-list mr-3 text-green-700"></i>
                            Line Items
                        </h3>
                        <div class="flex flex-col sm:flex-row w-full sm:w-auto space-y-2 sm:space-y-0 sm:space-x-2 mobile-action-buttons">
                            <button type="button" onclick="addLaborItem()" class="inline-flex items-center justify-center px-4 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-semibold touch-feedback">
                                <i class="fas fa-clock mr-2"></i>Add Labor
                            </button>
                            <button type="button" onclick="addMileageItem()" class="inline-flex items-center justify-center px-4 py-3 bg-orange-700 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium touch-feedback">
                                <i class="fas fa-car mr-2"></i>Add Mileage
                            </button>
                            <button type="button" onclick="addMaterialItem()" class="inline-flex items-center justify-center px-4 py-3 bg-green-700 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium touch-feedback">
                                <i class="fas fa-plus mr-2"></i>Add Material
                            </button>
                        </div>
                    </div>
                </div>
                <div id="repeat-last" class="px-4 sm:px-6 py-5 bg-blue-50 border-b border-gray-200" style="display: none;">
                    <h4 class="text-base font-semibold text-gray-900 mb-1">Same work as last time?</h4>
                    <p class="text-gray-700 mb-4" id="repeat-last-summary">Pick a customer to see their last invoice.</p>
                    <button type="button" id="repeat-last-button" onclick="repeatLastInvoice()"
                            class="min-h-[44px] inline-flex items-center px-5 py-3 bg-blue-800 text-white rounded-lg font-semibold hover:bg-blue-900">
                        <i class="fas fa-rotate-left mr-2" aria-hidden="true"></i>Copy those line items
                    </button>
                    <p class="mt-2 text-gray-700">This fills in the same lines. You can change anything before you save.</p>
                </div>

                <?php $servicesGrouped = getServicesGrouped($pdo); ?>
                <?php if ($servicesGrouped): ?>
                <div class="px-4 sm:px-6 py-5 bg-white border-b border-gray-200">
                    <h4 class="text-base font-semibold text-gray-900 mb-1">Tap a job to add it</h4>
                    <p class="text-gray-700 mb-4">Prices are your usual ones. You can change the amount on the invoice without changing the saved default.</p>
                    <?php foreach ($servicesGrouped as $category => $services): ?>
                    <div class="mb-4">
                        <p class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2"><?php echo htmlspecialchars($category); ?></p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($services as $service): ?>
                            <button type="button"
                                    onclick="addSavedJob(<?php echo htmlspecialchars(json_encode($service['name']), ENT_QUOTES); ?>, <?php echo (float) $service['default_price']; ?>)"
                                    class="min-h-[44px] inline-flex items-center px-4 py-2 bg-white border border-gray-400 rounded-lg hover:bg-gray-100 hover:border-gray-900 text-gray-900 font-medium">
                                <?php echo htmlspecialchars($service['name']); ?>
                                <?php if ($service['default_price'] > 0): ?>
                                <span class="ml-2 text-gray-700"><?php echo formatCurrency($service['default_price']); ?></span>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="services.php" class="inline-flex items-center min-h-[44px] text-blue-800 font-semibold hover:underline">
                        <i class="fas fa-sliders-h mr-2" aria-hidden="true"></i>Manage saved jobs
                    </a>
                </div>
                <?php endif; ?>
                <div class="p-4 sm:p-6">
                    <div id="line-items">
                        <!-- Header - Hidden on mobile -->
                        <div class="hidden sm:grid grid-cols-12 gap-4 mb-4 pb-2 border-b border-gray-200">
                            <div class="col-span-1 text-sm font-medium text-gray-700">Type</div>
                            <div class="col-span-5 text-sm font-medium text-gray-700">Description</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700">Qty/Hours/Miles</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700">Rate/Price</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700 text-right">Total</div>
                        </div>
                        
                        <!-- Empty state -->
                        <div id="empty-state" class="text-center py-8 text-gray-700">
                            <i class="fas fa-plus-circle text-4xl mb-4"></i>
                            <p class="text-sm sm:text-base">Click the buttons above to add Labor, Mileage, or Material line items</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Totals & Notes -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
                <!-- Notes -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-sticky-note mr-3 text-orange-600"></i>
                            Notes & Payment Instructions
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <textarea name="notes" rows="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all resize-none text-base"
                                  placeholder="Payment instructions, terms, or additional notes..."><?php echo htmlspecialchars($businessSettings['payment_instructions']); ?></textarea>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-4 sm:px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-calculator mr-3 text-gray-600"></i>
                            Invoice Totals
                        </h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between text-lg">
                                <span class="font-medium text-gray-700">Subtotal:</span>
                                <span id="subtotal" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-2 sm:space-y-0">
                                <label for="tax-rate" class="font-medium text-gray-700">Tax Rate (%):</label>
                                <input type="number" name="tax_rate" step="0.01" value="0" onchange="calculateTotals()" class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-right text-base" id="tax-rate">
                            </div>
                            <div class="flex justify-between text-lg">
                                <span class="font-medium text-gray-700">Tax Amount:</span>
                                <span id="tax-amount" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between text-xl sm:text-2xl">
                                    <span class="font-bold text-gray-900">Total:</span>
                                    <span id="total" class="font-bold text-gray-900">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <label for="save-new-jobs" class="flex items-start gap-3 cursor-pointer min-h-[44px]">
                    <input type="checkbox" id="save-new-jobs" name="save_new_jobs" value="1" class="mt-1 w-6 h-6 shrink-0 border-gray-400 rounded">
                    <span>
                        <span class="block text-base font-semibold text-gray-900">Remember any new jobs on this invoice</span>
                        <span class="block text-gray-700">Adds anything you typed that is not already in your saved jobs, using this amount as its usual price. Jobs you already saved keep the price they have.</span>
                    </span>
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                <a href="dashboard.php" class="inline-flex items-center justify-center px-6 py-4 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium text-base">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="inline-flex items-center justify-center px-8 py-4 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold text-base">
                    <i class="fas fa-plus mr-2"></i>Create Invoice
                </button>
            </div>
        </form>
    </main>

    <script>
        // Business settings and rates
        const defaultHourlyRate = <?php echo json_encode($businessSettings['default_hourly_rate']); ?>;
        const mileageRate = <?php echo json_encode($businessSettings['mileage_rate']); ?>;
        let currentCustomerRate = null;
        const propertyDistances = <?php
            $distances = [];
            foreach ($customerProperties as $cp) {
                $distances[(string) $cp['id']] = (float) ($cp['distance_miles'] ?? 0);
            }
            echo json_encode($distances ?: new stdClass());
        ?>;

        function onPropertyChange() {
            const select = document.getElementById('property-select');
            const miles = propertyDistances[String(select.value)] || 0;

            document.querySelectorAll('.line-item[data-auto-mileage="1"]').forEach(el => el.remove());

            if (miles > 0 && mileageRate > 0) {
                addAutoMileage(miles);
            }
            calculateTotals();
            refreshLastInvoice();
        }

        function addAutoMileage(miles) {
            hideEmptyState();
            const roundTrip = Math.round(miles * 2 * 100) / 100;
            const container = document.getElementById('line-items');
            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-orange-50 rounded-lg border-l-4 border-orange-700" data-auto-mileage="1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-orange-700 rounded-lg flex items-center justify-center">
                                <i class="fas fa-car text-white text-sm" aria-hidden="true"></i>
                            </span>
                            <span class="text-sm font-semibold text-gray-800">Mileage added automatically</span>
                        </div>
                        <button type="button" onclick="removeLineItem(this)" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-500 hover:text-red-700 rounded-lg" aria-label="Remove the mileage line">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-6">
                            <label for="auto-mileage-desc" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" id="auto-mileage-desc" name="item_description[]" value="Mileage - ${roundTrip} miles round trip" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="auto-mileage-qty" class="block text-sm font-medium text-gray-700 mb-1">Miles</label>
                            <input type="number" id="auto-mileage-qty" name="item_quantity[]" step="0.1" value="${roundTrip}" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="auto-mileage-rate" class="block text-sm font-medium text-gray-700 mb-1">Rate</label>
                            <input type="number" id="auto-mileage-rate" name="item_price[]" step="0.001" value="${mileageRate}" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between sm:justify-end pt-2">
                            <span class="text-sm font-medium text-gray-700 sm:hidden">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function toggleCustomerFields() {
            const customerType = document.querySelector('input[name="customer_type"]:checked').value;
            document.getElementById('existing-customer').style.display = customerType === 'existing' ? 'block' : 'none';
            document.getElementById('new-customer').style.display = customerType === 'new' ? 'block' : 'none';
        }

        function loadCustomerData() {
            const select = document.getElementById('customer-select');
            const selectedOption = select.options[select.selectedIndex];
            const propertySelection = document.getElementById('property-selection');
            
            if (selectedOption.value) {
                currentCustomerRate = selectedOption.dataset.hourlyRate || defaultHourlyRate;
                
                // Load properties for this customer
                loadCustomerProperties(selectedOption.value);
                propertySelection.style.display = 'block';
            } else {
                currentCustomerRate = defaultHourlyRate;
                propertySelection.style.display = 'none';
            }

            refreshLastInvoice();
        }

        function loadCustomerProperties(customerId) {
            const propertySelect = document.getElementById('property-select');
            
            // Clear existing options except the first one
            propertySelect.innerHTML = '<option value="">No specific property</option>';
            
            // Fetch properties via AJAX
            fetch(`get-customer-properties.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(properties => {
                    properties.forEach(property => {
                        const option = document.createElement('option');
                        option.value = property.id;
                        propertyDistances[String(property.id)] = parseFloat(property.distance_miles || 0) || 0;
                        
                        // Use address if available, otherwise fallback to property name
                        if (property.address && property.address.trim() !== '') {
                            // Replace line breaks with commas for display
                            let address = property.address.replace(/[\r\n]+/g, ', ');
                            // Truncate if longer than 30 characters
                            if (address.length > 30) {
                                option.textContent = address.substring(0, 27) + '...';
                            } else {
                                option.textContent = address;
                            }
                        } else {
                            option.textContent = property.property_name;
                        }
                        
                        if (property.property_type && property.property_type !== 'Other') {
                            option.textContent += ` (${property.property_type})`;
                        }
                        propertySelect.appendChild(option);
                    });
                })
                .catch(() => {});
        }

        function hideEmptyState() {
            const emptyState = document.getElementById('empty-state');
            if (emptyState) {
                emptyState.style.display = 'none';
            }
        }

        function addLaborItem() {
            hideEmptyState();
            const rate = currentCustomerRate || defaultHourlyRate;
            const container = document.getElementById('line-items');
            
            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-gray-50 rounded-lg border-l-4 border-gray-500">
                    <!-- Mobile Layout -->
                    <div class="block sm:hidden space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-gray-600 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-white text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">Labor</span>
                            </div>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 p-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div>
                            <label for="item-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="item_description[]" placeholder="Labor description (e.g., Handyman work, Lawn maintenance)" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-description">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="item-quantity" class="block text-sm font-medium text-gray-700 mb-1">Hours</label>
                                <input type="number" name="item_quantity[]" step="0.25" placeholder="Hours" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-quantity">
                            </div>
                            <div>
                                <label for="item-price" class="block text-sm font-medium text-gray-700 mb-1">Rate</label>
                                <input type="number" name="item_price[]" step="0.01" value="${rate}" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-price">
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-medium text-gray-700">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                    
                    <!-- Desktop Layout -->
                    <div class="hidden sm:grid grid-cols-12 gap-4 items-center">
                        <div class="col-span-1 flex items-center">
                            <div class="w-8 h-8 bg-gray-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-white text-sm"></i>
                            </div>
                        </div>
                        <div class="col-span-5">
                            <input type="text" name="item_description[]" placeholder="Labor description (e.g., Handyman work, Lawn maintenance)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_quantity[]" step="0.25" placeholder="Hours" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_price[]" step="0.01" value="${rate}" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2 flex items-center justify-between">
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 ml-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        let savedJobIndex = 0;

        function addSavedJob(name, price) {
            hideEmptyState();
            const container = document.getElementById('line-items');
            const idx = ++savedJobIndex;
            const safeName = String(name).replace(/"/g, '&quot;');
            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-700">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-blue-800 rounded-lg flex items-center justify-center">
                                <i class="fas fa-briefcase text-white text-sm" aria-hidden="true"></i>
                            </span>
                            <span class="text-sm font-semibold text-gray-800">Saved job</span>
                        </div>
                        <button type="button" onclick="removeLineItem(this)" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-500 hover:text-red-700 rounded-lg" aria-label="Remove this line">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-6">
                            <label for="saved-job-desc-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" id="saved-job-desc-${idx}" name="item_description[]" value="${safeName}" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="saved-job-qty-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                            <input type="number" id="saved-job-qty-${idx}" name="item_quantity[]" step="0.25" value="1" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="saved-job-price-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" id="saved-job-price-${idx}" name="item_price[]" step="0.01" value="${Number(price).toFixed(2)}" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between sm:justify-end pt-2">
                            <span class="text-sm font-medium text-gray-700 sm:hidden">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemHtml);
            calculateTotals();
        }

        let lastInvoiceItems = null;
        let repeatIndex = 0;

        function refreshLastInvoice() {
            const panel = document.getElementById('repeat-last');
            const summary = document.getElementById('repeat-last-summary');
            const customerId = document.getElementById('customer-select').value;
            const propertySelect = document.getElementById('property-select');
            const propertyId = propertySelect ? propertySelect.value : '';

            lastInvoiceItems = null;

            if (!customerId) {
                panel.style.display = 'none';
                return;
            }

            fetch(`get-last-invoice.php?customer_id=${encodeURIComponent(customerId)}&property_id=${encodeURIComponent(propertyId)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.found || !data.items.length) {
                        panel.style.display = 'none';
                        return;
                    }

                    lastInvoiceItems = data.items;
                    const lineWord = data.items.length === 1 ? 'line' : 'lines';
                    const scope = propertyId ? 'this property' : 'this customer';
                    summary.textContent = `The last invoice for ${scope} was ${data.date_label} — ${data.items.length} ${lineWord}, $${data.total.toFixed(2)}.`;
                    panel.style.display = 'block';
                })
                .catch(() => {
                    panel.style.display = 'none';
                });
        }

        function repeatLastInvoice() {
            if (!lastInvoiceItems || !lastInvoiceItems.length) {
                return;
            }

            document.querySelectorAll('.line-item[data-repeated="1"]').forEach(el => el.remove());

            const bringsMileage = lastInvoiceItems.some(item => /^mileage/i.test(item.description));
            if (bringsMileage) {
                document.querySelectorAll('.line-item[data-auto-mileage="1"]').forEach(el => el.remove());
            }

            lastInvoiceItems.forEach(addRepeatedItem);
            calculateTotals();
        }

        function addRepeatedItem(item) {
            hideEmptyState();
            const idx = ++repeatIndex;
            const container = document.getElementById('line-items');
            const safeDescription = String(item.description).replace(/"/g, '&quot;');
            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-blue-50 rounded-lg border-l-4 border-blue-700" data-repeated="1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 bg-blue-800 rounded-lg flex items-center justify-center">
                                <i class="fas fa-rotate-left text-white text-sm" aria-hidden="true"></i>
                            </span>
                            <span class="text-sm font-semibold text-gray-800">From the last invoice</span>
                        </div>
                        <button type="button" onclick="removeLineItem(this)" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-500 hover:text-red-700 rounded-lg" aria-label="Remove this line">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-6">
                            <label for="repeat-desc-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" id="repeat-desc-${idx}" name="item_description[]" value="${safeDescription}" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="repeat-qty-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                            <input type="number" id="repeat-qty-${idx}" name="item_quantity[]" step="0.01" value="${item.quantity}" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="repeat-price-${idx}" class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" id="repeat-price-${idx}" name="item_price[]" step="0.01" value="${item.unit_price}" onchange="calculateTotals()" onkeyup="calculateTotals()" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between sm:justify-end pt-2">
                            <span class="text-sm font-medium text-gray-700 sm:hidden">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function addMileageItem() {
            hideEmptyState();
            const container = document.getElementById('line-items');

            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-orange-50 rounded-xl border-l-4 border-orange-500">
                    <!-- Mobile Layout -->
                    <div class="block sm:hidden space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-orange-700 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-car text-white text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">Mileage</span>
                            </div>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 p-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div>
                            <label for="item-description-2" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="item_description[]" placeholder="Travel description (e.g., Travel to job site)" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-description-2">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="item-quantity-2" class="block text-sm font-medium text-gray-700 mb-1">Miles</label>
                                <input type="number" name="item_quantity[]" step="0.1" placeholder="Miles" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-quantity-2">
                            </div>
                            <div>
                                <label for="item-price-2" class="block text-sm font-medium text-gray-700 mb-1">Rate</label>
                                <input type="number" name="item_price[]" step="0.001" value="${mileageRate}" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-price-2">
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-medium text-gray-700">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                    
                    <!-- Desktop Layout -->
                    <div class="hidden sm:grid grid-cols-12 gap-4 items-center">
                        <div class="col-span-1 flex items-center">
                            <div class="w-8 h-8 bg-orange-700 rounded-lg flex items-center justify-center">
                                <i class="fas fa-car text-white text-sm"></i>
                            </div>
                        </div>
                        <div class="col-span-5">
                            <input type="text" name="item_description[]" placeholder="Travel description (e.g., Travel to job site)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_quantity[]" step="0.1" placeholder="Miles" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_price[]" step="0.001" value="${mileageRate}" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2 flex items-center justify-between">
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 ml-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function addMaterialItem() {
            hideEmptyState();
            const container = document.getElementById('line-items');
            
            const itemHtml = `
                <div class="line-item mb-4 p-4 bg-green-50 rounded-xl border-l-4 border-green-500">
                    <!-- Mobile Layout -->
                    <div class="block sm:hidden space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-green-700 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-box text-white text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">Material</span>
                            </div>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 p-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div>
                            <label for="item-description-3" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="item_description[]" placeholder="Material/part description (e.g., Light fixture, Paint, Lumber)" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-description-3">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="item-quantity-3" class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" name="item_quantity[]" step="0.01" placeholder="Quantity" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-quantity-3">
                            </div>
                            <div>
                                <label for="item-price-3" class="block text-sm font-medium text-gray-700 mb-1">Price Each</label>
                                <input type="number" name="item_price[]" step="0.01" placeholder="Price each" onchange="calculateTotals()" class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base" id="item-price-3">
                            </div>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-medium text-gray-700">Total:</span>
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                        </div>
                    </div>
                    
                    <!-- Desktop Layout -->
                    <div class="hidden sm:grid grid-cols-12 gap-4 items-center">
                        <div class="col-span-1 flex items-center">
                            <div class="w-8 h-8 bg-green-700 rounded-lg flex items-center justify-center">
                                <i class="fas fa-box text-white text-sm"></i>
                            </div>
                        </div>
                        <div class="col-span-5">
                            <input type="text" name="item_description[]" placeholder="Material/part description (e.g., Light fixture, Paint, Lumber)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_quantity[]" step="0.01" placeholder="Quantity" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2">
                            <input type="number" name="item_price[]" step="0.01" placeholder="Price each" onchange="calculateTotals()" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div class="col-span-2 flex items-center justify-between">
                            <span class="line-total text-lg font-semibold text-gray-900">$0.00</span>
                            <button type="button" onclick="removeLineItem(this)" class="text-red-700 hover:text-red-800 ml-2">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function removeLineItem(button) {
            button.closest('.line-item').remove();
            calculateTotals();
            
            // Show empty state if no items
            const items = document.querySelectorAll('.line-item');
            if (items.length === 0) {
                document.getElementById('empty-state').style.display = 'block';
            }
        }

        function calculateTotals() {
            let subtotal = 0;
            
            document.querySelectorAll('.line-item').forEach(item => {
                const mobileSection = item.querySelector('.block.sm\\:hidden');
                const desktopSection = item.querySelector('.hidden.sm\\:grid');

                let scope = item;
                if (mobileSection && desktopSection) {
                    scope = window.innerWidth < 640 ? mobileSection : desktopSection;
                }

                const quantityInput = scope.querySelector('input[name="item_quantity[]"]');
                const priceInput = scope.querySelector('input[name="item_price[]"]');

                if (!quantityInput || !priceInput) {
                    return;
                }

                const quantity = parseFloat(quantityInput.value) || 0;
                const price = parseFloat(priceInput.value) || 0;
                const lineTotal = quantity * price;

                item.querySelectorAll('.line-total').forEach(total => {
                    total.textContent = '$' + lineTotal.toFixed(2);
                });

                subtotal += lineTotal;
            });
            
            const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]').value) || 0;
            const taxAmount = subtotal * (taxRate / 100);
            const total = subtotal + taxAmount;
            
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax-amount').textContent = '$' + taxAmount.toFixed(2);
            document.getElementById('total').textContent = '$' + total.toFixed(2);
        }

        // Initialize default rate and load customer data if pre-selected
        currentCustomerRate = defaultHourlyRate;
        
        // If customer is pre-selected, load their data
        <?php if ($selectedCustomerId): ?>
        window.addEventListener('load', function() {
            loadCustomerData();
        });
        <?php endif; ?>
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>