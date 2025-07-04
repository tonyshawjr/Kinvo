<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

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
    try {
        $pdo->beginTransaction();
        
        // Handle customer - create new or use existing
        if ($_POST['customer_type'] === 'new') {
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['customer_name'],
                $_POST['customer_email'],
                $_POST['customer_phone']
            ]);
            $customerId = $pdo->lastInsertId();
        } else {
            $customerId = $_POST['customer_id'];
        }
        
        // Calculate totals
        $subtotal = 0;
        $items = [];
        
        foreach ($_POST['item_description'] as $index => $description) {
            if (!empty($description)) {
                $quantity = floatval($_POST['item_quantity'][$index]);
                $unitPrice = floatval($_POST['item_price'][$index]);
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;
                
                $items[] = [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal
                ];
            }
        }
        
        $taxRate = floatval($_POST['tax_rate'] ?? 0);
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
        
        $pdo->commit();
        $success = true;
        $invoiceUrl = "/public/view-invoice.php?id=" . $uniqueId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating invoice: " . $e->getMessage();
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
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Create New Invoice</h2>
            <p class="text-gray-600 mt-1">Generate a professional invoice for your customer</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Invoice Created Successfully!</h3>
                    <p class="text-green-700 mb-4">Invoice Number: <strong><?php echo htmlspecialchars($invoiceNumber); ?></strong></p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?php echo htmlspecialchars($invoiceUrl); ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-eye mr-2"></i>View Invoice
                        </a>
                        <a href="create-invoice.php" class="inline-flex items-center px-4 py-2 bg-white text-green-600 border border-green-300 rounded-lg hover:bg-green-50 transition-colors">
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
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Creating Invoice</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <!-- Customer Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-gray-600"></i>
                        Customer Information
                    </h3>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Customer Type</label>
                        <div class="flex space-x-6">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="customer_type" value="existing" checked onchange="toggleCustomerFields()" class="w-4 h-4 text-gray-600 border-gray-300 focus:ring-gray-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">Existing Customer</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="customer_type" value="new" onchange="toggleCustomerFields()" class="w-4 h-4 text-gray-600 border-gray-300 focus:ring-gray-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">New Customer</span>
                            </label>
                        </div>
                    </div>

                    <div id="existing-customer">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Customer</label>
                                <select name="customer_id" id="customer-select" onchange="loadCustomerData()" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
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
                                <select name="property_id" id="property-select" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                    <option value="">No specific property</option>
                                    <?php foreach ($customerProperties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" <?php echo $selectedPropertyId == $property['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($property['property_name']); ?>
                                        <?php if ($property['property_type'] !== 'Other'): ?>
                                            (<?php echo htmlspecialchars($property['property_type']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">Select the property where work will be performed</p>
                            </div>
                        </div>
                    </div>

                    <div id="new-customer" class="grid grid-cols-1 md:grid-cols-3 gap-6" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="customer_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="Customer name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="customer_email" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="customer@email.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="tel" name="customer_phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="(555) 123-4567">
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
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Date *</label>
                            <input type="date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                            <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-list mr-3 text-green-600"></i>
                            Line Items
                        </h3>
                        <div class="flex space-x-2">
                            <button type="button" onclick="addLaborItem()" class="inline-flex items-center px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-semibold">
                                <i class="fas fa-clock mr-2"></i>Add Labor
                            </button>
                            <button type="button" onclick="addMileageItem()" class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm font-medium">
                                <i class="fas fa-car mr-2"></i>Add Mileage
                            </button>
                            <button type="button" onclick="addMaterialItem()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                                <i class="fas fa-plus mr-2"></i>Add Material
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div id="line-items">
                        <!-- Header -->
                        <div class="grid grid-cols-12 gap-4 mb-4 pb-2 border-b border-gray-200">
                            <div class="col-span-1 text-sm font-medium text-gray-700">Type</div>
                            <div class="col-span-5 text-sm font-medium text-gray-700">Description</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700">Qty/Hours/Miles</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700">Rate/Price</div>
                            <div class="col-span-2 text-sm font-medium text-gray-700 text-right">Total</div>
                        </div>
                        
                        <!-- Empty state -->
                        <div id="empty-state" class="text-center py-8 text-gray-500">
                            <i class="fas fa-plus-circle text-4xl mb-4"></i>
                            <p>Click the buttons above to add Labor, Mileage, or Material line items</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Totals & Notes -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Notes -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-sticky-note mr-3 text-orange-600"></i>
                            Notes & Payment Instructions
                        </h3>
                    </div>
                    <div class="p-6">
                        <textarea name="notes" rows="6" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all resize-none"
                                  placeholder="Payment instructions, terms, or additional notes..."><?php echo htmlspecialchars($businessSettings['payment_instructions']); ?></textarea>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-calculator mr-3 text-gray-600"></i>
                            Invoice Totals
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between text-lg">
                                <span class="font-medium text-gray-700">Subtotal:</span>
                                <span id="subtotal" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <label class="font-medium text-gray-700">Tax Rate (%):</label>
                                <input type="number" name="tax_rate" step="0.01" value="0" onchange="calculateTotals()" class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-right">
                            </div>
                            <div class="flex justify-between text-lg">
                                <span class="font-medium text-gray-700">Tax Amount:</span>
                                <span id="tax-amount" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between text-2xl">
                                    <span class="font-bold text-gray-900">Total:</span>
                                    <span id="total" class="font-bold text-gray-900">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6">
                <a href="dashboard.php" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="inline-flex items-center justify-center px-8 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
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
                        option.textContent = property.property_name;
                        if (property.property_type && property.property_type !== 'Other') {
                            option.textContent += ` (${property.property_type})`;
                        }
                        propertySelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.log('Could not load properties:', error);
                });
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
                <div class="line-item grid grid-cols-12 gap-4 mb-4 p-4 bg-gray-50 rounded-lg border-l-4 border-gray-500">
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
                        <button type="button" onclick="removeLineItem(this)" class="text-red-600 hover:text-red-800 ml-2">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function addMileageItem() {
            hideEmptyState();
            const container = document.getElementById('line-items');
            
            const itemHtml = `
                <div class="line-item grid grid-cols-12 gap-4 mb-4 p-4 bg-orange-50 rounded-xl border-l-4 border-orange-500">
                    <div class="col-span-1 flex items-center">
                        <div class="w-8 h-8 bg-orange-600 rounded-lg flex items-center justify-center">
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
                        <button type="button" onclick="removeLineItem(this)" class="text-red-600 hover:text-red-800 ml-2">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function addMaterialItem() {
            hideEmptyState();
            const container = document.getElementById('line-items');
            
            const itemHtml = `
                <div class="line-item grid grid-cols-12 gap-4 mb-4 p-4 bg-green-50 rounded-xl border-l-4 border-green-500">
                    <div class="col-span-1 flex items-center">
                        <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
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
                        <button type="button" onclick="removeLineItem(this)" class="text-red-600 hover:text-red-800 ml-2">
                            <i class="fas fa-trash"></i>
                        </button>
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
                const quantity = parseFloat(item.querySelector('input[name="item_quantity[]"]').value) || 0;
                const price = parseFloat(item.querySelector('input[name="item_price[]"]').value) || 0;
                const lineTotal = quantity * price;
                
                item.querySelector('.line-total').textContent = '$' + lineTotal.toFixed(2);
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