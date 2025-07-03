<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

// Get current date info
$today = date('Y-m-d');
$thisWeek = date('Y-m-d', strtotime('monday this week'));
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// CRITICAL: Overdue invoices
$stmt = $pdo->query("
    SELECT i.*, c.name as customer_name, COALESCE(SUM(p.amount), 0) as total_paid,
           (i.total - COALESCE(SUM(p.amount), 0)) as balance_due,
           DATEDIFF('$today', i.due_date) as days_overdue
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    LEFT JOIN payments p ON i.id = p.invoice_id 
    WHERE i.status != 'Paid' AND i.due_date < '$today'
    GROUP BY i.id 
    HAVING balance_due > 0
    ORDER BY days_overdue DESC
    LIMIT 5
");
$overdueInvoices = $stmt->fetchAll();

// URGENT: Due this week
$stmt = $pdo->query("
    SELECT i.*, c.name as customer_name, COALESCE(SUM(p.amount), 0) as total_paid,
           (i.total - COALESCE(SUM(p.amount), 0)) as balance_due
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    LEFT JOIN payments p ON i.id = p.invoice_id 
    WHERE i.status != 'Paid' AND i.due_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)
    GROUP BY i.id 
    HAVING balance_due > 0
    ORDER BY i.due_date ASC
    LIMIT 5
");
$dueSoonInvoices = $stmt->fetchAll();

// CASH FLOW: This week's payments
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
    FROM payments 
    WHERE payment_date >= '$thisWeek'
");
$thisWeekPayments = $stmt->fetch();

// CASH FLOW: This month vs last month
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN DATE_FORMAT(payment_date, '%Y-%m') = ? THEN amount ELSE 0 END) as this_month,
        SUM(CASE WHEN DATE_FORMAT(payment_date, '%Y-%m') = ? THEN amount ELSE 0 END) as last_month
    FROM payments
    WHERE DATE_FORMAT(payment_date, '%Y-%m') IN (?, ?)
");
$stmt->execute([$thisMonth, $lastMonth, $thisMonth, $lastMonth]);
$monthlyComparison = $stmt->fetch();

// BUSINESS INSIGHT: Top customers by revenue
$stmt = $pdo->query("
    SELECT c.name, COALESCE(SUM(p.amount), 0) as total_paid, COUNT(DISTINCT i.id) as invoice_count
    FROM customers c
    LEFT JOIN invoices i ON c.id = i.customer_id
    LEFT JOIN payments p ON i.id = p.invoice_id
    GROUP BY c.id, c.name
    HAVING total_paid > 0
    ORDER BY total_paid DESC
    LIMIT 5
");
$topCustomers = $stmt->fetchAll();

// RECENT ACTIVITY: Last 5 payments
$stmt = $pdo->query("
    SELECT p.*, i.invoice_number, c.name as customer_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN customers c ON i.customer_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$recentPayments = $stmt->fetchAll();

// OUTSTANDING MONEY
$stmt = $pdo->query("
    SELECT COALESCE(SUM(i.total - COALESCE(p.total_paid, 0)), 0) as total_outstanding
    FROM invoices i
    LEFT JOIN (
        SELECT invoice_id, SUM(amount) as total_paid
        FROM payments
        GROUP BY invoice_id
    ) p ON i.id = p.invoice_id
    WHERE i.status != 'Paid'
");
$totalOutstanding = $stmt->fetchColumn();

// Calculate percentage change
$monthlyChange = 0;
if ($monthlyComparison['last_month'] > 0) {
    $monthlyChange = (($monthlyComparison['this_month'] - $monthlyComparison['last_month']) / $monthlyComparison['last_month']) * 100;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard<?php 
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

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Welcome Header with Quick Actions -->
        <div class="mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                <div class="mb-4 lg:mb-0">
                    <h2 class="text-3xl font-bold text-gray-900">Good <?php echo date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening'); ?>! 👋</h2>
                    <p class="text-gray-600 mt-1">Here's what needs your attention today.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="create-invoice.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-plus mr-2"></i>Create Invoice
                    </a>
                    <a href="payments.php" class="inline-flex items-center px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                        <i class="fas fa-credit-card mr-2"></i>Record Payment
                    </a>
                </div>
            </div>
        </div>

        <!-- CRITICAL ALERTS -->
        <?php if (!empty($overdueInvoices)): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-red-900 mb-2">⚠️ Overdue Invoices Need Attention</h3>
                    <p class="text-red-700 mb-4"><?php echo count($overdueInvoices); ?> invoice<?php echo count($overdueInvoices) > 1 ? 's are' : ' is'; ?> past due. Consider following up with these customers.</p>
                    <div class="space-y-2">
                        <?php foreach ($overdueInvoices as $invoice): ?>
                        <div class="flex items-center justify-between bg-white p-3 rounded-lg border border-red-200">
                            <div>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></span>
                                <span class="text-red-600 ml-2"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                <span class="text-sm text-gray-500 ml-2"><?php echo $invoice['days_overdue']; ?> day<?php echo $invoice['days_overdue'] > 1 ? 's' : ''; ?> overdue</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="font-semibold text-red-600"><?php echo formatCurrency($invoice['balance_due']); ?></span>
                                <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="text-sm bg-red-100 text-red-700 px-3 py-1 rounded-lg hover:bg-red-200 transition-colors">
                                    View
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- KEY METRICS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- This Week's Income -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-gray-600 text-lg"></i>
                    </div>
                    <span class="text-sm text-gray-600 font-semibold">This Week</span>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo formatCurrency($thisWeekPayments['total']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo $thisWeekPayments['count']; ?> payment<?php echo $thisWeekPayments['count'] != 1 ? 's' : ''; ?> received</p>
                </div>
            </div>

            <!-- Monthly Progress -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-trending-up text-gray-600 text-lg"></i>
                    </div>
                    <span class="text-sm <?php echo $monthlyChange >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-semibold">
                        <?php echo $monthlyChange >= 0 ? '+' : ''; ?><?php echo number_format($monthlyChange, 1); ?>%
                    </span>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo formatCurrency($monthlyComparison['this_month']); ?></p>
                    <p class="text-sm text-gray-500">This month vs last month</p>
                </div>
            </div>

            <!-- Outstanding Money -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-gray-600 text-lg"></i>
                    </div>
                    <span class="text-sm text-gray-600 font-semibold">Pending</span>
                </div>
                <div>
                    <p class="text-3xl font-bold text-gray-900"><?php echo formatCurrency($totalOutstanding); ?></p>
                    <p class="text-sm text-gray-500">Total outstanding</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Due Soon -->
            <div class="xl:col-span-2 space-y-6">
                <?php if (!empty($dueSoonInvoices)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-gray-600"></i>
                            Due This Week
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Follow up on these to ensure timely payment</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($dueSoonInvoices as $invoice): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clock text-gray-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['invoice_number']); ?> • Due <?php echo date('M d', strtotime($invoice['due_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900"><?php echo formatCurrency($invoice['balance_due']); ?></p>
                                    <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="text-sm text-gray-700 hover:text-gray-900 font-medium">View →</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Payments -->
                <?php if (!empty($recentPayments)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-check-circle mr-3 text-gray-600"></i>
                            Recent Payments
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Latest money received</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($recentPayments as $payment): ?>
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-dollar-sign text-gray-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($payment['method']); ?> • <?php echo date('M d', strtotime($payment['payment_date'])); ?></p>
                                    </div>
                                </div>
                                <span class="font-semibold text-green-600">+<?php echo formatCurrency($payment['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Top Customers -->
                <?php if (!empty($topCustomers)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-star mr-3 text-gray-600"></i>
                            Top Customers
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Your best clients by revenue</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($topCustomers as $customer): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $customer['invoice_count']; ?> invoice<?php echo $customer['invoice_count'] > 1 ? 's' : ''; ?></p>
                                    </div>
                                </div>
                                <span class="font-semibold text-gray-900"><?php echo formatCurrency($customer['total_paid']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2 text-gray-600"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="create-invoice.php" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-plus-circle text-gray-600 mr-3"></i>
                            <span class="font-semibold text-gray-900 group-hover:text-gray-700">Create New Invoice</span>
                        </a>
                        <a href="payments.php" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-credit-card text-gray-600 mr-3"></i>
                            <span class="font-semibold text-gray-900 group-hover:text-gray-700">Record Payment</span>
                        </a>
                        <a href="invoices.php?status=Unpaid" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-search text-gray-600 mr-3"></i>
                            <span class="font-semibold text-gray-900 group-hover:text-gray-700">View Unpaid Invoices</span>
                        </a>
                    </div>
                </div>

                <!-- Today's Summary -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-calendar-day mr-2 text-gray-600"></i>
                        Today's Summary
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium text-gray-900"><?php echo date('F d, Y'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Overdue invoices:</span>
                            <span class="font-medium text-red-600"><?php echo count($overdueInvoices); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Due this week:</span>
                            <span class="font-medium text-yellow-600"><?php echo count($dueSoonInvoices); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">This week's income:</span>
                            <span class="font-medium text-green-600"><?php echo formatCurrency($thisWeekPayments['total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>