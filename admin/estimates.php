<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

// Update expired estimates
updateExpiredEstimates($pdo);

// Get filter parameters
$status = $_GET['status'] ?? '';
$customer_id = $_GET['customer'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT e.*, c.name as customer_name, 
           COUNT(ei.id) as item_count,
           (SELECT COUNT(*) FROM invoices WHERE id = e.converted_invoice_id) as has_invoice
    FROM estimates e
    LEFT JOIN customers c ON e.customer_id = c.id
    LEFT JOIN estimate_items ei ON e.id = ei.estimate_id
    WHERE 1=1
";

$params = [];

if ($status) {
    $query .= " AND e.status = ?";
    $params[] = $status;
}

if ($customer_id) {
    $query .= " AND e.customer_id = ?";
    $params[] = $customer_id;
}

if ($search) {
    $query .= " AND (e.estimate_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY e.id ORDER BY e.created_at DESC";

// Prepare and execute
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$estimates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customers for filter dropdown
$customersStmt = $pdo->query("SELECT id, name FROM customers ORDER BY name");
$customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get estimate count by status
$statusCounts = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM estimates 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimates<?php 
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
                <h2 class="text-3xl font-bold text-gray-900">All Estimates</h2>
                <p class="text-gray-600 mt-1">Manage and track all your quotes and estimates</p>
            </div>
            <a href="create-estimate.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                <i class="fas fa-plus mr-2"></i>Create New Estimate
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php
            $totalEstimates = array_sum($statusCounts);
            $pendingCount = ($statusCounts['Draft'] ?? 0) + ($statusCounts['Sent'] ?? 0);
            $approvedCount = $statusCounts['Approved'] ?? 0;
            ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Estimates</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalEstimates; ?></p>
                        <p class="text-sm text-gray-500">estimate<?php echo $totalEstimates != 1 ? 's' : ''; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Pending</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $pendingCount; ?></p>
                        <p class="text-sm text-gray-500">awaiting response</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-blue-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Approved</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $approvedCount; ?></p>
                        <p class="text-sm text-gray-500">ready to convert</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-lg"></i>
                    </div>
                </div>
            </div>
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
                               placeholder="Customer name or estimate #"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <option value="">All Statuses</option>
                            <?php foreach (['Draft', 'Sent', 'Approved', 'Rejected', 'Expired'] as $statusOption): ?>
                                <option value="<?php echo $statusOption; ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>>
                                    <?php 
                                    $emoji = '';
                                    switch($statusOption) {
                                        case 'Draft': $emoji = '📝'; break;
                                        case 'Sent': $emoji = '📤'; break;
                                        case 'Approved': $emoji = '✅'; break;
                                        case 'Rejected': $emoji = '❌'; break;
                                        case 'Expired': $emoji = '⏰'; break;
                                    }
                                    echo $emoji . ' ' . $statusOption; 
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
                        <select name="customer" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo $customer_id == $customer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end space-x-3">
                        <button type="submit" class="flex-1 px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <?php if ($search || $status || $customer_id): ?>
                            <a href="estimates.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estimates List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-3 text-gray-600"></i>
                    Estimate List
                </h3>
                <p class="text-sm text-gray-600 mt-1"><?php echo count($estimates); ?> result<?php echo count($estimates) != 1 ? 's' : ''; ?> found</p>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($estimates)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-gray-400 text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-2">No Estimates Found</h4>
                    <p class="text-gray-600 mb-6">No estimates match your current filters.</p>
                    <a href="create-estimate.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-plus mr-2"></i>Create First Estimate
                    </a>
                </div>
                <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-6 py-4 text-left">
                                <span class="text-sm font-medium text-gray-700">Estimate #</span>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <span class="text-sm font-medium text-gray-700">Customer</span>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <span class="text-sm font-medium text-gray-700">Date</span>
                            </th>
                            <th class="px-6 py-4 text-left">
                                <span class="text-sm font-medium text-gray-700">Expires</span>
                            </th>
                            <th class="px-6 py-4 text-right">
                                <span class="text-sm font-medium text-gray-700">Amount</span>
                            </th>
                            <th class="px-6 py-4 text-center">
                                <span class="text-sm font-medium text-gray-700">Status</span>
                            </th>
                            <th class="px-6 py-4 text-center">
                                <span class="text-sm font-medium text-gray-700">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($estimates as $estimate): ?>
                        <tr class="hover:bg-gray-50 transition-colors <?php echo $estimate['status'] === 'Expired' ? 'bg-red-50' : ''; ?>">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($estimate['estimate_number']); ?>
                                    <?php if ($estimate['has_invoice']): ?>
                                        <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Invoiced
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-900"><?php echo htmlspecialchars($estimate['customer_name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-gray-600"><?php echo date('M d, Y', strtotime($estimate['date'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $expiresDate = strtotime($estimate['expires_date']);
                                $isExpired = $estimate['status'] === 'Expired';
                                $isExpiringSoon = $expiresDate < strtotime('+7 days') && !$isExpired && in_array($estimate['status'], ['Draft', 'Sent']);
                                ?>
                                <div class="text-gray-600">
                                    <?php echo date('M d, Y', $expiresDate); ?>
                                    <?php if ($isExpired): ?>
                                        <span class="block text-xs text-red-600 font-medium">Expired</span>
                                    <?php elseif ($isExpiringSoon): ?>
                                        <span class="block text-xs text-yellow-600 font-medium">Expiring Soon</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-semibold text-gray-900"><?php echo formatCurrency($estimate['total']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php echo formatEstimateStatus($estimate['status']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="estimate-detail.php?id=<?php echo $estimate['id']; ?>" 
                                       class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (canEditEstimate($estimate)): ?>
                                        <a href="edit-estimate.php?id=<?php echo $estimate['id']; ?>" 
                                           class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Edit Estimate">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (canConvertEstimate($estimate)): ?>
                                        <a href="convert-estimate.php?id=<?php echo $estimate['id']; ?>" 
                                           class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition-colors" title="Convert to Invoice">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="../public/view-estimate.php?id=<?php echo $estimate['unique_id']; ?>" 
                                       target="_blank" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-colors" title="Share Link">
                                        <i class="fas fa-share"></i>
                                    </a>
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