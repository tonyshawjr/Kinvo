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

// Filter parameters
$year_filter = $_GET['year'] ?? date('Y');
$method_filter = $_GET['method'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ['i.customer_id = ?'];
$params = [$customer_id];

if ($year_filter !== 'all') {
    $where_conditions[] = "YEAR(p.payment_date) = ?";
    $params[] = $year_filter;
}

if ($method_filter !== 'all') {
    $where_conditions[] = "p.method = ?";
    $params[] = $method_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR p.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get payments
$sql = "
    SELECT p.*, i.invoice_number, i.total as invoice_total, i.unique_id as invoice_unique_id
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE {$where_clause}
    ORDER BY p.payment_date DESC, p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get payment summary
$stmt = $pdo->prepare("
    SELECT 
        SUM(p.amount) as total_payments,
        COUNT(p.id) as payment_count,
        MIN(p.payment_date) as first_payment,
        MAX(p.payment_date) as last_payment
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.customer_id = ?
");
$stmt->execute([$customer_id]);
$payment_summary = $stmt->fetch();

// Get available years
$stmt = $pdo->prepare("
    SELECT DISTINCT YEAR(p.payment_date) as year 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.customer_id = ? 
    ORDER BY year DESC
");
$stmt->execute([$customer_id]);
$available_years = $stmt->fetchAll();

// Get payment methods
$stmt = $pdo->prepare("
    SELECT DISTINCT p.method 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id
    WHERE i.customer_id = ? 
    ORDER BY p.method
");
$stmt->execute([$customer_id]);
$payment_methods = $stmt->fetchAll();

$businessSettings = getBusinessSettings($pdo);

// Log activity
logClientActivity($pdo, $customer_id, 'payments_view', 'Viewed payment history');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
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
                <h2 class="text-3xl font-bold text-gray-900">Payment History</h2>
                <p class="text-gray-600 mt-1">View and track all your payments with detailed filtering options</p>
            </div>

            <!-- Payment Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Payments</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($payment_summary['total_payments'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-credit-card text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Payment Count</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $payment_summary['payment_count'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">First Payment</p>
                        <p class="text-sm font-bold text-gray-900">
                            <?php 
                            echo $payment_summary['first_payment'] 
                                ? date('M j, Y', strtotime($payment_summary['first_payment']))
                                : 'No payments'; 
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Last Payment</p>
                        <p class="text-sm font-bold text-gray-900">
                            <?php 
                            echo $payment_summary['last_payment'] 
                                ? date('M j, Y', strtotime($payment_summary['last_payment']))
                                : 'No payments'; 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                    <select name="year" id="year" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?php echo $year_filter === 'all' ? 'selected' : ''; ?>>All Years</option>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                    <select name="method" id="method" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?php echo $method_filter === 'all' ? 'selected' : ''; ?>>All Methods</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?php echo htmlspecialchars($method['method']); ?>" <?php echo $method_filter === $method['method'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method['method']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Invoice number or notes..."
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

            <!-- Payment List -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">
                    Payment History (<?php echo count($payments); ?> found)
                </h2>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('g:i A', strtotime($payment['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Invoice Total: <?php echo formatCurrency($payment['invoice_total']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-green-600">
                                        <?php echo formatCurrency($payment['amount']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['method']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['notes'] ?? ''); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <a href="/public/view-invoice.php?id=<?php echo $payment['invoice_unique_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-500" target="_blank" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No payments found matching your criteria
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