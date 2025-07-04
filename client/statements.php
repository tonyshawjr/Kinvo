<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireClientLogin();

$customer_id = $_SESSION['client_id'];
$customer_name = $_SESSION['client_name'];

// Default date range - last 6 months
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 months'));

// Get customer info
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Get invoices for the date range
$stmt = $pdo->prepare("
    SELECT i.*, 
           COALESCE(SUM(p.amount), 0) as total_paid,
           i.total - COALESCE(SUM(p.amount), 0) as balance_due
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ? 
    AND i.date BETWEEN ? AND ?
    GROUP BY i.id
    ORDER BY i.date DESC
");
$stmt->execute([$customer_id, $start_date, $end_date]);
$invoices = $stmt->fetchAll();

// Get payments for the date range
$stmt = $pdo->prepare("
    SELECT p.*, i.invoice_number
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.customer_id = ? 
    AND p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$customer_id, $start_date, $end_date]);
$payments = $stmt->fetchAll();

// Calculate summary
$total_invoiced = array_sum(array_column($invoices, 'total'));
$total_paid = array_sum(array_column($payments, 'amount'));
$total_balance = $total_invoiced - $total_paid;

$businessSettings = getBusinessSettings($pdo);

// Log activity
logClientActivity($pdo, $customer_id, 'statement_view', "Viewed statement for period {$start_date} to {$end_date}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Statements - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Page Header -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Account Statements</h2>
                <p class="text-gray-600 mt-1">Generate and view your account statements with custom date ranges</p>
            </div>

            <!-- Date Range Filter -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               value="<?php echo htmlspecialchars($start_date); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>

                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               value="<?php echo htmlspecialchars($end_date); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 transition-colors">
                            <i class="fas fa-search mr-2"></i>Generate Statement
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statement Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-file-invoice text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Invoiced</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($total_invoiced); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-green-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Paid</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($total_paid); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-<?php echo $total_balance > 0 ? 'red' : 'green'; ?>-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-balance-scale text-<?php echo $total_balance > 0 ? 'red' : 'green'; ?>-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Balance</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($total_balance); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statement Details -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Statement Details
                            <span class="text-sm font-normal text-gray-600 ml-2">
                                (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)
                            </span>
                        </h3>
                        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-semibold">
                            <i class="fas fa-print mr-2"></i>Print Statement
                        </button>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Customer Information</h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($customer['name']); ?></p>
                            <?php if ($customer['email']): ?>
                                <p class="text-gray-600"><?php echo htmlspecialchars($customer['email']); ?></p>
                            <?php endif; ?>
                            <?php if ($customer['phone']): ?>
                                <p class="text-gray-600"><?php echo htmlspecialchars($customer['phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Statement Period</h4>
                            <p class="text-gray-600">From: <?php echo date('F j, Y', strtotime($start_date)); ?></p>
                            <p class="text-gray-600">To: <?php echo date('F j, Y', strtotime($end_date)); ?></p>
                            <p class="text-gray-600">Generated: <?php echo date('F j, Y g:i A'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Invoices Section -->
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 mb-4">Invoices (<?php echo count($invoices); ?>)</h4>
                    <?php if (!empty($invoices)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($invoice['date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($invoice['total']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo formatCurrency($invoice['total_paid']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="<?php echo $invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                                    <?php echo formatCurrency($invoice['balance_due']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No invoices found for this period</p>
                    <?php endif; ?>
                </div>

                <!-- Payments Section -->
                <div class="p-6 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-4">Payments (<?php echo count($payments); ?>)</h4>
                    <?php if (!empty($payments)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-semibold">
                                                <?php echo formatCurrency($payment['amount']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($payment['method']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No payments found for this period</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
            }
        }
    </style>
</body>
</html>