<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

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

// Get business settings and estimate settings
$businessSettings = getBusinessSettings($pdo);
$estimateSettings = getEstimateSettings($pdo);

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
            throw new InvalidArgumentException('Estimate must have at least one line item.');
        }
        
        foreach ($_POST['item_description'] as $index => $description) {
            if (!empty($description)) {
                // Validate each line item
                $validatedDescription = validateAndSanitizeString($description, 500, "Item {$index} description", true);
                $validatedQuantity = validateCurrency($_POST['item_quantity'][$index] ?? '', "Item {$index} quantity", true);
                $validatedUnitPrice = validateCurrency($_POST['item_price'][$index] ?? '', "Item {$index} price", true);
                
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
            throw new InvalidArgumentException('Estimate must have at least one line item.');
        }
        
        // Validate tax rate
        $taxRate = validateCurrency($_POST['tax_rate'] ?? '0', 'Tax Rate', false, true);
        if ($taxRate > 100) {
            throw new InvalidArgumentException('Tax rate cannot exceed 100%.');
        }
        
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;
        
        // Generate estimate number
        $estimateNumber = generateEstimateNumber($pdo);
        if (!$estimateNumber) {
            throw new Exception('Failed to generate estimate number');
        }
        
        // Create estimate
        $estimateDate = validateDate($_POST['estimate_date'] ?? '', 'Estimate Date', true);
        $expirationDays = validateInteger($_POST['expiration_days'] ?? $estimateSettings['default_expiration'], 'Expiration Days', true, 1, 365);
        $expiresDate = date('Y-m-d', strtotime($estimateDate . ' + ' . $expirationDays . ' days'));
        
        $notes = validateAndSanitizeString($_POST['notes'] ?? '', 1000, 'Notes', false);
        $terms = validateAndSanitizeString($_POST['terms'] ?? '', 1000, 'Terms', false);
        $propertyId = validateInteger($_POST['property_id'] ?? '', 'Property ID', false, 1);
        
        $stmt = $pdo->prepare("
            INSERT INTO estimates (customer_id, property_id, estimate_number, date, expires_date, 
                                 subtotal, tax_rate, tax_amount, total, notes, terms, unique_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
        ");
        
        $uniqueId = generateEstimateUniqueId();
        
        $stmt->execute([
            $customerId,
            $propertyId ?: null,
            $estimateNumber,
            $estimateDate,
            $expiresDate,
            $subtotal,
            $taxRate,
            $taxAmount,
            $total,
            $notes,
            $terms,
            $uniqueId
        ]);
        
        $estimateId = $pdo->lastInsertId();
        
        // Insert line items
        $stmt = $pdo->prepare("
            INSERT INTO estimate_items (estimate_id, description, quantity, unit_price, total) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $estimateId,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['total']
            ]);
        }
        
        // Log activity
        logEstimateActivity($pdo, $estimateId, 'created', 'Estimate created');
        
        // Handle email option
        if (isset($_POST['send_email']) && $_POST['send_email'] === '1') {
            // TODO: Implement email sending
            // For now, just update status to Sent
            $stmt = $pdo->prepare("UPDATE estimates SET status = 'Sent' WHERE id = ?");
            $stmt->execute([$estimateId]);
            logEstimateActivity($pdo, $estimateId, 'sent', 'Estimate emailed to customer');
        }
        
        $pdo->commit();
        
        $success = true;
        $estimateUrl = "estimate-detail.php?id={$estimateId}";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
        error_log("Create estimate error: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Estimate - Kinvo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        /* Mobile-optimized styles */
        @media (max-width: 640px) {
            .mobile-card {
                border-radius: 0;
                border-left: 0;
                border-right: 0;
            }
            
            .mobile-full-width {
                margin-left: -1rem;
                margin-right: -1rem;
                width: calc(100% + 2rem);
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
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Create New Estimate</h2>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">Generate a quote for your customer</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Estimate Created Successfully!</h3>
                    <p class="text-green-700 mb-4">Estimate Number: <strong><?php echo htmlspecialchars($estimateNumber); ?></strong></p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?php echo htmlspecialchars($estimateUrl); ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-eye mr-2"></i>View Estimate
                        </a>
                        <a href="create-estimate.php" class="inline-flex items-center px-4 py-2 bg-white text-green-600 border border-green-300 rounded-lg hover:bg-green-50 transition-colors">
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
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Creating Estimate</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 sm:space-y-8">
            <?php echo getCSRFTokenField(); ?>
            <!-- Customer Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mobile-card">
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
                                <select name="customer_id" id="customer-select" onchange="loadCustomerData()" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
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
                                <select name="property_id" id="property-select" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                    <option value="">No specific property</option>
                                    <?php foreach ($customerProperties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" <?php echo $selectedPropertyId == $property['id'] ? 'selected' : ''; ?>>
                                        <?php 
                                        if (!empty($property['address'])) {
                                            $address = str_replace(["\r\n", "\n", "\r"], ', ', $property['address']);
                                            if (strlen($address) > 30) {
                                                echo htmlspecialchars(substr($address, 0, 27) . '...');
                                            } else {
                                                echo htmlspecialchars($address);
                                            }
                                        } else {
                                            echo htmlspecialchars($property['property_name']);
                                        }
                                        ?>
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
                            <input type="text" name="name" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="Customer name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="customer@email.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="tel" name="phone" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="(555) 123-4567">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estimate Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mobile-card">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-file-invoice mr-3 text-gray-600"></i>
                        Estimate Details
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estimate Date</label>
                            <input type="date" name="estimate_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valid For</label>
                            <select name="expiration_days" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                <option value="7" <?php echo $estimateSettings['default_expiration'] == '7' ? 'selected' : ''; ?>>7 days</option>
                                <option value="14" <?php echo $estimateSettings['default_expiration'] == '14' ? 'selected' : ''; ?>>14 days</option>
                                <option value="30" <?php echo $estimateSettings['default_expiration'] == '30' ? 'selected' : ''; ?>>30 days</option>
                                <option value="60" <?php echo $estimateSettings['default_expiration'] == '60' ? 'selected' : ''; ?>>60 days</option>
                                <option value="90" <?php echo $estimateSettings['default_expiration'] == '90' ? 'selected' : ''; ?>>90 days</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" id="tax-rate" value="0" step="0.01" min="0" max="100" 
                                   onchange="calculateTotals()" onkeyup="calculateTotals()" 
                                   class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mobile-card">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-ul mr-3 text-gray-600"></i>
                        Line Items
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <!-- Quick Add Buttons -->
                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button type="button" onclick="addLaborItem()" class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors touch-feedback">
                            <i class="fas fa-clock mr-2"></i>Add Labor
                        </button>
                        <button type="button" onclick="addMileageItem()" class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors touch-feedback">
                            <i class="fas fa-car mr-2"></i>Add Mileage
                        </button>
                        <button type="button" onclick="addMaterialItem()" class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors touch-feedback">
                            <i class="fas fa-box mr-2"></i>Add Materials
                        </button>
                    </div>

                    <div id="line-items">
                        <!-- Line items will be added here -->
                    </div>

                    <button type="button" onclick="addLineItem()" class="mt-4 w-full sm:w-auto px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors touch-feedback">
                        <i class="fas fa-plus mr-2"></i>Add Custom Item
                    </button>
                </div>
            </div>

            <!-- Notes & Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mobile-card">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-sticky-note mr-3 text-gray-600"></i>
                        Additional Information
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (visible on estimate)</label>
                            <textarea name="notes" rows="3" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="Any special notes for this estimate..."></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Terms & Conditions</label>
                            <textarea name="terms" rows="3" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" placeholder="Payment terms, conditions..."></textarea>
                        </div>
                    </div>

                    <!-- Email Option -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="send_email" value="1" class="w-5 h-5 text-gray-600 border-gray-300 rounded focus:ring-gray-500" <?php echo $estimateSettings['email_by_default'] == '1' ? 'checked' : ''; ?>>
                            <span class="ml-3 text-base font-medium text-gray-700">Email this estimate to customer</span>
                        </label>
                        <p class="mt-2 ml-8 text-sm text-gray-500">Customer will receive the estimate with approve/reject options</p>
                    </div>
                </div>
            </div>

            <!-- Totals Summary -->
            <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-4 sm:p-6">
                <div class="max-w-xs ml-auto">
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium">$<span id="subtotal">0.00</span></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Tax:</span>
                        <span class="font-medium">$<span id="tax">0.00</span></span>
                    </div>
                    <div class="flex justify-between py-3 text-lg font-bold border-t border-yellow-300">
                        <span>Total:</span>
                        <span>$<span id="total">0.00</span></span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex flex-col sm:flex-row gap-4">
                <button type="submit" class="flex-1 sm:flex-initial px-8 py-4 bg-gray-900 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors shadow-lg touch-feedback">
                    <i class="fas fa-save mr-2"></i>Create Estimate
                </button>
                <a href="estimates.php" class="flex-1 sm:flex-initial text-center px-8 py-4 bg-white text-gray-700 font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </main>

    <script>
    // Get hourly rate from business settings
    const defaultHourlyRate = <?php echo json_encode($businessSettings['default_hourly_rate'] ?? 45); ?>;
    const mileageRate = <?php echo json_encode($businessSettings['mileage_rate'] ?? 0.65); ?>;
    let lineItemCount = 0;

    function toggleCustomerFields() {
        const customerType = document.querySelector('input[name="customer_type"]:checked').value;
        document.getElementById('existing-customer').style.display = customerType === 'existing' ? 'block' : 'none';
        document.getElementById('new-customer').style.display = customerType === 'new' ? 'block' : 'none';
    }

    function loadCustomerData() {
        const select = document.getElementById('customer-select');
        const customerId = select.value;
        const propertySelection = document.getElementById('property-selection');
        
        if (customerId) {
            // Show property selection
            propertySelection.style.display = 'block';
            
            // Load properties via AJAX
            fetch(`../ajax/get-customer-properties.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    const propertySelect = document.getElementById('property-select');
                    propertySelect.innerHTML = '<option value="">No specific property</option>';
                    
                    data.properties.forEach(property => {
                        const option = document.createElement('option');
                        option.value = property.id;
                        
                        // Format the display text
                        let displayText = '';
                        if (property.address) {
                            displayText = property.address.replace(/[\r\n]+/g, ', ');
                            if (displayText.length > 30) {
                                displayText = displayText.substring(0, 27) + '...';
                            }
                        } else {
                            displayText = property.property_name;
                        }
                        
                        if (property.property_type !== 'Other') {
                            displayText += ` (${property.property_type})`;
                        }
                        
                        option.textContent = displayText;
                        propertySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading properties:', error));
        } else {
            propertySelection.style.display = 'none';
        }
    }

    function addLineItem(description = '', quantity = 1, price = 0) {
        const container = document.getElementById('line-items');
        const itemHtml = `
            <div class="line-item mb-4 p-4 bg-gray-50 rounded-lg" id="item-${lineItemCount}">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6">
                        <input type="text" name="item_description[]" value="${escapeHtml(description)}" 
                               class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" 
                               placeholder="Description" required>
                    </div>
                    <div class="md:col-span-2">
                        <input type="number" name="item_quantity[]" value="${quantity}" step="0.01" min="0" 
                               onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})"
                               class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" 
                               placeholder="Qty" required>
                    </div>
                    <div class="md:col-span-2">
                        <input type="number" name="item_price[]" value="${price}" step="0.01" min="0" 
                               onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})"
                               class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" 
                               placeholder="Price" required>
                    </div>
                    <div class="md:col-span-1 flex items-center">
                        <span class="text-lg font-medium">$<span id="line-total-${lineItemCount}">0.00</span></span>
                    </div>
                    <div class="md:col-span-1 flex items-center">
                        <button type="button" onclick="removeLineItem(${lineItemCount})" class="text-red-600 hover:text-red-800 transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', itemHtml);
        lineItemCount++;
        
        if (quantity && price) {
            calculateLineTotal(lineItemCount - 1);
        }
    }

    function addLaborItem() {
        const customerSelect = document.getElementById('customer-select');
        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        const customerHourlyRate = selectedOption.getAttribute('data-hourly-rate');
        const hourlyRate = customerHourlyRate || defaultHourlyRate;
        
        addLineItem('Labor - ', 1, hourlyRate);
    }

    function addMileageItem() {
        addLineItem('Mileage - ', 1, mileageRate);
    }

    function addMaterialItem() {
        addLineItem('Materials - ', 1, 0);
    }

    function removeLineItem(index) {
        document.getElementById(`item-${index}`).remove();
        calculateTotals();
    }

    function calculateLineTotal(index) {
        const quantity = parseFloat(document.querySelector(`#item-${index} input[name="item_quantity[]"]`).value) || 0;
        const price = parseFloat(document.querySelector(`#item-${index} input[name="item_price[]"]`).value) || 0;
        const total = quantity * price;
        
        document.getElementById(`line-total-${index}`).textContent = total.toFixed(2);
        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = 0;
        
        // Calculate subtotal from all line items
        document.querySelectorAll('[id^="line-total-"]').forEach(element => {
            subtotal += parseFloat(element.textContent) || 0;
        });
        
        // Calculate tax
        const taxRate = parseFloat(document.getElementById('tax-rate').value) || 0;
        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax;
        
        // Update display
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('tax').textContent = tax.toFixed(2);
        document.getElementById('total').textContent = total.toFixed(2);
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Initialize with one blank line item
    addLineItem();

    // If customer is pre-selected, load their data
    <?php if ($selectedCustomerId): ?>
    loadCustomerData();
    <?php endif; ?>
    </script>
</body>
</html>