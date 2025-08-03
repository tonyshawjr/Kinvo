<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

requireAdmin();

// Get and validate estimate ID
$estimateId = $_GET['id'] ?? '';
if (!$estimateId || !is_numeric($estimateId) || $estimateId < 1) {
    header('Location: estimates.php');
    exit;
}

// Get estimate details
$estimate = getEstimate($pdo, (int)$estimateId);
if (!$estimate || !canEditEstimate($estimate)) {
    header('Location: estimates.php');
    exit;
}

// Get estimate items
$items = getEstimateItems($pdo, $estimateId);

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, name, custom_hourly_rate FROM customers ORDER BY name");
$customers = $stmt->fetchAll();

// Get properties for selected customer
$customerProperties = [];
if ($estimate['customer_id']) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer_properties WHERE customer_id = ? AND is_active = 1 ORDER BY property_name");
        $stmt->execute([$estimate['customer_id']]);
        $customerProperties = $stmt->fetchAll();
    } catch (Exception $e) {
        // Properties table might not exist yet
    }
}

// Get business settings
$businessSettings = getBusinessSettings($pdo);
$estimateSettings = getEstimateSettings($pdo);

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    try {
        $pdo->beginTransaction();
        
        // Validate customer
        $customerId = validateInteger($_POST['customer_id'] ?? '', 'Customer ID', true, 1);
        
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
        
        // Validate other fields
        $estimateDate = validateDate($_POST['estimate_date'] ?? '', 'Estimate Date', true);
        $expiresDate = validateDate($_POST['expires_date'] ?? '', 'Expiration Date', true);
        $taxRate = validateCurrency($_POST['tax_rate'] ?? '0', 'Tax Rate', false, true);
        if ($taxRate > 100) {
            throw new InvalidArgumentException('Tax rate cannot exceed 100%.');
        }
        
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;
        
        $notes = validateAndSanitizeString($_POST['notes'] ?? '', 1000, 'Notes', false);
        $terms = validateAndSanitizeString($_POST['terms'] ?? '', 1000, 'Terms', false);
        $propertyId = validateInteger($_POST['property_id'] ?? '', 'Property ID', false, 1);
        
        // Update estimate
        $stmt = $pdo->prepare("
            UPDATE estimates 
            SET customer_id = ?, property_id = ?, date = ?, expires_date = ?, 
                subtotal = ?, tax_rate = ?, tax_amount = ?, total = ?, 
                notes = ?, terms = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $customerId,
            $propertyId ?: null,
            $estimateDate,
            $expiresDate,
            $subtotal,
            $taxRate,
            $taxAmount,
            $total,
            $notes,
            $terms,
            $estimateId
        ]);
        
        // Delete existing line items
        $stmt = $pdo->prepare("DELETE FROM estimate_items WHERE estimate_id = ?");
        $stmt->execute([$estimateId]);
        
        // Insert new line items
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
        logEstimateActivity($pdo, $estimateId, 'updated', 'Estimate updated');
        
        $pdo->commit();
        
        header("Location: estimate-detail.php?id=$estimateId&success=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
        error_log("Edit estimate error: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Estimate - Kinvo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-6 sm:py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6 sm:mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Edit Estimate <?php echo htmlspecialchars($estimate['estimate_number']); ?></h2>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">Update estimate details</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Updating Estimate</h3>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                            <select name="customer_id" id="customer-select" onchange="loadCustomerData()" 
                                    class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" 
                                        data-hourly-rate="<?php echo htmlspecialchars($customer['custom_hourly_rate'] ?? ''); ?>"
                                        <?php echo $estimate['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                    <?php if ($customer['custom_hourly_rate']): ?>
                                        ($<?php echo htmlspecialchars($customer['custom_hourly_rate']); ?>/hr)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="property-selection">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Property/Location (Optional)</label>
                            <select name="property_id" id="property-select" 
                                    class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                <option value="">No specific property</option>
                                <?php foreach ($customerProperties as $property): ?>
                                <option value="<?php echo $property['id']; ?>" <?php echo $estimate['property_id'] == $property['id'] ? 'selected' : ''; ?>>
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
            </div>

            <!-- Estimate Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
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
                            <input type="date" name="estimate_date" value="<?php echo $estimate['date']; ?>" 
                                   class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Expires Date</label>
                            <input type="date" name="expires_date" value="<?php echo $estimate['expires_date']; ?>" 
                                   class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" id="tax-rate" value="<?php echo $estimate['tax_rate']; ?>" 
                                   step="0.01" min="0" max="100" 
                                   onchange="calculateTotals()" onkeyup="calculateTotals()" 
                                   class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-ul mr-3 text-gray-600"></i>
                        Line Items
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <!-- Quick Add Buttons -->
                    <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button type="button" onclick="addLaborItem()" 
                                class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-clock mr-2"></i>Add Labor
                        </button>
                        <button type="button" onclick="addMileageItem()" 
                                class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-car mr-2"></i>Add Mileage
                        </button>
                        <button type="button" onclick="addMaterialItem()" 
                                class="flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-box mr-2"></i>Add Materials
                        </button>
                    </div>

                    <div id="line-items">
                        <!-- Existing line items will be loaded here -->
                    </div>

                    <button type="button" onclick="addLineItem()" 
                            class="mt-4 w-full sm:w-auto px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Custom Item
                    </button>
                </div>
            </div>

            <!-- Notes & Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-sticky-note mr-3 text-gray-600"></i>
                        Additional Information
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (visible on estimate)</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" 
                                      placeholder="Any special notes for this estimate..."><?php echo htmlspecialchars($estimate['notes'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Terms & Conditions</label>
                            <textarea name="terms" rows="3" 
                                      class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" 
                                      placeholder="Payment terms, conditions..."><?php echo htmlspecialchars($estimate['terms'] ?? ''); ?></textarea>
                        </div>
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
                <button type="submit" class="flex-1 sm:flex-initial px-8 py-4 bg-gray-900 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors shadow-lg">
                    <i class="fas fa-save mr-2"></i>Update Estimate
                </button>
                <a href="estimate-detail.php?id=<?php echo $estimateId; ?>" 
                   class="flex-1 sm:flex-initial text-center px-8 py-4 bg-white text-gray-700 font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </main>

    <script>
    // Get business settings
    const defaultHourlyRate = <?php echo json_encode($businessSettings['default_hourly_rate'] ?? 45); ?>;
    const mileageRate = <?php echo json_encode($businessSettings['mileage_rate'] ?? 0.65); ?>;
    let lineItemCount = 0;

    // Load existing line items
    const existingItems = <?php echo json_encode($items); ?>;

    function loadCustomerData() {
        const select = document.getElementById('customer-select');
        const customerId = select.value;
        
        if (customerId) {
            // Load properties via AJAX
            fetch(`../ajax/get-customer-properties.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    const propertySelect = document.getElementById('property-select');
                    const currentPropertyId = '<?php echo $estimate['property_id']; ?>';
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
                        if (property.id == currentPropertyId) {
                            option.selected = true;
                        }
                        propertySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading properties:', error));
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

    // Load existing items
    existingItems.forEach(item => {
        addLineItem(item.description, item.quantity, item.unit_price);
    });

    // If no items exist, add one blank line
    if (existingItems.length === 0) {
        addLineItem();
    }
    </script>
</body>
</html>