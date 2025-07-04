<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$customerId = $_GET['id'] ?? null;
$success = '';
$error = '';

if (!$customerId) {
    header('Location: customers.php');
    exit;
}

// Verify customer exists and access is authorized
requireResourceOwnership($pdo, 'customer', $customerId);

// Handle portal creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_portal'])) {
    requireCSRFToken();
    try {
        // Get customer info
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception('Customer not found.');
        }
        
        if (empty($customer['email'])) {
            throw new Exception('Customer must have an email address to create portal access.');
        }
        
        // Check if portal already exists
        $stmt = $pdo->prepare("SELECT id FROM client_auth WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        if ($stmt->fetch()) {
            throw new Exception('Portal access already exists for this customer.');
        }
        
        // Create portal access
        $clientPin = generateClientPIN();
        $created = createClientAuth($pdo, $customerId, $customer['email'], $clientPin);
        
        if ($created) {
            // Create default preferences
            $stmt = $pdo->prepare("INSERT INTO client_preferences (customer_id) VALUES (?)");
            $stmt->execute([$customerId]);
            
            logClientActivity($pdo, $customerId, 'account_created', 'Client portal access created by admin');
            $success = "Portal access created successfully! PIN: $clientPin";
        } else {
            throw new Exception('Failed to create portal access.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get customer information
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Check if customer has portal access
$stmt = $pdo->prepare("SELECT * FROM client_auth WHERE customer_id = ?");
$stmt->execute([$customerId]);
$portalAccess = $stmt->fetch();

// Get customer statistics
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(invoice_stats.total_invoices, 0) as total_invoices,
        COALESCE(invoice_stats.total_invoiced, 0) as total_invoiced,
        COALESCE(payment_stats.total_paid, 0) as total_paid,
        (COALESCE(invoice_stats.total_invoiced, 0) - COALESCE(payment_stats.total_paid, 0)) as total_outstanding,
        COALESCE(invoice_stats.paid_invoices, 0) as paid_invoices,
        COALESCE(invoice_stats.unpaid_invoices, 0) as unpaid_invoices,
        COALESCE(invoice_stats.partial_invoices, 0) as partial_invoices,
        invoice_stats.avg_invoice_amount,
        invoice_stats.first_invoice_date,
        invoice_stats.last_invoice_date
    FROM (SELECT 1) dummy
    LEFT JOIN (
        SELECT 
            COUNT(*) as total_invoices,
            SUM(total) as total_invoiced,
            COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_invoices,
            COUNT(CASE WHEN status = 'Unpaid' THEN 1 END) as unpaid_invoices,
            COUNT(CASE WHEN status = 'Partial' THEN 1 END) as partial_invoices,
            AVG(total) as avg_invoice_amount,
            MIN(date) as first_invoice_date,
            MAX(date) as last_invoice_date
        FROM invoices 
        WHERE customer_id = ?
    ) invoice_stats ON 1=1
    LEFT JOIN (
        SELECT 
            SUM(p.amount) as total_paid
        FROM invoices i
        JOIN payments p ON i.id = p.invoice_id
        WHERE i.customer_id = ?
    ) payment_stats ON 1=1
");
$stmt->execute([$customerId, $customerId]);
$stats = $stmt->fetch();

// Get all invoices for this customer
try {
    // Check if property_id column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
    $hasPropertyColumn = $stmt->rowCount() > 0;
    
    // Simple query without complex joins
    $stmt = $pdo->prepare("
        SELECT DISTINCT i.id, i.invoice_number, i.date, i.due_date, i.total, i.unique_id, i.customer_id, i.property_id
        FROM invoices i 
        WHERE i.customer_id = ? 
        ORDER BY i.date DESC
    ");
    $stmt->execute([$customerId]);
    $rawInvoices = $stmt->fetchAll();
    
    // Deduplicate by invoice ID to ensure no duplicates
    $invoices = [];
    $seenIds = [];
    foreach ($rawInvoices as $invoice) {
        $invoiceId = (string)$invoice['id']; // Convert to string to ensure consistent comparison
        if (!isset($seenIds[$invoiceId])) {
            $seenIds[$invoiceId] = true;
            $invoices[] = $invoice;
        }
    }
} catch (Exception $e) {
    // Fallback to basic query
    $stmt = $pdo->prepare("
        SELECT i.*, 
               COALESCE(SUM(p.amount), 0) as total_paid,
               (i.total - COALESCE(SUM(p.amount), 0)) as balance_due,
               NULL as property_name, NULL as property_type
        FROM invoices i 
        LEFT JOIN payments p ON i.id = p.invoice_id 
        WHERE i.customer_id = ? 
        GROUP BY i.id 
        ORDER BY i.date DESC
    ");
    $stmt->execute([$customerId]);
    $invoices = $stmt->fetchAll();
}

// Calculate status and additional data for each invoice
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
    
    // Calculate balance due
    $invoice['balance_due'] = $invoice['total'] - $invoice['total_paid'];
    
    // Get status
    $invoice['actual_status'] = getInvoiceStatus($invoice, $pdo);
    
    // Update the array
    $invoices[$key] = $invoice;
}

// Remove any lingering reference
unset($invoice);

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, i.invoice_number 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.customer_id = ? 
    ORDER BY p.payment_date DESC 
    LIMIT 10
");
$stmt->execute([$customerId]);
$recentPayments = $stmt->fetchAll();

// Get monthly revenue data for chart (last 12 months)
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyData[$month] = 0;
}

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(p.payment_date, '%Y-%m') as month, SUM(p.amount) as amount
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.customer_id = ? 
    AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$customerId]);
$monthlyPayments = $stmt->fetchAll();

foreach ($monthlyPayments as $payment) {
    $monthlyData[$payment['month']] = floatval($payment['amount']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($customer['name']); ?> - Customer Details<?php 
    $businessSettings = getBusinessSettings($pdo);
    $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
        ? ' - ' . $businessSettings['business_name'] 
        : '';
    echo htmlspecialchars($appName);
    ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-8">
            <div class="mb-4 lg:mb-0">
                <div class="flex items-center space-x-4">
                    <a href="customers.php" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></h2>
                        <p class="text-gray-600 mt-1">Customer details and transaction history</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="customer-statement.php?customer_id=<?php echo $customer['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Generate Statement
                </a>
                <a href="customer-edit.php?id=<?php echo $customer['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                    <i class="fas fa-edit mr-2"></i>Edit Customer
                </a>
                
                <?php if ($portalAccess): ?>
                    <a href="/client/login.php?email=<?php echo urlencode($customer['email']); ?>" target="_blank" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors font-semibold">
                        <i class="fas fa-user-shield mr-2"></i>View Portal
                    </a>
                <?php elseif ($customer['email']): ?>
                    <form method="POST" class="inline">
                        <?php echo getCSRFTokenField(); ?>
                        <button type="submit" name="create_portal" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors font-semibold">
                            <i class="fas fa-plus mr-2"></i>Create Portal
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="create-invoice.php?customer_id=<?php echo $customer['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                    <i class="fas fa-plus mr-2"></i>New Invoice
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-check-circle text-green-600 mt-1"></i>
                <div>
                    <h4 class="font-semibold text-green-900">Portal Created Successfully!</h4>
                    <p class="text-sm text-green-700 mt-1"><?php echo htmlspecialchars($success); ?></p>
                    <div class="mt-2 p-3 bg-white rounded border border-green-200">
                        <p class="text-sm"><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                        <p class="text-sm"><strong>Login URL:</strong> <a href="/client/login.php?email=<?php echo urlencode($customer['email']); ?>" class="text-blue-600 hover:text-blue-500" target="_blank">/client/login.php</a></p>
                    </div>
                    <p class="text-xs text-green-600 mt-2">
                        <i class="fas fa-info-circle"></i> 
                        Send these credentials to your customer so they can access their invoices and payment history.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-exclamation-triangle text-red-600 mt-1"></i>
                <div>
                    <h4 class="font-semibold text-red-900">Error</h4>
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Info Card -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-user mr-3 text-gray-600"></i>
                    Contact Information
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Customer Name</p>
                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-envelope text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email Address</p>
                            <?php if ($customer['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="font-semibold text-gray-900 hover:text-gray-700">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                            <?php else: ?>
                            <p class="font-semibold text-gray-400">Not provided</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-phone text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Phone Number</p>
                            <?php if ($customer['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="font-semibold text-blue-600 hover:text-blue-700">
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </a>
                            <?php else: ?>
                            <p class="font-semibold text-gray-400">Not provided</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Total Invoices</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo $stats['total_invoices']; ?></p>
                    <div class="flex items-center justify-center space-x-2 text-xs">
                        <span class="text-gray-500"><?php echo $stats['paid_invoices']; ?> paid</span>
                        <span class="text-gray-400">•</span>
                        <span class="text-gray-500"><?php echo $stats['unpaid_invoices']; ?> unpaid</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Total Revenue</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($stats['total_paid']); ?></p>
                    <p class="text-sm text-gray-500">from <?php echo formatCurrency($stats['total_invoiced']); ?> invoiced</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Outstanding</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($stats['total_outstanding']); ?></p>
                    <p class="text-sm text-gray-500">amount due</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-2">Average Invoice</p>
                    <p class="text-4xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($stats['avg_invoice_amount']); ?></p>
                    <?php if ($stats['first_invoice_date']): ?>
                    <p class="text-sm text-gray-500">Customer since <?php echo date('M Y', strtotime($stats['first_invoice_date'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2 space-y-8">
                <!-- Revenue Chart -->
                <?php if ($stats['total_invoices'] > 0): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-chart-line mr-3 text-gray-600"></i>
                            Monthly Revenue (Last 12 Months)
                        </h3>
                    </div>
                    <div class="p-6">
                        <canvas id="revenueChart" height="300"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Invoices List -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-file-invoice mr-3 text-gray-600"></i>
                            All Invoices
                        </h3>
                        <p class="text-sm text-gray-600 mt-1"><?php echo count($invoices); ?> total invoice<?php echo count($invoices) != 1 ? 's' : ''; ?></p>
                    </div>
                    <div class="overflow-x-auto">
                        <?php if (empty($invoices)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">No Invoices Yet</h4>
                            <p class="text-gray-600 mb-6">This customer doesn't have any invoices.</p>
                            <a href="create-invoice.php?customer_id=<?php echo $customer['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                                <i class="fas fa-plus mr-2"></i>Create First Invoice
                            </a>
                        </div>
                        <?php else: ?>
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Invoice #</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Property</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Date</th>
                                    <th class="px-6 py-4 text-left text-sm font-medium text-gray-700">Due Date</th>
                                    <th class="px-6 py-4 text-right text-sm font-medium text-gray-700">Amount</th>
                                    <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Status</th>
                                    <th class="px-6 py-4 text-right text-sm font-medium text-gray-700">Balance</th>
                                    <th class="px-6 py-4 text-center text-sm font-medium text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($invoices as $invoice): ?>
                                <tr class="hover:bg-gray-50 transition-colors <?php echo $invoice['actual_status'] === 'Unpaid' && strtotime($invoice['due_date']) < time() ? 'bg-red-50' : ''; ?>">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($invoice['property_name']): ?>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($invoice['property_name']); ?></div>
                                        <?php if ($invoice['property_type'] && $invoice['property_type'] !== 'Other'): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
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
                                            <a href="../public/view-invoice.php?id=<?php echo $invoice['unique_id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View Invoice">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($invoice['balance_due'] > 0): ?>
                                            <a href="payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Add Payment">
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
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <!-- Recent Payments -->
                <?php if (!empty($recentPayments)): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-credit-card mr-3 text-gray-600"></i>
                            Recent Payments
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4 max-h-64 overflow-y-auto">
                            <?php foreach ($recentPayments as $payment): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['invoice_number']); ?></p>
                                    <div class="flex items-center text-xs text-gray-500 space-x-2">
                                        <span><?php echo htmlspecialchars($payment['method']); ?></span>
                                        <span>•</span>
                                        <span><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                    </div>
                                </div>
                                <span class="font-semibold text-gray-900"><?php echo formatCurrency($payment['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2 text-gray-600"></i>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="create-invoice.php?customer_id=<?php echo $customer['id']; ?>" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-plus-circle text-gray-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-gray-700">Create New Invoice</span>
                        </a>
                        <a href="customer-edit.php?id=<?php echo $customer['id']; ?>" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-edit text-gray-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-gray-700">Edit Customer Info</span>
                        </a>
                        <a href="customer-properties.php?customer_id=<?php echo $customer['id']; ?>" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-building text-gray-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-gray-700">Manage Properties</span>
                        </a>
                        <?php if ($customer['email']): ?>
                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors group">
                            <i class="fas fa-envelope text-gray-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-gray-700">Send Email</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($customer['phone']): ?>
                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors group">
                            <i class="fas fa-phone text-blue-600 mr-3"></i>
                            <span class="font-medium text-gray-900 group-hover:text-blue-700">Call Customer</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer Summary -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-purple-600"></i>
                        Summary
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Customer since:</span>
                            <span class="font-medium text-gray-900">
                                <?php echo $stats['first_invoice_date'] ? date('M d, Y', strtotime($stats['first_invoice_date'])) : 'No invoices yet'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Last invoice:</span>
                            <span class="font-medium text-gray-900">
                                <?php echo $stats['last_invoice_date'] ? date('M d, Y', strtotime($stats['last_invoice_date'])) : 'Never'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment rate:</span>
                            <span class="font-medium text-green-600">
                                <?php 
                                $paymentRate = $stats['total_invoiced'] > 0 ? ($stats['total_paid'] / $stats['total_invoiced']) * 100 : 0;
                                echo number_format($paymentRate, 1) . '%';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($stats['total_invoices'] > 0): ?>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode(array_values($monthlyData)); ?>;
        const monthlyLabels = <?php echo json_encode(array_map(function($month) { return date('M Y', strtotime($month . '-01')); }, array_keys($monthlyData))); ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Revenue',
                    data: monthlyData,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
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
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>
</body>
</html>