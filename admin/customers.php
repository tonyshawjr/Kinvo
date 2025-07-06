<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

// Get filter parameters
$search = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "
    SELECT c.*, 
           COALESCE(invoice_stats.total_invoices, 0) as total_invoices,
           COALESCE(invoice_stats.total_invoiced, 0) as total_invoiced,
           COALESCE(payment_stats.total_paid, 0) as total_paid,
           (COALESCE(invoice_stats.total_invoiced, 0) - COALESCE(payment_stats.total_paid, 0)) as total_outstanding,
           COALESCE(invoice_stats.unpaid_invoices, 0) as unpaid_invoices,
           invoice_stats.last_invoice_date
    FROM customers c 
    LEFT JOIN (
        SELECT customer_id,
               COUNT(*) as total_invoices,
               SUM(total) as total_invoiced,
               COUNT(CASE WHEN status != 'Paid' THEN 1 END) as unpaid_invoices,
               MAX(date) as last_invoice_date
        FROM invoices 
        GROUP BY customer_id
    ) invoice_stats ON c.id = invoice_stats.customer_id
    LEFT JOIN (
        SELECT i.customer_id,
               SUM(p.amount) as total_paid
        FROM invoices i
        JOIN payments p ON i.id = p.invoice_id
        GROUP BY i.customer_id
    ) payment_stats ON c.id = payment_stats.customer_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Calculate summary stats
$totalCustomers = count($customers);
$activeCustomers = count(array_filter($customers, function($c) { return $c['total_invoices'] > 0; }));
$totalRevenue = array_sum(array_column($customers, 'total_paid'));
$totalOutstanding = array_sum(array_column($customers, 'total_outstanding'));

// Check for success/error messages
$deleted = $_GET['deleted'] ?? false;
$deletedName = $_GET['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers<?php 
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
                <h2 class="text-3xl font-bold text-gray-900">Customer Management</h2>
                <p class="text-gray-600 mt-1">View and manage all your customers</p>
            </div>
            <a href="create-customer.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                <i class="fas fa-user-plus mr-2"></i>Add New Customer
            </a>
        </div>

        <?php if ($deleted): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Customer Deleted Successfully!</h3>
                    <p class="text-gray-600">Customer "<?php echo htmlspecialchars($deletedName); ?>" has been removed from your system.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-search mr-3 text-gray-600"></i>
                    Search Customers
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, email, or phone..."
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all">
                    </div>
                    <button type="submit" class="px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <a href="customers.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                        <i class="fas fa-times"></i>
                    </a>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Customers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalCustomers; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Active Customers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $activeCustomers; ?></p>
                        <p class="text-sm text-gray-500">with invoices</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-check text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($totalRevenue); ?></p>
                        <p class="text-sm text-gray-500">from all customers</p>
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
                        <p class="text-sm text-gray-500">unpaid amount</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-gray-600 text-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customers List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-3 text-gray-600"></i>
                    Customer List
                </h3>
                <p class="text-sm text-gray-600 mt-1"><?php echo count($customers); ?> customer<?php echo count($customers) != 1 ? 's' : ''; ?> found</p>
            </div>
            <?php if (empty($customers)): ?>
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-users text-gray-400 text-3xl"></i>
                </div>
                <h4 class="text-xl font-semibold text-gray-900 mb-2">No Customers Found</h4>
                <p class="text-gray-600 mb-6">No customers match your current search.</p>
                <a href="create-customer.php" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                    <i class="fas fa-user-plus mr-2"></i>Add First Customer
                </a>
            </div>
            <?php else: ?>
            <!-- Desktop Table (hidden on mobile) -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Customer</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Contact</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Invoices</th>
                            <th class="px-6 py-4 text-right text-sm font-medium text-gray-700">Total Revenue</th>
                            <th class="px-6 py-4 text-right text-sm font-medium text-gray-700">Outstanding</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Last Invoice</th>
                            <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($customers as $customer): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></div>
                                    <?php if ($customer['total_invoices'] > 0): ?>
                                    <div class="text-sm text-green-600 font-medium">Active Customer</div>
                                    <?php else: ?>
                                    <div class="text-sm text-gray-500">No invoices yet</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <?php if ($customer['email']): ?>
                                    <div class="flex items-center mb-1">
                                        <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-gray-700 hover:text-gray-900">
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($customer['phone']): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-phone text-gray-400 mr-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-gray-700 hover:text-gray-900">
                                            <?php echo htmlspecialchars($customer['phone']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="font-semibold text-gray-900"><?php echo $customer['total_invoices']; ?></div>
                                <?php if ($customer['unpaid_invoices'] > 0): ?>
                                <div class="text-xs text-red-600 font-medium"><?php echo $customer['unpaid_invoices']; ?> unpaid</div>
                                <?php else: ?>
                                <div class="text-xs text-green-600">All paid</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-semibold text-gray-900"><?php echo formatCurrency($customer['total_paid']); ?></div>
                                <div class="text-xs text-gray-500">of <?php echo formatCurrency($customer['total_invoiced']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-semibold <?php echo $customer['total_outstanding'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                    <?php echo formatCurrency($customer['total_outstanding']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($customer['last_invoice_date']): ?>
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($customer['last_invoice_date'])); ?></div>
                                <?php else: ?>
                                <div class="text-sm text-gray-500">Never</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="customer-edit.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Edit Customer">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($customer['total_invoices'] == 0): ?>
                                    <button onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>')" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Delete Customer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card Layout (shown on mobile) -->
            <div class="lg:hidden divide-y divide-gray-100">
                <?php foreach ($customers as $customer): ?>
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <!-- Customer Name & Status -->
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($customer['name']); ?></h4>
                            <?php if ($customer['total_invoices'] > 0): ?>
                            <span class="inline-block px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active Customer</span>
                            <?php else: ?>
                            <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">No invoices yet</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="customer-edit.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Edit Customer">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($customer['total_invoices'] == 0): ?>
                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>')" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" title="Delete Customer">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="mb-3">
                        <?php if ($customer['email']): ?>
                        <div class="flex items-center mb-2">
                            <i class="fas fa-envelope text-gray-400 mr-2 w-4"></i>
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-sm text-gray-700 hover:text-gray-900">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($customer['phone']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-gray-400 mr-2 w-4"></i>
                            <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-sm text-gray-700 hover:text-gray-900">
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Financial Summary -->
                    <div class="grid grid-cols-2 gap-4 mb-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Invoices</div>
                            <div class="font-semibold text-gray-900"><?php echo $customer['total_invoices']; ?></div>
                            <?php if ($customer['unpaid_invoices'] > 0): ?>
                            <div class="text-xs text-red-600 font-medium"><?php echo $customer['unpaid_invoices']; ?> unpaid</div>
                            <?php else: ?>
                            <div class="text-xs text-green-600">All paid</div>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Total Revenue</div>
                            <div class="font-semibold text-gray-900"><?php echo formatCurrency($customer['total_paid']); ?></div>
                            <div class="text-xs text-gray-500">of <?php echo formatCurrency($customer['total_invoiced']); ?></div>
                        </div>
                    </div>

                    <!-- Outstanding & Last Invoice -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Outstanding</div>
                            <div class="font-semibold <?php echo $customer['total_outstanding'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo formatCurrency($customer['total_outstanding']); ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-xs text-gray-500 mb-1">Last Invoice</div>
                            <?php if ($customer['last_invoice_date']): ?>
                            <div class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($customer['last_invoice_date'])); ?></div>
                            <?php else: ?>
                            <div class="font-semibold text-gray-500">Never</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function deleteCustomer(customerId, customerName) {
            if (confirm(`Are you sure you want to delete customer "${customerName}"? This action cannot be undone.`)) {
                window.location.href = `customer-delete.php?id=${customerId}`;
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>