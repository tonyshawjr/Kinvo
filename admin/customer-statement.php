<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$customerId = $_GET['customer_id'] ?? null;
$action = $_GET['action'] ?? 'view';
$format = $_GET['format'] ?? 'html';

if (!$customerId) {
    header('Location: customers.php');
    exit;
}

// Get customer information
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Get business settings for statement header
$businessSettings = getBusinessSettings($pdo);

// Date range handling
$dateRange = $_GET['date_range'] ?? '90_days';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';

// Calculate date range
switch ($dateRange) {
    case '30_days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        $rangeLabel = 'Last 30 Days';
        break;
    case '90_days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $endDate = date('Y-m-d');
        $rangeLabel = 'Last 90 Days';
        break;
    case '6_months':
        $startDate = date('Y-m-d', strtotime('-6 months'));
        $endDate = date('Y-m-d');
        $rangeLabel = 'Last 6 Months';
        break;
    case '1_year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d');
        $rangeLabel = 'Last Year';
        break;
    case 'custom':
        $startDate = $customStartDate ?: date('Y-m-d', strtotime('-90 days'));
        $endDate = $customEndDate ?: date('Y-m-d');
        $rangeLabel = 'Custom Range';
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $endDate = date('Y-m-d');
        $rangeLabel = 'Last 90 Days';
}

// Statement options
$includeOutstandingOnly = isset($_GET['outstanding_only']) && $_GET['outstanding_only'] === '1';
$includePayments = !isset($_GET['include_payments']) || $_GET['include_payments'] === '1';

// Get invoices in date range
$invoiceQuery = "
    SELECT 
        i.*,
        COALESCE(p.total_paid, 0) as total_paid
    FROM invoices i
    LEFT JOIN (
        SELECT invoice_id, SUM(amount) as total_paid 
        FROM payments 
        GROUP BY invoice_id
    ) p ON i.id = p.invoice_id
    WHERE i.customer_id = ? 
    AND i.date >= ? 
    AND i.date <= ?
";

if ($includeOutstandingOnly) {
    $invoiceQuery .= " AND (i.total - COALESCE(p.total_paid, 0)) > 0";
}

$invoiceQuery .= " ORDER BY i.date DESC";

$stmt = $pdo->prepare($invoiceQuery);
$stmt->execute([$customerId, $startDate, $endDate]);
$invoices = $stmt->fetchAll();

// Calculate invoice statuses and balances
foreach ($invoices as $key => $invoice) {
    $invoice['balance_due'] = $invoice['total'] - $invoice['total_paid'];
    $invoice['status'] = getInvoiceStatus($invoice, $pdo);
    $invoices[$key] = $invoice;
}

// Get payments in date range if included
$payments = [];
if ($includePayments) {
    $stmt = $pdo->prepare("
        SELECT p.*, i.invoice_number 
        FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id 
        WHERE i.customer_id = ? 
        AND p.payment_date >= ? 
        AND p.payment_date <= ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$customerId, $startDate, $endDate]);
    $payments = $stmt->fetchAll();
}

// Calculate summary totals
$totalInvoiced = array_sum(array_column($invoices, 'total'));
$totalPaid = array_sum(array_column($payments, 'amount'));
$currentBalance = $totalInvoiced - $totalPaid;

// Get overall customer balance (all time)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(i.total), 0) as all_time_invoiced,
        COALESCE(SUM(p.amount), 0) as all_time_paid
    FROM invoices i
    LEFT JOIN payments p ON i.id = p.invoice_id
    WHERE i.customer_id = ?
");
$stmt->execute([$customerId]);
$overallTotals = $stmt->fetch();
$overallBalance = $overallTotals['all_time_invoiced'] - $overallTotals['all_time_paid'];

// Handle email sending
if ($action === 'email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailAddress = $_POST['email_address'] ?? $customer['email'];
    $emailSubject = $_POST['email_subject'] ?? "Account Statement - " . $businessSettings['business_name'];
    $emailMessage = $_POST['email_message'] ?? "Please find your account statement attached.";
    
    if ($emailAddress) {
        // Generate PDF content (we'll implement this next)
        $pdfContent = generateStatementPDF($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance);
        
        // Send email with PDF attachment
        $success = sendStatementEmail($emailAddress, $emailSubject, $emailMessage, $pdfContent, $customer['name'], $businessSettings);
        
        if ($success) {
            $emailSent = true;
        } else {
            $emailError = "Failed to send email. Please check your email settings.";
        }
    } else {
        $emailError = "Email address is required.";
    }
}

// If PDF format requested, generate and download PDF
if ($format === 'pdf') {
    generateAndDownloadPDF($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Statement - <?php echo htmlspecialchars($customer['name']); ?><?php 
    $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
        ? ' - ' . $businessSettings['business_name'] 
        : '';
    echo htmlspecialchars($appName);
    ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-break { page-break-after: always; }
            body { background: white !important; }
            .bg-gray-50 { background: white !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Professional Header -->
        <div class="no-print mb-10">
            <!-- Navigation & Title -->
            <div class="mb-6">
                <div class="flex items-center space-x-4 mb-4">
                    <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="inline-flex items-center text-gray-500 hover:text-gray-700 transition-colors group">
                        <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                        <span class="text-sm font-medium">Back to Customer</span>
                    </a>
                </div>
                <div class="border-b border-gray-200 pb-6">
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Account Statement</h1>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xl text-gray-600"><?php echo htmlspecialchars($customer['name']); ?></p>
                            <p class="text-sm text-gray-500 mt-1">Statement Period: <?php echo $rangeLabel; ?></p>
                        </div>
                        <div class="mt-4 sm:mt-0">
                            <p class="text-sm text-gray-500">Generated on</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo date('M j, Y \a\t g:i A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Bar -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Statement Actions</h3>
                        <p class="text-sm text-gray-500">Generate, print, or share this statement</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="window.print()" class="inline-flex items-center justify-center px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                            <i class="fas fa-print mr-2"></i>Print Statement
                        </button>
                        <a href="?customer_id=<?php echo $customer['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['format' => 'pdf'])); ?>" class="inline-flex items-center justify-center px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-semibold">
                            <i class="fas fa-file-pdf mr-2"></i>Download PDF
                        </a>
                        <button onclick="toggleEmailModal()" class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold shadow-sm">
                            <i class="fas fa-envelope mr-2"></i>Email Statement
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statement Options -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Statement Options</h3>
                    <p class="text-sm text-gray-500">Customize the date range and content for this statement</p>
                </div>
                
                <form method="GET" class="space-y-8">
                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                    
                    <!-- Date Range Section -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Date Range</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                                <select name="date_range" onchange="toggleCustomDates()" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base">
                                    <option value="30_days" <?php echo $dateRange === '30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="90_days" <?php echo $dateRange === '90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                    <option value="6_months" <?php echo $dateRange === '6_months' ? 'selected' : ''; ?>>Last 6 Months</option>
                                    <option value="1_year" <?php echo $dateRange === '1_year' ? 'selected' : ''; ?>>Last Year</option>
                                    <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
                                </select>
                            </div>
                            
                            <div id="custom-dates" class="space-y-4" style="display: <?php echo $dateRange === 'custom' ? 'block' : 'none'; ?>;">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                                        <input type="date" name="start_date" value="<?php echo $customStartDate; ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                                        <input type="date" name="end_date" value="<?php echo $customEndDate; ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-all text-base">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Filters Section -->
                    <div class="space-y-4">
                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Content Filters</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <label class="flex items-start space-x-3 cursor-pointer">
                                        <input type="checkbox" name="outstanding_only" value="1" <?php echo $includeOutstandingOnly ? 'checked' : ''; ?> class="w-5 h-5 text-gray-600 border-gray-300 rounded focus:ring-gray-500 mt-0.5">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">Outstanding Invoices Only</span>
                                            <p class="text-xs text-gray-500 mt-1">Show only invoices with remaining balances</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <label class="flex items-start space-x-3 cursor-pointer">
                                        <input type="checkbox" name="include_payments" value="1" <?php echo $includePayments ? 'checked' : ''; ?> class="w-5 h-5 text-gray-600 border-gray-300 rounded focus:ring-gray-500 mt-0.5">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">Include Payments</span>
                                            <p class="text-xs text-gray-500 mt-1">Show payment history in the statement</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Update Button -->
                    <div class="flex justify-end pt-4 border-t border-gray-200">
                        <button type="submit" class="inline-flex items-center px-8 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold shadow-sm">
                            <i class="fas fa-refresh mr-2"></i>Update Statement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Professional Statement Document -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
            <!-- Statement Document Header -->
            <div class="p-8 border-b border-gray-200">
                <div class="flex justify-between items-start mb-8">
                    <!-- Business Information -->
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-3"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h2>
                        <div class="text-gray-600 space-y-1">
                            <?php if ($businessSettings['business_address']): ?>
                            <div class="whitespace-pre-line"><?php echo htmlspecialchars($businessSettings['business_address']); ?></div>
                            <?php endif; ?>
                            <div class="flex flex-col sm:flex-row sm:space-x-4 pt-2">
                                <?php if ($businessSettings['business_phone']): ?>
                                <div>Phone: <?php echo htmlspecialchars($businessSettings['business_phone']); ?></div>
                                <?php endif; ?>
                                <?php if ($businessSettings['business_email']): ?>
                                <div>Email: <?php echo htmlspecialchars($businessSettings['business_email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statement Title & Meta -->
                    <div class="text-right">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">ACCOUNT STATEMENT</h1>
                        <div class="text-gray-600 space-y-1">
                            <div><span class="font-medium">Statement Date:</span> <?php echo date('M j, Y'); ?></div>
                            <div><span class="font-medium">Statement Period:</span> <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Statement For</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-gray-900 font-semibold text-lg mb-2"><?php echo htmlspecialchars($customer['name']); ?></div>
                            <div class="text-gray-600 space-y-1">
                                <?php if ($customer['email']): ?>
                                <div><?php echo htmlspecialchars($customer['email']); ?></div>
                                <?php endif; ?>
                                <?php if ($customer['phone']): ?>
                                <div><?php echo htmlspecialchars($customer['phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Account Summary</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Statement Period Balance:</span>
                                    <span class="font-semibold text-gray-900">$<?php echo number_format($currentBalance, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Current Account Balance:</span>
                                    <span class="font-bold text-xl <?php echo $overallBalance > 0 ? 'text-red-600' : 'text-green-600'; ?>">$<?php echo number_format($overallBalance, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="p-8 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900 mb-6">Financial Summary - <?php echo $rangeLabel; ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900">$<?php echo number_format($totalInvoiced, 2); ?></div>
                                <div class="text-sm text-gray-500">Total Invoiced</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">New charges for this period</div>
                    </div>
                    
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-credit-card text-green-600 text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900">$<?php echo number_format($totalPaid, 2); ?></div>
                                <div class="text-sm text-gray-500">Payments Received</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">Payments applied this period</div>
                    </div>
                    
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-balance-scale text-gray-600 text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold <?php echo $currentBalance > 0 ? 'text-red-600' : 'text-green-600'; ?>">$<?php echo number_format($currentBalance, 2); ?></div>
                                <div class="text-sm text-gray-500">Net Change</div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500">Period activity balance</div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details -->
            <?php if (!empty($invoices)): ?>
            <div class="p-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">
                        Invoice Details
                        <?php if ($includeOutstandingOnly): ?>
                        <span class="text-sm font-normal text-gray-500 ml-2">- Outstanding Balances Only</span>
                        <?php endif; ?>
                    </h3>
                    <div class="text-sm text-gray-500">
                        <?php echo count($invoices); ?> invoice<?php echo count($invoices) != 1 ? 's' : ''; ?> in this period
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Invoice Number</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Payments</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Balance Due</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php foreach ($invoices as $index => $invoice): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-blue-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="../public/view-invoice.php?id=<?php echo htmlspecialchars($invoice['unique_id']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600"><?php echo date('M j, Y', strtotime($invoice['date'])); ?></td>
                                    <td class="px-6 py-4 text-right font-mono text-gray-900">$<?php echo number_format($invoice['total'], 2); ?></td>
                                    <td class="px-6 py-4 text-right font-mono text-gray-900">$<?php echo number_format($invoice['total_paid'], 2); ?></td>
                                    <td class="px-6 py-4 text-right font-mono font-semibold <?php echo $invoice['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        $<?php echo number_format($invoice['balance_due'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-700';
                                        $statusText = $invoice['status'];
                                        if ($invoice['status'] === 'Paid') {
                                            $statusClass = 'bg-green-100 text-green-800';
                                        } elseif ($invoice['status'] === 'Unpaid') {
                                            $statusClass = 'bg-red-100 text-red-800';
                                        } elseif ($invoice['status'] === 'Partial') {
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                        }
                                        ?>
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($statusText); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 font-semibold text-gray-900">TOTALS</td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-gray-900">$<?php echo number_format($totalInvoiced, 2); ?></td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-gray-900">$<?php echo number_format($totalPaid, 2); ?></td>
                                    <td class="px-6 py-4 text-right font-mono font-bold <?php echo $currentBalance > 0 ? 'text-red-600' : 'text-green-600'; ?>">$<?php echo number_format($currentBalance, 2); ?></td>
                                    <td class="px-6 py-4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="p-8 text-center">
                <div class="max-w-sm mx-auto">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-gray-400 text-2xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">No Invoices Found</h4>
                    <p class="text-gray-500 mb-6">No invoices were found for the selected date range and filters.</p>
                    <a href="?customer_id=<?php echo $customer['id']; ?>&date_range=1_year" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-semibold">
                        <i class="fas fa-search mr-2"></i>Try Broader Date Range
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History -->
            <?php if ($includePayments && !empty($payments)): ?>
            <div class="p-8 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Payment History</h3>
                    <div class="text-sm text-gray-500">
                        <?php echo count($payments); ?> payment<?php echo count($payments) != 1 ? 's' : ''; ?> received in this period
                    </div>
                </div>
                
                <div class="bg-white rounded-lg overflow-hidden border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-green-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Payment Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Invoice Number</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-900 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php foreach ($payments as $index => $payment): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-green-25'; ?> hover:bg-green-50 transition-colors">
                                    <td class="px-6 py-4 text-gray-600"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="px-6 py-4 text-gray-900 font-medium"><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-green-600">$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?php echo htmlspecialchars($payment['payment_method'] ?? 'Not specified'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-sm"><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-green-50">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 font-semibold text-gray-900">TOTAL PAYMENTS</td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-green-600">$<?php echo number_format(array_sum(array_column($payments, 'amount')), 2); ?></td>
                                    <td colspan="2" class="px-6 py-4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statement Footer -->
            <div class="p-8 border-t border-gray-200 bg-gray-50">
                <div class="text-center">
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Questions about this statement?</h4>
                        <div class="text-sm text-gray-600 space-y-1">
                            <div>Contact <?php echo htmlspecialchars($businessSettings['business_name']); ?></div>
                            <?php if ($businessSettings['business_phone']): ?>
                            <div>Phone: <?php echo htmlspecialchars($businessSettings['business_phone']); ?></div>
                            <?php endif; ?>
                            <?php if ($businessSettings['business_email']): ?>
                            <div>Email: <?php echo htmlspecialchars($businessSettings['business_email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 border-t border-gray-200 pt-4">
                        This statement was generated on <?php echo date('F j, Y \a\t g:i A T'); ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Email Modal -->
    <div id="emailModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="?customer_id=<?php echo $customer['id']; ?>&action=email&<?php echo http_build_query($_GET); ?>">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-envelope text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Email Statement</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                        <input type="email" name="email_address" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" required 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                        <input type="text" name="email_subject" value="Account Statement - <?php echo htmlspecialchars($businessSettings['business_name']); ?>" required 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                        <textarea name="email_message" rows="3" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                                  placeholder="Please find your account statement attached.">Please find your account statement for the period <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?> attached.</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Send Email
                        </button>
                        <button type="button" onclick="toggleEmailModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomDates() {
            const dateRange = document.querySelector('select[name="date_range"]').value;
            const customDates = document.getElementById('custom-dates');
            customDates.style.display = dateRange === 'custom' ? 'block' : 'none';
        }

        function toggleEmailModal() {
            const modal = document.getElementById('emailModal');
            modal.classList.toggle('hidden');
        }

        // Show email result if exists
        <?php if (isset($emailSent)): ?>
        alert('Statement sent successfully!');
        <?php elseif (isset($emailError)): ?>
        alert('<?php echo addslashes($emailError); ?>');
        <?php endif; ?>
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>

<?php
// Helper functions for PDF generation and email sending
function generateStatementPDF($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance) {
    // This function will be implemented when we add PDF functionality
    return '';
}

function generateAndDownloadPDF($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance) {
    // Generate clean PDF-style HTML
    $html = generatePDFHTML($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance);
    
    // Set headers for PDF download
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="statement-' . preg_replace('/[^a-zA-Z0-9]/', '', $customer['name']) . '-' . date('Y-m-d') . '.html"');
    
    echo $html;
}

function generatePDFHTML($customer, $invoices, $payments, $businessSettings, $startDate, $endDate, $rangeLabel, $totalInvoiced, $totalPaid, $currentBalance, $overallBalance) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Statement - <?php echo htmlspecialchars($customer['name']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                font-size: 12px; 
                line-height: 1.4; 
                color: #333; 
                background: white;
                padding: 20px;
            }
            .container { max-width: 800px; margin: 0 auto; }
            .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .company-info { float: left; width: 50%; }
            .statement-info { float: right; width: 45%; text-align: right; }
            .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
            .statement-title { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
            .clearfix::after { content: ""; display: table; clear: both; }
            
            .customer-section { background: #f8f9fa; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff; }
            .summary-section { background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; }
            .summary-grid { display: flex; justify-content: space-between; margin-bottom: 15px; }
            .summary-item { text-align: center; flex: 1; }
            .summary-label { font-size: 11px; color: #666; text-transform: uppercase; }
            .summary-value { font-size: 18px; font-weight: bold; margin-top: 5px; }
            
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f1f3f4; font-weight: 600; font-size: 11px; text-transform: uppercase; }
            .amount { text-align: right; }
            .status-paid { color: #28a745; font-weight: bold; }
            .status-unpaid { color: #dc3545; font-weight: bold; }
            .status-partial { color: #ffc107; font-weight: bold; }
            .balance-positive { color: #dc3545; font-weight: bold; }
            .balance-negative { color: #28a745; font-weight: bold; }
            
            .section-title { font-size: 16px; font-weight: bold; margin: 25px 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; font-size: 10px; color: #666; }
            
            @media print {
                body { padding: 0; }
                .container { max-width: none; margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div class="header clearfix">
                <div class="company-info">
                    <div class="company-name"><?php echo htmlspecialchars($businessSettings['business_name']); ?></div>
                    <?php if ($businessSettings['business_address']): ?>
                    <div><?php echo nl2br(htmlspecialchars($businessSettings['business_address'])); ?></div>
                    <?php endif; ?>
                    <?php if ($businessSettings['business_phone']): ?>
                    <div>Phone: <?php echo htmlspecialchars($businessSettings['business_phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($businessSettings['business_email']): ?>
                    <div>Email: <?php echo htmlspecialchars($businessSettings['business_email']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="statement-info">
                    <div class="statement-title">ACCOUNT STATEMENT</div>
                    <div><strong>Statement Date:</strong> <?php echo date('M j, Y'); ?></div>
                    <div><strong>Period:</strong> <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></div>
                    <div><strong>Account Balance:</strong> <span class="<?php echo $overallBalance > 0 ? 'balance-positive' : 'balance-negative'; ?>">$<?php echo number_format($overallBalance, 2); ?></span></div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="customer-section">
                <strong>Statement For:</strong><br>
                <?php echo htmlspecialchars($customer['name']); ?><br>
                <?php if ($customer['email']): ?>
                <?php echo htmlspecialchars($customer['email']); ?><br>
                <?php endif; ?>
                <?php if ($customer['phone']): ?>
                <?php echo htmlspecialchars($customer['phone']); ?><br>
                <?php endif; ?>
            </div>
            
            <!-- Summary -->
            <div class="summary-section">
                <h3>Summary for <?php echo $rangeLabel; ?></h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Total Invoiced</div>
                        <div class="summary-value">$<?php echo number_format($totalInvoiced, 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Payments</div>
                        <div class="summary-value">$<?php echo number_format($totalPaid, 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Period Balance</div>
                        <div class="summary-value <?php echo $currentBalance > 0 ? 'balance-positive' : 'balance-negative'; ?>">$<?php echo number_format($currentBalance, 2); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Invoices -->
            <?php if (!empty($invoices)): ?>
            <div class="section-title">Invoices (<?php echo count($invoices); ?>)</div>
            <table>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th class="amount">Amount</th>
                        <th class="amount">Paid</th>
                        <th class="amount">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($invoice['date'])); ?></td>
                        <td class="amount">$<?php echo number_format($invoice['total'], 2); ?></td>
                        <td class="amount">$<?php echo number_format($invoice['total_paid'], 2); ?></td>
                        <td class="amount <?php echo $invoice['balance_due'] > 0 ? 'balance-positive' : 'balance-negative'; ?>">
                            $<?php echo number_format($invoice['balance_due'], 2); ?>
                        </td>
                        <td>
                            <span class="status-<?php echo strtolower($invoice['status']); ?>">
                                <?php echo htmlspecialchars($invoice['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <!-- Payments -->
            <?php if (!empty($payments)): ?>
            <div class="section-title">Payments Received (<?php echo count($payments); ?>)</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th class="amount">Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                        <td class="amount">$<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'Not specified'); ?></td>
                        <td><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="footer">
                <p>This statement was generated on <?php echo date('F j, Y g:i A'); ?></p>
                <p>For questions about this statement, please contact <?php echo htmlspecialchars($businessSettings['business_name']); ?></p>
                <?php if ($businessSettings['business_phone']): ?>
                <p>Phone: <?php echo htmlspecialchars($businessSettings['business_phone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            // Auto-print when opened as PDF
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function sendStatementEmail($emailAddress, $subject, $message, $pdfContent, $customerName, $businessSettings) {
    $fromEmail = $businessSettings['business_email'] ?: 'noreply@localhost';
    $fromName = $businessSettings['business_name'] ?: 'Invoice System';
    
    // Email headers
    $headers = [];
    $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
    $headers[] = "Reply-To: " . $fromEmail;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    // Email body with professional styling
    $emailBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #f8f9fa; padding: 20px; border-bottom: 1px solid #ddd; text-align: center; }
            .content { padding: 20px; }
            .footer { background: #f8f9fa; padding: 15px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
            .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; margin: 0; }
            .message { margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 class="company-name">' . htmlspecialchars($fromName) . '</h1>
                <p style="margin: 5px 0 0 0; color: #666;">Account Statement</p>
            </div>
            <div class="content">
                <h2>Hello ' . htmlspecialchars($customerName) . ',</h2>
                <div class="message">
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <p>Your account statement has been generated and is available for review. Please contact us if you have any questions about your account or need any clarification.</p>
                <p>Thank you for your business!</p>
            </div>
            <div class="footer">
                <p>This email was sent from ' . htmlspecialchars($fromName) . '</p>
                ' . ($businessSettings['business_phone'] ? '<p>Phone: ' . htmlspecialchars($businessSettings['business_phone']) . '</p>' : '') . '
                ' . ($businessSettings['business_email'] ? '<p>Email: ' . htmlspecialchars($businessSettings['business_email']) . '</p>' : '') . '
            </div>
        </div>
    </body>
    </html>';
    
    // Send email
    try {
        $result = mail($emailAddress, $subject, $emailBody, implode("\r\n", $headers));
        return $result;
    } catch (Exception $e) {
        error_log("Failed to send statement email: " . $e->getMessage());
        return false;
    }
}
?>