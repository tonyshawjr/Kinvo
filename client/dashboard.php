<?php
session_start();
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(false, true);

requireClientLogin();

$customer_id = $_SESSION['client_id'];
$customer_name = $_SESSION['client_name'];

// Get customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Get recent invoices
$stmt = $pdo->prepare("
    SELECT i.*, 
           COALESCE(SUM(p.amount), 0) as total_paid,
           CASE 
               WHEN COALESCE(SUM(p.amount), 0) >= i.total THEN 'Paid'
               WHEN COALESCE(SUM(p.amount), 0) > 0 THEN 'Partial'
               ELSE 'Unpaid'
           END as status
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ?
    GROUP BY i.id
    ORDER BY i.created_at DESC
    LIMIT 5
");
$stmt->execute([$customer_id]);
$recent_invoices = $stmt->fetchAll();

// Get account summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(i.id) as total_invoices,
        SUM(i.total) as total_billed,
        SUM(CASE WHEN i.status = 'Paid' THEN i.total ELSE 0 END) as total_paid_invoices,
        SUM(COALESCE(p.amount, 0)) as total_payments,
        SUM(i.total) - SUM(COALESCE(p.amount, 0)) as balance_due
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ?
");
$stmt->execute([$customer_id]);
$summary = $stmt->fetch();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, i.invoice_number, i.total as invoice_total
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.customer_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 3
");
$stmt->execute([$customer_id]);
$recent_payments = $stmt->fetchAll();

// Get overdue invoices
$stmt = $pdo->prepare("
    SELECT i.*, 
           COALESCE(SUM(p.amount), 0) as total_paid,
           i.total - COALESCE(SUM(p.amount), 0) as balance_due
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ? AND i.due_date < CURDATE()
    GROUP BY i.id
    HAVING balance_due > 0
    ORDER BY i.due_date ASC
");
$stmt->execute([$customer_id]);
$overdue_invoices = $stmt->fetchAll();

$businessSettings = getBusinessSettings($pdo);

// Log dashboard view
logClientActivity($pdo, $customer_id, 'dashboard_view', 'Viewed dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Account Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-invoice text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Invoices</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $summary['total_invoices']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Billed</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($summary['total_billed'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Paid</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($summary['total_payments'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-<?php echo ($summary['balance_due'] ?? 0) > 0 ? 'red' : 'green'; ?>-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-balance-scale text-<?php echo ($summary['balance_due'] ?? 0) > 0 ? 'red' : 'green'; ?>-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Balance Due</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($summary['balance_due'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (count($overdue_invoices) > 0): ?>
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                    <div>
                        <h3 class="text-sm font-medium text-red-800">Overdue Invoices</h3>
                        <p class="text-sm text-red-700 mt-1">
                            You have <?php echo count($overdue_invoices); ?> overdue invoice(s) requiring attention.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Invoices -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">Recent Invoices</h2>
                    <a href="/client/invoices.php" class="text-sm text-blue-600 hover:text-blue-500">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($invoice['date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo formatCurrency($invoice['total']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadgeClass($invoice['status']); ?>">
                                        <?php echo htmlspecialchars($invoice['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <a href="/public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-500" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_invoices)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No invoices found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">Recent Payments</h2>
                    <a href="/client/payments.php" class="text-sm text-blue-600 hover:text-blue-500">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['invoice_number']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo formatCurrency($payment['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($payment['method']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_payments)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No payments found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>