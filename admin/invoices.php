<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'date';
$sortOrder = $_GET['order'] ?? 'DESC';

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(c.name LIKE ? OR i.invoice_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where[] = "i.status = ?";
    $params[] = $status;
}

$allowedSorts = ['date', 'due_date', 'total', 'customer_name', 'invoice_number'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'date';
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

if ($sortBy === 'customer_name') {
    $orderBy = "c.name $sortOrder";
} else {
    $orderBy = "i.$sortBy $sortOrder";
}

// Check if property_id column exists
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
    $hasPropertyColumn = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $hasPropertyColumn = false;
}

// Simple query without any complex joins or subqueries
$sql = "
    SELECT DISTINCT i.id, i.invoice_number, i.date, i.due_date, i.total, i.unique_id, i.customer_id, i.property_id,
           c.name as customer_name
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Secure debug: Only allow in debug mode
if (isset($_GET['debug4']) && defined('APP_DEBUG') && APP_DEBUG) {
    logSecureError("Debug mode accessed for invoices", [
        'sql' => $sql,
        'params' => $params,
        'result_count' => count($invoices)
    ], 'DEBUG');
    
    echo "<pre>Debug mode enabled. Check error logs for SQL details.\n";
    echo "Total rows: " . count($invoices) . "</pre>";
    exit;
}

// Now fetch additional data for each invoice
foreach ($invoices as $key => $invoice) {
    // Get payment total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoice['id']]);
    $invoice['total_paid'] = $stmt->fetchColumn();
    
    // Get property info if property_id exists
    if ($hasPropertyColumn && !empty($invoice['property_id'])) {
        $stmt = $pdo->prepare("SELECT property_name, property_type FROM customer_properties WHERE id = ?");
        $stmt->execute([$invoice['property_id']]);
        $property = $stmt->fetch();
        $invoice['property_name'] = $property ? $property['property_name'] : null;
        $invoice['property_type'] = $property ? $property['property_type'] : null;
    } else {
        $invoice['property_name'] = null;
        $invoice['property_type'] = null;
    }
    
    $invoice['actual_status'] = getInvoiceStatus($invoice, $pdo);
    $invoice['balance_due'] = $invoice['total'] - $invoice['total_paid'];
    
    // Update the array with the modified invoice
    $invoices[$key] = $invoice;
}

// Remove any reference that might be lingering
unset($invoice);

// Add cache busting headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices<?php 
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
        <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-8">
            <div class="mb-4 lg:mb-0">
                <h2 class="text-3xl font-bold text-gray-900">All Invoices</h2>
                <p class="text-gray-600 mt-1">Manage and track all your invoices</p>
            </div>
            <a href="create-invoice.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                <i class="fas fa-plus mr-2"></i>Create New Invoice
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-filter mr-3 text-gray-600"></i>
                    Filter & Search
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Customer name or invoice #"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <option value="">All Statuses</option>
                            <option value="Unpaid" <?php echo $status === 'Unpaid' ? 'selected' : ''; ?>>🔴 Unpaid</option>
                            <option value="Partial" <?php echo $status === 'Partial' ? 'selected' : ''; ?>>🟡 Partial</option>
                            <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>🟢 Paid</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                        <select name="sort" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>📅 Invoice Date</option>
                            <option value="due_date" <?php echo $sortBy === 'due_date' ? 'selected' : ''; ?>>⏰ Due Date</option>
                            <option value="total" <?php echo $sortBy === 'total' ? 'selected' : ''; ?>>💰 Amount</option>
                            <option value="customer_name" <?php echo $sortBy === 'customer_name' ? 'selected' : ''; ?>>👤 Customer</option>
                            <option value="invoice_number" <?php echo $sortBy === 'invoice_number' ? 'selected' : ''; ?>>🔢 Invoice #</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-3">
                        <button type="submit" class="flex-1 px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="invoices.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <input type="hidden" name="order" value="<?php echo $sortOrder; ?>">
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php
            $totalInvoices = count($invoices);
            $totalAmount = array_sum(array_column($invoices, 'total'));
            $totalPaid = array_sum(array_column($invoices, 'total_paid'));
            $totalOutstanding = $totalAmount - $totalPaid;
            ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Invoices</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalInvoices; ?></p>
                        <p class="text-sm text-gray-500">invoice<?php echo $totalInvoices != 1 ? 's' : ''; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Amount</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($totalAmount); ?></p>
                        <p class="text-sm text-gray-500">invoiced</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Outstanding</p>
                        <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totalOutstanding); ?></p>
                        <p class="text-sm text-gray-500">unpaid</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-3 text-gray-600"></i>
                    Invoice List
                </h3>
                <p class="text-sm text-gray-600 mt-1"><?php echo count($invoices); ?> result<?php echo count($invoices) != 1 ? 's' : ''; ?> found</p>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($invoices)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-gray-400 text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-2">No Invoices Found</h4>
                    <p class="text-gray-600 mb-6">No invoices match your current filters.</p>
                    <a href="create-invoice.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-plus mr-2"></i>Create First Invoice
                    </a>
                </div>
                <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-6 py-4 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'invoice_number', 'order' => $sortBy === 'invoice_number' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                                    Invoice #
                                    <?php if ($sortBy === 'invoice_number'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'customer_name', 'order' => $sortBy === 'customer_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                                    Customer
                                    <?php if ($sortBy === 'customer_name'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <span class="text-sm font-medium text-gray-700">Property</span>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'date', 'order' => $sortBy === 'date' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                                    Date
                                    <?php if ($sortBy === 'date'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'due_date', 'order' => $sortBy === 'due_date' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                                    Due Date
                                    <?php if ($sortBy === 'due_date'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-right">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total', 'order' => $sortBy === 'total' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="flex items-center justify-end text-sm font-medium text-gray-700 hover:text-gray-900">
                                    Amount
                                    <?php if ($sortBy === 'total'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-1 text-xs"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-medium text-gray-700">Balance</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        $rowCount = 0;
                        foreach ($invoices as $index => $invoice): 
                            $rowCount++;
                        ?>
                        <!-- Row <?php echo $rowCount; ?>, Array Index: <?php echo $index; ?>, ID: <?php echo $invoice['id']; ?> -->
                        <tr class="hover:bg-gray-50 transition-colors <?php echo $invoice['actual_status'] === 'Unpaid' && strtotime($invoice['due_date']) < time() ? 'bg-red-50' : ''; ?>">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($invoice['property_name']): ?>
                                <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($invoice['property_name']); ?></div>
                                <?php if ($invoice['property_type'] && $invoice['property_type'] !== 'Other'): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                    <?php echo htmlspecialchars($invoice['property_type']); ?>
                                </span>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="text-gray-400 text-sm">No property</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-600"><?php echo date('M d, Y', strtotime($invoice['date'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-600">
                                    <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                    <?php if ($invoice['actual_status'] === 'Unpaid' && strtotime($invoice['due_date']) < time()): ?>
                                    <span class="block text-xs text-red-600 font-medium">Overdue</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-semibold text-gray-900"><?php echo formatCurrency($invoice['total']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full <?php echo getStatusBadgeClass($invoice['actual_status']); ?>">
                                    <?php echo $invoice['actual_status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-semibold <?php echo $invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo formatCurrency($invoice['balance_due']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($invoice['balance_due'] > 0): ?>
                                    <a href="payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Add Payment">
                                        <i class="fas fa-plus-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>