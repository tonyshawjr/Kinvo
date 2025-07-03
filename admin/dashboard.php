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

// REVENUE CHART DATA - Last 12 months
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total
    FROM payments 
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month ASC
");
$monthlyRevenue = $stmt->fetchAll();

// Fill in missing months with 0
$chartData = [];
$chartLabels = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    $chartLabels[] = $monthName;
    
    $found = false;
    foreach ($monthlyRevenue as $revenue) {
        if ($revenue['month'] === $date) {
            $chartData[] = (float)$revenue['total'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chartData[] = 0;
    }
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Overdue Invoices Need Attention</h3>
                    <p class="text-gray-600 mb-4"><?php echo count($overdueInvoices); ?> invoice<?php echo count($overdueInvoices) > 1 ? 's are' : ' is'; ?> past due. Consider following up with these customers.</p>
                    <div class="space-y-3">
                        <?php foreach ($overdueInvoices as $invoice): ?>
                        <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div>
                                <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></span>
                                <span class="text-gray-600 ml-2"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                <span class="text-sm text-gray-500 ml-2"><?php echo $invoice['days_overdue']; ?> day<?php echo $invoice['days_overdue'] > 1 ? 's' : ''; ?> overdue</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="font-semibold text-gray-900"><?php echo formatCurrency($invoice['balance_due']); ?></span>
                                <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="text-sm bg-gray-900 text-white px-3 py-1 rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                                    View
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- KEY METRICS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Outstanding Money -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Outstanding</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($totalOutstanding); ?></p>
                    <p class="text-sm text-gray-500">Total owed to you</p>
                </div>
            </div>

            <!-- This Week's Income -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">This Week</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($thisWeekPayments['total']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo $thisWeekPayments['count']; ?> payment<?php echo $thisWeekPayments['count'] != 1 ? 's' : ''; ?> received</p>
                </div>
            </div>

            <!-- Monthly Progress -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-2 mb-2">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">This Month</p>
                        <span class="text-sm font-semibold text-gray-600">
                            <?php echo $monthlyChange >= 0 ? '+' : ''; ?><?php echo number_format($monthlyChange, 1); ?>%
                        </span>
                    </div>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($monthlyComparison['this_month']); ?></p>
                    <p class="text-sm text-gray-500">vs last month</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Due Soon -->
            <div class="xl:col-span-2 space-y-6">
            <!-- Due This Week -->
            <div>
                <?php if (!empty($dueSoonInvoices)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Due This Week (<?php echo count($dueSoonInvoices); ?>)
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Follow up to ensure timely payment</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($dueSoonInvoices as $invoice): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['invoice_number']); ?> • Due <?php echo date('M d', strtotime($invoice['due_date'])); ?></p>
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
                <?php else: ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">All Caught Up!</h3>
                    <p class="text-gray-600">No invoices due this week.</p>
                </div>
                <?php endif; ?>
            </div>

            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Revenue Trend -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Revenue Trend</h3>
                        <select id="chartPeriod" class="text-sm border border-gray-300 rounded-lg px-3 py-1 focus:border-gray-900 focus:ring-1 focus:ring-gray-900">
                            <option value="12">Last 12 months</option>
                            <option value="6">Last 6 months</option>
                            <option value="3">Last 3 months</option>
                        </select>
                    </div>
                    <div class="h-64">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <?php if (!empty($recentPayments)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Recent Payments
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Latest money received</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($recentPayments as $payment): ?>
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($payment['method']); ?> • <?php echo date('M d', strtotime($payment['payment_date'])); ?></p>
                                </div>
                                <span class="font-semibold text-gray-900">+<?php echo formatCurrency($payment['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Recent Payments</h3>
                    <p class="text-gray-600">Payments will appear here once received.</p>
                </div>
                <?php endif; ?>
            </div>

            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
    // Revenue Chart
    const chartData = <?php echo json_encode($chartData); ?>;
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    
    let revenueChart;
    
    function initChart(months = 12) {
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        // Slice data based on selected period
        const dataSlice = chartData.slice(-months);
        const labelSlice = chartLabels.slice(-months);
        
        if (revenueChart) {
            revenueChart.destroy();
        }
        
        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labelSlice,
                datasets: [{
                    label: 'Revenue',
                    data: dataSlice,
                    borderColor: '#374151',
                    backgroundColor: '#374151',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            }
        });
    }
    
    // Initialize chart
    document.addEventListener('DOMContentLoaded', function() {
        initChart();
        
        // Handle period changes
        document.getElementById('chartPeriod').addEventListener('change', function() {
            initChart(parseInt(this.value));
        });
    });
    </script>
</body>
</html>