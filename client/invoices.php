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
$status_filter = $_GET['status'] ?? 'all';
$year_filter = $_GET['year'] ?? date('Y');
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ['i.customer_id = ?'];
$params = [$customer_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "
        CASE 
            WHEN COALESCE(SUM(p.amount), 0) >= i.total THEN 'Paid'
            WHEN COALESCE(SUM(p.amount), 0) > 0 THEN 'Partial'
            ELSE 'Unpaid'
        END = ?
    ";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR i.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($year_filter !== 'all') {
    $where_conditions[] = "YEAR(i.date) = ?";
    $params[] = $year_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get invoices with payment totals
$sql = "
    SELECT i.*, 
           COALESCE(SUM(p.amount), 0) as total_paid,
           i.total - COALESCE(SUM(p.amount), 0) as balance_due,
           CASE 
               WHEN COALESCE(SUM(p.amount), 0) >= i.total THEN 'Paid'
               WHEN COALESCE(SUM(p.amount), 0) > 0 THEN 'Partial'
               ELSE 'Unpaid'
           END as status
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE {$where_clause}
    GROUP BY i.id
";

// Add HAVING clause for status filter if needed
if ($status_filter !== 'all') {
    $sql .= " HAVING status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY i.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Get available years for filter
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(date) as year FROM invoices WHERE customer_id = ? ORDER BY year DESC");
$stmt->execute([$customer_id]);
$available_years = $stmt->fetchAll();

$businessSettings = getBusinessSettings($pdo);

// Log activity
logClientActivity($pdo, $customer_id, 'invoices_view', 'Viewed invoice list');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
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
                <h2 class="text-3xl font-bold text-gray-900">Your Invoices</h2>
                <p class="text-gray-600 mt-1">View and manage all your invoices with advanced filtering options</p>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Unpaid" <?php echo $status_filter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="Partial" <?php echo $status_filter === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>

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

            <!-- Invoice List -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">
                    Your Invoices (<?php echo count($invoices); ?> found)
                </h2>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($invoice['date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $due_date = strtotime($invoice['due_date']);
                                    $is_overdue = $due_date < time() && $invoice['balance_due'] > 0;
                                    ?>
                                    <span class="<?php echo $is_overdue ? 'text-red-600 font-semibold' : ''; ?>">
                                        <?php echo date('M j, Y', $due_date); ?>
                                        <?php if ($is_overdue): ?>
                                            <i class="fas fa-exclamation-triangle ml-1"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo formatCurrency($invoice['total']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo formatCurrency($invoice['total_paid']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="<?php echo $invoice['balance_due'] > 0 ? 'text-red-600 font-semibold' : 'text-green-600'; ?>">
                                        <?php echo formatCurrency($invoice['balance_due']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadgeClass($invoice['status']); ?>">
                                        <?php echo htmlspecialchars($invoice['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex space-x-2">
                                        <a href="/public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-500" target="_blank" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>&download=1" 
                                           class="text-green-600 hover:text-green-500" title="Download PDF">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No invoices found matching your criteria
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