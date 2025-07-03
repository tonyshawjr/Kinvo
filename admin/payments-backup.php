<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$success = false;
$error = '';
$selectedInvoiceId = $_GET['invoice_id'] ?? null;

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'add') {
            // Add new payment
            $stmt = $pdo->prepare("
                INSERT INTO payments (invoice_id, method, amount, payment_date, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['invoice_id'],
                $_POST['method'],
                $_POST['amount'],
                $_POST['payment_date'],
                $_POST['notes']
            ]);
            
            $success = "Payment added successfully!";
            $invoiceIdToUpdate = $_POST['invoice_id'];
            
        } elseif ($_POST['action'] === 'edit' && isset($_POST['payment_id'])) {
            // Edit existing payment
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET amount = ?, method = ?, payment_date = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['amount'],
                $_POST['method'],
                $_POST['payment_date'],
                $_POST['notes'],
                $_POST['payment_id']
            ]);
            
            // Get invoice ID for status update
            $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ?");
            $stmt->execute([$_POST['payment_id']]);
            $invoiceIdToUpdate = $stmt->fetchColumn();
            
            $success = "Payment updated successfully!";
            
        } elseif ($_POST['action'] === 'delete' && isset($_POST['payment_id'])) {
            // Get invoice ID before deleting
            $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ?");
            $stmt->execute([$_POST['payment_id']]);
            $invoiceIdToUpdate = $stmt->fetchColumn();
            
            // Delete payment
            $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$_POST['payment_id']]);
            
            $success = "Payment deleted successfully!";
        }
        
        // Update invoice status for any action
        if (isset($invoiceIdToUpdate)) {
            $stmt = $pdo->prepare("
                SELECT i.total, COALESCE(SUM(p.amount), 0) as total_paid 
                FROM invoices i 
                LEFT JOIN payments p ON i.id = p.invoice_id 
                WHERE i.id = ? 
                GROUP BY i.id, i.total
            ");
            $stmt->execute([$invoiceIdToUpdate]);
            $invoiceData = $stmt->fetch();
            
            $newStatus = 'Unpaid';
            if ($invoiceData && $invoiceData['total_paid'] >= $invoiceData['total']) {
                $newStatus = 'Paid';
            } elseif ($invoiceData && $invoiceData['total_paid'] > 0) {
                $newStatus = 'Partial';
            }
            
            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $invoiceIdToUpdate]);
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing payment: " . $e->getMessage();
    }
}

// Get unpaid and partially paid invoices
$stmt = $pdo->query("
    SELECT i.*, c.name as customer_name,
           COALESCE(SUM(p.amount), 0) as total_paid,
           (i.total - COALESCE(SUM(p.amount), 0)) as balance_due
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    LEFT JOIN payments p ON i.id = p.invoice_id 
    WHERE i.status IN ('Unpaid', 'Partial')
    GROUP BY i.id 
    HAVING balance_due > 0
    ORDER BY i.due_date ASC
");
$unpaidInvoices = $stmt->fetchAll();

// Get recent payments with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    JOIN customers c ON i.customer_id = c.id
");
$totalPayments = $stmt->fetchColumn();
$totalPages = ceil($totalPayments / $limit);

$stmt = $pdo->prepare("
    SELECT p.*, i.invoice_number, i.unique_id as invoice_unique_id, c.name as customer_name 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    JOIN customers c ON i.customer_id = c.id 
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$limit, $offset]);
$recentPayments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Kinvo</title>
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

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Payment Management</h2>
            <p class="text-gray-600 mt-1">Track and record payments from your customers</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
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
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-2xl p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error Adding Payment</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Payment Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle mr-3 text-green-600"></i>
                    Record New Payment
                </h3>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="add">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Invoice *</label>
                            <select name="invoice_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                <option value="">Choose an invoice...</option>
                                <?php foreach ($unpaidInvoices as $invoice): ?>
                                <option value="<?php echo $invoice['id']; ?>" <?php echo $selectedInvoiceId == $invoice['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?> - 
                                    <?php echo htmlspecialchars($invoice['customer_name']); ?> 
                                    (<?php echo formatCurrency($invoice['balance_due']); ?> due)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                            <select name="method" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                                <option value="">Select method...</option>
                                <option value="Zelle">💰 Zelle</option>
                                <option value="Venmo">📱 Venmo</option>
                                <option value="Cash App">💳 Cash App</option>
                                <option value="Cash">💵 Cash</option>
                                <option value="Check">📝 Check</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount *</label>
                            <input type="number" name="amount" step="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
                            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="Reference number, confirmation code, etc." class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all font-medium shadow-lg">
                            <i class="fas fa-plus mr-2"></i>Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <!-- Outstanding Invoices -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-orange-50 to-red-50 px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 text-orange-600"></i>
                        Outstanding Invoices
                    </h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo count($unpaidInvoices); ?> invoice<?php echo count($unpaidInvoices) != 1 ? 's' : ''; ?> awaiting payment</p>
                </div>
                <div class="p-6">
                    <?php if (empty($unpaidInvoices)): ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">All Caught Up!</h4>
                        <p class="text-gray-600">No outstanding invoices at the moment.</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($unpaidInvoices as $invoice): ?>
                        <div class="p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors <?php echo strtotime($invoice['due_date']) < time() ? 'border-red-200 bg-red-50' : ''; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                        <?php if (strtotime($invoice['due_date']) < time()): ?>
                                        <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Overdue</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-1"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                    <div class="flex items-center text-xs text-gray-500 space-x-4">
                                        <span>Due: <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                                        <span>Total: <?php echo formatCurrency($invoice['total']); ?></span>
                                        <span>Paid: <?php echo formatCurrency($invoice['total_paid']); ?></span>
                                    </div>
                                </div>
                                <div class="text-right ml-4">
                                    <p class="text-lg font-bold text-gray-900"><?php echo formatCurrency($invoice['balance_due']); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <a href="?invoice_id=<?php echo $invoice['id']; ?>" class="text-sm bg-green-100 text-green-700 px-3 py-1 rounded-lg hover:bg-green-200 transition-colors">
                                            Add Payment
                                        </a>
                                        <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-lg hover:bg-blue-200 transition-colors">
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-history mr-3 text-blue-600"></i>
                        Recent Payments
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Latest payment activities</p>
                </div>
                <div class="p-6">
                    <?php if (empty($recentPayments)): ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">No Payments Yet</h4>
                        <p class="text-gray-600">Payments will appear here once recorded.</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <?php
                                    $icon = 'fas fa-credit-card';
                                    if ($payment['method'] === 'Zelle') $icon = 'fas fa-university';
                                    if ($payment['method'] === 'Venmo') $icon = 'fab fa-venmo';
                                    if ($payment['method'] === 'Cash App') $icon = 'fas fa-mobile-alt';
                                    if ($payment['method'] === 'Cash') $icon = 'fas fa-money-bill';
                                    if ($payment['method'] === 'Check') $icon = 'fas fa-check';
                                    ?>
                                    <i class="<?php echo $icon; ?> text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['invoice_number']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                    <div class="flex items-center text-xs text-gray-500 space-x-2 mt-1">
                                        <span><?php echo htmlspecialchars($payment['method']); ?></span>
                                        <span>•</span>
                                        <span><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                        <?php if ($payment['notes']): ?>
                                        <span>•</span>
                                        <span><?php echo htmlspecialchars($payment['notes']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-green-600"><?php echo formatCurrency($payment['amount']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>