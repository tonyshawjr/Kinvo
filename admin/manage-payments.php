<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$invoiceId = $_GET['invoice_id'] ?? null;
$success = false;
$error = '';

if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

// Add authorization check for invoice ownership
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

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'edit' && isset($_POST['payment_id'])) {
            // Edit payment
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET amount = ?, method = ?, payment_date = ?, notes = ?
                WHERE id = ? AND invoice_id = ?
            ");
            $stmt->execute([
                $_POST['amount'],
                $_POST['method'],
                $_POST['payment_date'],
                $_POST['notes'],
                $_POST['payment_id'],
                $invoiceId
            ]);
            $success = "Payment updated successfully!";
            
        } elseif ($_POST['action'] === 'delete' && isset($_POST['payment_id'])) {
            // Delete payment
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ? AND invoice_id = ?");
            $stmt->execute([$_POST['payment_id'], $invoiceId]);
            $success = "Payment deleted successfully!";
            
        } elseif ($_POST['action'] === 'add') {
            // Add new payment
            $stmt = $pdo->prepare("
                INSERT INTO payments (invoice_id, amount, method, payment_date, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceId,
                $_POST['amount'],
                $_POST['method'],
                $_POST['payment_date'],
                $_POST['notes']
            ]);
            $success = "Payment added successfully!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all payments for this invoice
$stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$stmt->execute([$invoiceId]);
$payments = $stmt->fetchAll();

// Calculate totals
$totalPaid = array_sum(array_column($payments, 'amount'));
$balance = $invoice['total'] - $totalPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?><?php 
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
            <div class="flex items-center space-x-4">
                <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Manage Payments</h2>
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
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Success!</h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($success); ?></p>
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

        <!-- Invoice Summary -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-calculator mr-3 text-gray-600"></i>
                Payment Summary
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add New Payment Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-plus mr-3 text-green-600"></i>
                            Add New Payment
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
                            <input type="number" name="amount" step="0.01" min="0" max="<?php echo $balance; ?>" 
                                   value="<?php echo $balance > 0 ? number_format($balance, 2, '.', '') : ''; ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                            <select name="method" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash App">Cash App</option>
                                <option value="Venmo">Venmo</option>
                                <option value="Zelle">Zelle</option>
                                <option value="PayPal">PayPal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" placeholder="Payment reference, confirmation number, etc."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all resize-none"></textarea>
                        </div>

                        <button type="submit" class="w-full px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                            <i class="fas fa-plus mr-2"></i>Add Payment
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Payments -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-history mr-3 text-gray-600"></i>
                            Payment History
                        </h3>
                        <p class="text-sm text-gray-600 mt-1"><?php echo count($payments); ?> payment<?php echo count($payments) != 1 ? 's' : ''; ?></p>
                    </div>
                    <div class="p-6">
                        <?php if (empty($payments)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-credit-card text-gray-400 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">No Payments Yet</h4>
                            <p class="text-gray-600">Add the first payment for this invoice using the form on the left.</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($payments as $payment): ?>
                            <div class="border border-gray-200 rounded-lg p-4" id="payment-<?php echo $payment['id']; ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <span class="font-semibold text-lg text-green-600"><?php echo formatCurrency($payment['amount']); ?></span>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <?php echo htmlspecialchars($payment['method']); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-1">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                        </p>
                                        <?php if ($payment['notes']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-sticky-note mr-1"></i>
                                            <?php echo htmlspecialchars($payment['notes']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button onclick="editPayment(<?php echo $payment['id']; ?>)" 
                                                class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Edit Payment">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deletePayment(<?php echo $payment['id']; ?>)" 
                                                class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Payment">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Edit Form (Hidden by default) -->
                                <div id="edit-form-<?php echo $payment['id']; ?>" class="hidden mt-4 pt-4 border-t border-gray-200">
                                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                                            <input type="number" name="amount" value="<?php echo $payment['amount']; ?>" 
                                                   step="0.01" min="0" required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Method</label>
                                            <select name="method" required
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                                <option value="Cash" <?php echo $payment['method'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="Check" <?php echo $payment['method'] === 'Check' ? 'selected' : ''; ?>>Check</option>
                                                <option value="Credit Card" <?php echo $payment['method'] === 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                                                <option value="Bank Transfer" <?php echo $payment['method'] === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                                <option value="Cash App" <?php echo $payment['method'] === 'Cash App' ? 'selected' : ''; ?>>Cash App</option>
                                                <option value="Venmo" <?php echo $payment['method'] === 'Venmo' ? 'selected' : ''; ?>>Venmo</option>
                                                <option value="Zelle" <?php echo $payment['method'] === 'Zelle' ? 'selected' : ''; ?>>Zelle</option>
                                                <option value="PayPal" <?php echo $payment['method'] === 'PayPal' ? 'selected' : ''; ?>>PayPal</option>
                                                <option value="Other" <?php echo $payment['method'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                            <input type="date" name="payment_date" value="<?php echo $payment['payment_date']; ?>" required
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                                            <input type="text" name="notes" value="<?php echo htmlspecialchars($payment['notes']); ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                                        </div>
                                        
                                        <div class="md:col-span-2 flex space-x-3">
                                            <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                                                <i class="fas fa-save mr-1"></i>Save
                                            </button>
                                            <button type="button" onclick="cancelEdit(<?php echo $payment['id']; ?>)" 
                                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function editPayment(paymentId) {
            // Hide all edit forms first
            document.querySelectorAll('[id^="edit-form-"]').forEach(form => {
                form.classList.add('hidden');
            });
            
            // Show the selected edit form
            document.getElementById('edit-form-' + paymentId).classList.remove('hidden');
        }

        function cancelEdit(paymentId) {
            document.getElementById('edit-form-' + paymentId).classList.add('hidden');
        }

        function deletePayment(paymentId) {
            if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="payment_id" value="${paymentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>