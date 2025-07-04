<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$invoiceId = $_GET['id'] ?? null;
$success = false;
$error = '';

if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

// Verify invoice exists and access is authorized
requireInvoiceOwnership($pdo, $invoiceId);

// Get invoice information
$stmt = $pdo->prepare("
    SELECT i.*, c.name as customer_name
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    WHERE i.id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Get line items
$stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
$stmt->execute([$invoiceId]);
$lineItems = $stmt->fetchAll();

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, name, custom_hourly_rate FROM customers ORDER BY name");
$customers = $stmt->fetchAll();

// Get customer properties if property_id column exists
$customerProperties = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
    $hasPropertyColumn = $stmt->rowCount() > 0;
    
    if ($hasPropertyColumn && $invoice['customer_id']) {
        $stmt = $pdo->prepare("SELECT id, property_name FROM customer_properties WHERE customer_id = ? AND is_active = 1 ORDER BY property_name");
        $stmt->execute([$invoice['customer_id']]);
        $customerProperties = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $hasPropertyColumn = false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    try {
        $pdo->beginTransaction();
        
        // Update invoice
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET customer_id = ?, property_id = ?, date = ?, due_date = ?, 
                subtotal = ?, tax_rate = ?, tax_amount = ?, total = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['property_id'] ?? null,
            $_POST['date'],
            $_POST['due_date'],
            $_POST['subtotal'],
            $_POST['tax_rate'],
            $_POST['tax_amount'],
            $_POST['total'],
            $_POST['notes'],
            $invoiceId
        ]);
        
        // Delete existing line items
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        
        // Add new line items
        if (!empty($_POST['line_items'])) {
            foreach ($_POST['line_items'] as $item) {
                if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $invoiceId,
                        $item['description'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total']
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $success = "Invoice updated successfully!";
        
        // Refresh the invoice data
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as customer_name
            FROM invoices i 
            JOIN customers c ON i.customer_id = c.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        
        // Refresh line items
        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $stmt->execute([$invoiceId]);
        $lineItems = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating invoice: " . $e->getMessage();
    }
}

// Get business settings for defaults
$businessSettings = getBusinessSettings($pdo);

// Get payments for this invoice
$stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$stmt->execute([$invoiceId]);
$payments = $stmt->fetchAll();

// Calculate payment totals
$totalPaid = array_sum(array_column($payments, 'amount'));
$balance = $invoice['total'] - $totalPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?><?php 
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
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4">
                <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Edit Invoice</h2>
                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($invoice['invoice_number']); ?> for <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-green-900 mb-2">Success!</h3>
                    <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
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
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <?php echo getCSRFTokenField(); ?>
            <!-- Invoice Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-file-invoice mr-3 text-gray-600"></i>
                        Invoice Details
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer *</label>
                        <select name="customer_id" id="customer-select" required onchange="loadCustomerProperties()"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    data-hourly-rate="<?php echo $customer['custom_hourly_rate']; ?>"
                                    <?php echo $customer['id'] == $invoice['customer_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($hasPropertyColumn): ?>
                    <div id="property-selection">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Property/Location</label>
                        <select name="property_id" id="property-select"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <option value="">No specific property</option>
                            <?php foreach ($customerProperties as $property): ?>
                            <option value="<?php echo $property['id']; ?>" 
                                    <?php echo $property['id'] == $invoice['property_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($property['property_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Date *</label>
                        <input type="date" name="date" value="<?php echo $invoice['date']; ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                        <input type="date" name="due_date" value="<?php echo $invoice['due_date']; ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list mr-3 text-gray-600"></i>
                        Line Items
                    </h3>
                </div>
                <div class="p-6">
                    <div id="line-items-container">
                        <?php foreach ($lineItems as $index => $item): ?>
                        <div class="line-item grid grid-cols-1 md:grid-cols-12 gap-4 mb-4 p-4 border border-gray-200 rounded-lg">
                            <div class="md:col-span-5">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <input type="text" name="line_items[<?php echo $index; ?>][description]" 
                                       value="<?php echo htmlspecialchars($item['description']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                                <input type="number" name="line_items[<?php echo $index; ?>][quantity]" 
                                       value="<?php echo $item['quantity']; ?>" step="0.01" min="0"
                                       onchange="calculateLineTotal(this)"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rate</label>
                                <input type="number" name="line_items[<?php echo $index; ?>][unit_price]" 
                                       value="<?php echo $item['unit_price']; ?>" step="0.01" min="0"
                                       onchange="calculateLineTotal(this)"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                                <input type="number" name="line_items[<?php echo $index; ?>][total]" 
                                       value="<?php echo $item['total']; ?>" step="0.01" readonly
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-700">
                            </div>
                            <div class="md:col-span-1 flex items-end">
                                <button type="button" onclick="removeLineItem(this)" 
                                        class="p-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" onclick="addLineItem()" 
                            class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-plus mr-2"></i>Add Line Item
                    </button>
                </div>
            </div>

            <!-- Totals -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-calculator mr-3 text-gray-600"></i>
                        Invoice Totals
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subtotal</label>
                        <input type="number" name="subtotal" id="subtotal" value="<?php echo $invoice['subtotal']; ?>" 
                               step="0.01" readonly
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="tax-rate" value="<?php echo $invoice['tax_rate']; ?>" 
                               step="0.01" min="0" max="100" onchange="calculateTotals()"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tax Amount</label>
                        <input type="number" name="tax_amount" id="tax-amount" value="<?php echo $invoice['tax_amount']; ?>" 
                               step="0.01" readonly
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                        <input type="number" name="total" id="total" value="<?php echo $invoice['total']; ?>" 
                               step="0.01" readonly
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-700 font-bold">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-sticky-note mr-3 text-gray-600"></i>
                        Notes & Payment Instructions
                    </h3>
                </div>
                <div class="p-6">
                    <textarea name="notes" rows="4" placeholder="Payment instructions, notes, terms, etc."
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all resize-none"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
                </div>
            </div>

            <!-- Payment Management -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-credit-card mr-3 text-gray-600"></i>
                            Payment Management
                        </h3>
                        <a href="manage-payments.php?invoice_id=<?php echo $invoiceId; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-semibold">
                            <i class="fas fa-cog mr-2"></i>Manage Payments
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Payment Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="text-center">
                            <p class="text-sm text-gray-500 mb-1">Invoice Total</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($invoice['total']); ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-500 mb-1">Total Paid</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totalPaid); ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-sm text-gray-500 mb-1">Balance Due</p>
                            <p class="text-2xl font-bold <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo formatCurrency($balance); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Recent Payments -->
                    <?php if (!empty($payments)): ?>
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Recent Payments</h4>
                        <div class="space-y-3">
                            <?php foreach (array_slice($payments, 0, 3) as $payment): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-check text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900"><?php echo formatCurrency($payment['amount']); ?></div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($payment['method']); ?> • 
                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-green-600">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($payments) > 3): ?>
                        <div class="mt-3 text-center">
                            <a href="manage-payments.php?invoice_id=<?php echo $invoiceId; ?>" 
                               class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                                View all <?php echo count($payments); ?> payments →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">No Payments Yet</h4>
                        <p class="text-gray-600 mb-4">This invoice hasn't received any payments.</p>
                        <a href="manage-payments.php?invoice_id=<?php echo $invoiceId; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                            <i class="fas fa-plus mr-2"></i>Add First Payment
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-between">
                <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" 
                   class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                    <i class="fas fa-save mr-2"></i>Update Invoice
                </button>
            </div>
        </form>
    </main>

    <script>
        let lineItemIndex = <?php echo count($lineItems); ?>;

        function addLineItem() {
            const container = document.getElementById('line-items-container');
            const div = document.createElement('div');
            div.className = 'line-item grid grid-cols-1 md:grid-cols-12 gap-4 mb-4 p-4 border border-gray-200 rounded-lg';
            div.innerHTML = `
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <input type="text" name="line_items[${lineItemIndex}][description]" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                    <input type="number" name="line_items[${lineItemIndex}][quantity]" 
                           step="0.01" min="0" onchange="calculateLineTotal(this)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rate</label>
                    <input type="number" name="line_items[${lineItemIndex}][unit_price]" 
                           step="0.01" min="0" onchange="calculateLineTotal(this)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                    <input type="number" name="line_items[${lineItemIndex}][total]" 
                           step="0.01" readonly
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-700">
                </div>
                <div class="md:col-span-1 flex items-end">
                    <button type="button" onclick="removeLineItem(this)" 
                            class="p-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
            lineItemIndex++;
        }

        function removeLineItem(button) {
            button.closest('.line-item').remove();
            calculateTotals();
        }

        function calculateLineTotal(input) {
            const lineItem = input.closest('.line-item');
            const quantity = parseFloat(lineItem.querySelector('input[name*="[quantity]"]').value) || 0;
            const unitPrice = parseFloat(lineItem.querySelector('input[name*="[unit_price]"]').value) || 0;
            const total = quantity * unitPrice;
            
            lineItem.querySelector('input[name*="[total]"]').value = total.toFixed(2);
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('input[name*="[total]"]').forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const taxRate = parseFloat(document.getElementById('tax-rate').value) || 0;
            const taxAmount = (subtotal * taxRate) / 100;
            const total = subtotal + taxAmount;
            
            document.getElementById('subtotal').value = subtotal.toFixed(2);
            document.getElementById('tax-amount').value = taxAmount.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
        }

        function loadCustomerProperties() {
            const customerId = document.getElementById('customer-select').value;
            const propertySelect = document.getElementById('property-select');
            
            if (!propertySelect || !customerId) return;
            
            fetch(`get-customer-properties.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(properties => {
                    propertySelect.innerHTML = '<option value="">No specific property</option>';
                    properties.forEach(property => {
                        const option = document.createElement('option');
                        option.value = property.id;
                        option.textContent = property.property_name;
                        propertySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error loading properties:', error));
        }

        // Calculate totals on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotals();
        });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>