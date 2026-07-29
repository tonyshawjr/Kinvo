<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/payment-functions.php';

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
            <div class="flex flex-wrap gap-3">
                <a href="record-payments.php" class="inline-flex items-center px-6 py-3 bg-green-700 text-white rounded-lg hover:bg-green-800 transition-colors font-semibold">
                    <i class="fas fa-check-double mr-2" aria-hidden="true"></i>Record Payments
                </a>
                <a href="create-invoice.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i>Create New Invoice
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-800"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <span class="text-red-800"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

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
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Customer name or invoice #"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" id="search">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" id="status">
                            <option value="">All Statuses</option>
                            <option value="Unpaid" <?php echo $status === 'Unpaid' ? 'selected' : ''; ?>>🔴 Unpaid</option>
                            <option value="Partial" <?php echo $status === 'Partial' ? 'selected' : ''; ?>>🟡 Partial</option>
                            <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>🟢 Paid</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                        <select name="sort" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all" id="sort">
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
                        <a href="invoices.php" aria-label="Clear all filters" class="min-h-[44px] inline-flex items-center px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
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
                        <p class="text-sm font-medium text-gray-700 mb-1">Total Invoices</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalInvoices; ?></p>
                        <p class="text-sm text-gray-700">invoice<?php echo $totalInvoices != 1 ? 's' : ''; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Total Amount</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($totalAmount); ?></p>
                        <p class="text-sm text-gray-700">invoiced</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Outstanding</p>
                        <p class="text-2xl font-bold text-red-700"><?php echo formatCurrency($totalOutstanding); ?></p>
                        <p class="text-sm text-gray-700">unpaid</p>
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
                        <i class="fas fa-file-invoice text-gray-700 text-3xl"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-900 mb-2">No Invoices Found</h4>
                    <p class="text-gray-600 mb-6">No invoices match your current filters.</p>
                    <a href="create-invoice.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-plus mr-2"></i>Create First Invoice
                    </a>
                </div>
                <?php else: ?>
                <table class="w-full">
                    <caption class="sr-only">All invoices, showing customer, due date, amount owed and available actions</caption>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th scope="col" class="w-full px-5 py-3 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'customer_name', 'order' => $sortBy === 'customer_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-gray-700 hover:text-gray-900">
                                    Customer &amp; property
                                    <?php if ($sortBy === 'customer_name'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-2 text-xs" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col" class="w-px whitespace-nowrap px-5 py-3 text-left">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'due_date', 'order' => $sortBy === 'due_date' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-gray-700 hover:text-gray-900">
                                    Due
                                    <?php if ($sortBy === 'due_date'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-2 text-xs" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col" class="w-px whitespace-nowrap px-5 py-3 text-right">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total', 'order' => $sortBy === 'total' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="inline-flex items-center justify-end min-h-[44px] text-sm font-semibold text-gray-700 hover:text-gray-900">
                                    Owed
                                    <?php if ($sortBy === 'total'): ?>
                                        <i class="fas fa-chevron-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> ml-2 text-xs" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col" class="w-px whitespace-nowrap px-5 py-3 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($invoices as $invoice):
                            $isOverdue = $invoice['balance_due'] > 0 && strtotime($invoice['due_date']) < strtotime('today');
                            $daysOverdue = $isOverdue ? (int) floor((strtotime('today') - strtotime($invoice['due_date'])) / 86400) : 0;
                            $isPartial = $invoice['total_paid'] > 0 && $invoice['balance_due'] > 0;
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="w-full px-5 py-3">
                                <div class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                <div class="text-gray-700">
                                    <?php if ($invoice['property_name']): ?>
                                        <?php echo htmlspecialchars($invoice['property_name']); ?>
                                        <?php if ($invoice['property_type'] && $invoice['property_type'] !== 'Other'): ?>
                                        <span class="text-gray-700">&middot; <?php echo htmlspecialchars($invoice['property_type']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-700">No property</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-700 mt-1">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?> &middot; sent <?php echo date('M j', strtotime($invoice['date'])); ?>
                                </div>
                            </td>
                            <td class="w-px px-5 py-3 whitespace-nowrap align-top">
                                <div class="text-gray-900"><?php echo date('M j, Y', strtotime($invoice['due_date'])); ?></div>
                                <?php if ($isOverdue): ?>
                                <div class="text-sm font-semibold text-red-700"><?php echo $daysOverdue; ?> days late</div>
                                <?php elseif ($invoice['balance_due'] <= 0): ?>
                                <div class="text-sm font-semibold text-green-700">Paid</div>
                                <?php endif; ?>
                            </td>
                            <td class="w-px px-5 py-3 text-right whitespace-nowrap align-top">
                                <div class="text-lg font-bold <?php echo $invoice['balance_due'] > 0 ? 'text-gray-900' : 'text-gray-700'; ?>">
                                    <?php echo formatCurrency($invoice['balance_due'] > 0 ? $invoice['balance_due'] : $invoice['total']); ?>
                                </div>
                                <?php if ($isPartial): ?>
                                <div class="text-sm text-gray-700">of <?php echo formatCurrency($invoice['total']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="w-px px-5 py-3 align-top">
                                <div class="flex flex-col items-end gap-1">
                                    <?php if ($invoice['balance_due'] > 0): ?>
                                    <form method="POST" action="record-payments.php" class="inline mark-paid-form" data-invoice="<?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES); ?>" data-amount="<?php echo htmlspecialchars(formatCurrency($invoice['balance_due']), ENT_QUOTES); ?>">
                                        <?php echo getCSRFTokenField(); ?>
                                        <input type="hidden" name="invoice_ids[]" value="<?php echo (int) $invoice['id']; ?>">
                                        <input type="hidden" name="method" value="<?php echo htmlspecialchars(getDefaultPaymentMethod()); ?>">
                                        <input type="hidden" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                                        <input type="hidden" name="return_to" value="invoices.php">
                                        <button type="submit" class="min-h-[44px] whitespace-nowrap inline-flex items-center px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 font-semibold text-sm">
                                            <i class="fas fa-check mr-2" aria-hidden="true"></i>Mark Paid
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <div class="flex items-center justify-end gap-1">
                                    <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" aria-label="View invoice <?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES); ?>">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-blue-800 hover:bg-blue-100 rounded-lg transition-colors" aria-label="Edit invoice <?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES); ?>">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES); ?>')" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" aria-label="Delete invoice <?php echo htmlspecialchars($invoice['invoice_number'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                    </div>
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

    <script>
        function deleteInvoice(invoiceId, invoiceNumber) {
            if (confirm(`Are you sure you want to delete invoice #${invoiceNumber}? This action cannot be undone.`)) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete-invoice.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'invoice_id';
                idInput.value = invoiceId;

                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'csrf_token';
                tokenInput.value = <?php echo json_encode(generateCSRFToken()); ?>;

                form.appendChild(idInput);
                form.appendChild(tokenInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.querySelectorAll('.mark-paid-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const label = 'Mark invoice ' + form.dataset.invoice + ' paid in full (' + form.dataset.amount + ')?';
                if (!confirm(label)) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>