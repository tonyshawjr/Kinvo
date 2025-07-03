<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = '';
$invoice = null;

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
               p.property_name, p.address as property_address, p.property_type
        FROM invoices i 
        JOIN customers c ON i.customer_id = c.id 
        LEFT JOIN customer_properties p ON i.property_id = p.id
        WHERE i.unique_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $invoice = $stmt->fetch();
    
    if ($invoice) {
        // Get line items
        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoice['id']]);
        $lineItems = $stmt->fetchAll();
        
        // Get payments
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
        $stmt->execute([$invoice['id']]);
        $payments = $stmt->fetchAll();
        
        // Calculate total paid
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $balance = $invoice['total'] - $totalPaid;
        
        // Get business settings
        $businessSettings = getBusinessSettings($pdo);
    } else {
        $error = 'Invoice not found';
    }
} else {
    $error = 'Invalid invoice link';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $invoice ? 'Invoice ' . htmlspecialchars($invoice['invoice_number']) : 'Invoice Not Found'; ?><?php 
    if ($invoice) {
        $businessSettings = getBusinessSettings($pdo);
        $appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
            ? ' - ' . $businessSettings['business_name'] 
            : '';
        echo htmlspecialchars($appName);
    }
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
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .shadow, .shadow-sm, .shadow-lg {
                box-shadow: none !important;
            }
            .border {
                border-color: #e5e7eb !important;
            }
            .bg-gradient-to-r, .bg-gradient-to-br {
                background: white !important;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <?php if ($error): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Invoice Not Found</h2>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
            <p class="text-sm text-gray-500">Please check the invoice link and try again.</p>
        </div>
    </div>
    <?php elseif ($invoice): ?>
    
    <!-- Invoice Container -->
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Print-friendly Invoice -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-8 no-print">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <h1 class="text-3xl font-bold mb-2">Invoice Received</h1>
                        <p class="text-blue-100">Thank you for your business!</p>
                    </div>
                    <div class="text-right">
                        <?php 
                        $status = getInvoiceStatus($invoice, $pdo);
                        $statusColors = [
                            'Paid' => 'bg-green-500',
                            'Partial' => 'bg-yellow-500', 
                            'Unpaid' => 'bg-red-500'
                        ];
                        ?>
                        <span class="inline-flex px-4 py-2 text-sm font-semibold rounded-full text-white <?php echo $statusColors[$status] ?? 'bg-gray-500'; ?>">
                            <i class="fas fa-circle mr-2 text-xs"></i>
                            <?php echo $status; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Invoice Content -->
            <div class="p-8">
                <!-- Business & Invoice Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Business Info -->
                    <div>
                        <div class="mb-4">
                            <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h2>
                            <?php if (!empty($businessSettings['business_ein'])): ?>
                            <p class="text-gray-600">EIN: <?php echo htmlspecialchars($businessSettings['business_ein']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-2 text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-phone w-5 mr-3 text-blue-600"></i>
                                <span><?php echo htmlspecialchars($businessSettings['business_phone']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-envelope w-5 mr-3 text-blue-600"></i>
                                <span><?php echo htmlspecialchars($businessSettings['business_email']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Details -->
                    <div class="text-right">
                        <h3 class="text-3xl font-bold text-gray-900 mb-2">INVOICE</h3>
                        <div class="text-2xl font-bold text-blue-600 mb-4">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        <div class="space-y-2">
                            <div class="flex justify-end items-center">
                                <span class="text-gray-600 w-24">Issue Date:</span>
                                <span class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($invoice['date'])); ?></span>
                            </div>
                            <div class="flex justify-end items-center">
                                <span class="text-gray-600 w-24">Due Date:</span>
                                <span class="font-medium text-gray-900"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bill To Section -->
                <div class="mb-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Customer Info -->
                        <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-xl p-6 border border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-600"></i>
                                Customer
                            </h4>
                            <div class="space-y-3">
                                <p class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                <?php if ($invoice['customer_email']): ?>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-envelope w-4 mr-2 text-blue-600 flex-shrink-0"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($invoice['customer_email']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($invoice['customer_phone']): ?>
                                <div class="flex items-center text-gray-600">
                                    <i class="fas fa-phone w-4 mr-2 text-blue-600 flex-shrink-0"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($invoice['customer_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Service Location -->
                        <?php if ($invoice['property_name']): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 border border-green-200">
                            <h4 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>
                                Service Location
                            </h4>
                            <div class="space-y-2">
                                <div class="flex items-center space-x-2">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($invoice['property_name']); ?></p>
                                    <?php if ($invoice['property_type'] && $invoice['property_type'] !== 'Other'): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <?php echo htmlspecialchars($invoice['property_type']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($invoice['property_address']): ?>
                                <p class="text-sm text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($invoice['property_address'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-list mr-2 text-blue-600"></i>
                        Services & Items
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gradient-to-r from-gray-50 to-blue-50 border-y border-gray-200">
                                    <th class="text-left py-4 px-6 font-semibold text-gray-900">Description</th>
                                    <th class="text-center py-4 px-6 font-semibold text-gray-900">Qty</th>
                                    <th class="text-right py-4 px-6 font-semibold text-gray-900">Rate</th>
                                    <th class="text-right py-4 px-6 font-semibold text-gray-900">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lineItems as $item): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-4 px-6">
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($item['description']); ?></p>
                                    </td>
                                    <td class="py-4 px-6 text-center text-gray-700">
                                        <?php echo number_format($item['quantity'], $item['quantity'] == floor($item['quantity']) ? 0 : 2); ?>
                                    </td>
                                    <td class="py-4 px-6 text-right text-gray-700">
                                        <?php echo formatCurrency($item['unit_price']); ?>
                                    </td>
                                    <td class="py-4 px-6 text-right font-semibold text-gray-900">
                                        <?php echo formatCurrency($item['total']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totals Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Quick Payment Links -->
                    <?php if (!empty($businessSettings['cashapp_username']) || !empty($businessSettings['venmo_username'])): ?>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-mobile-alt mr-2 text-green-600"></i>
                            Pay Now - Quick Links
                        </h4>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-6 border border-green-200">
                            <div class="space-y-4">
                                <?php if (!empty($businessSettings['cashapp_username'])): ?>
                                <a href="https://cash.app/$<?php echo htmlspecialchars($businessSettings['cashapp_username']); ?>/<?php echo number_format($balance, 2, '.', ''); ?>" 
                                   target="_blank" 
                                   class="flex items-center justify-between p-4 bg-white rounded-xl border border-green-200 hover:border-green-300 hover:shadow-md transition-all group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-dollar-sign text-white"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Cash App</p>
                                            <p class="text-sm text-gray-600">$<?php echo htmlspecialchars($businessSettings['cashapp_username']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 text-green-600 group-hover:text-green-700">
                                        <span class="text-sm font-medium">Pay Now</span>
                                        <i class="fas fa-external-link-alt"></i>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($businessSettings['venmo_username'])): ?>
                                <a href="https://account.venmo.com/payment-link?amount=<?php echo number_format($balance, 2, '.', ''); ?>&recipients=<?php echo htmlspecialchars($businessSettings['venmo_username']); ?>&txn=pay" 
                                   target="_blank" 
                                   class="flex items-center justify-between p-4 bg-white rounded-xl border border-green-200 hover:border-green-300 hover:shadow-md transition-all group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                            <i class="fab fa-venmo text-white"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Venmo</p>
                                            <p class="text-sm text-gray-600">@<?php echo htmlspecialchars($businessSettings['venmo_username']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 text-blue-600 group-hover:text-blue-700">
                                        <span class="text-sm font-medium">Pay Now</span>
                                        <i class="fas fa-external-link-alt"></i>
                                    </div>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Instructions -->
                    <?php if ($invoice['notes']): ?>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-credit-card mr-2 text-blue-600"></i>
                            Payment Instructions
                        </h4>
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-200">
                            <p class="text-gray-700 whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars($invoice['notes']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Invoice Totals -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-calculator mr-2 text-blue-600"></i>
                            Invoice Summary
                        </h4>
                        <div class="bg-gradient-to-r from-gray-50 to-blue-50 rounded-2xl p-6 border border-gray-200">
                            <div class="space-y-3">
                                <div class="flex justify-between text-lg">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-semibold text-gray-900"><?php echo formatCurrency($invoice['subtotal']); ?></span>
                                </div>
                                <?php if ($invoice['tax_amount'] > 0): ?>
                                <div class="flex justify-between text-lg">
                                    <span class="text-gray-600">Tax (<?php echo number_format($invoice['tax_rate'], 2); ?>%):</span>
                                    <span class="font-semibold text-gray-900"><?php echo formatCurrency($invoice['tax_amount']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="border-t border-gray-300 pt-3">
                                    <div class="flex justify-between text-2xl">
                                        <span class="font-bold text-gray-900">Total:</span>
                                        <span class="font-bold text-blue-600"><?php echo formatCurrency($invoice['total']); ?></span>
                                    </div>
                                </div>
                                <?php if ($totalPaid > 0): ?>
                                <div class="border-t border-gray-300 pt-3 space-y-2">
                                    <div class="flex justify-between text-lg text-green-600">
                                        <span class="font-medium">Amount Paid:</span>
                                        <span class="font-semibold">-<?php echo formatCurrency($totalPaid); ?></span>
                                    </div>
                                    <div class="flex justify-between text-xl">
                                        <span class="font-bold text-gray-900">Balance Due:</span>
                                        <span class="font-bold <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo formatCurrency($balance); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <?php if (!empty($payments)): ?>
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-history mr-2 text-green-600"></i>
                        Payment History
                    </h4>
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-6 border border-green-200">
                        <div class="space-y-3">
                            <?php foreach ($payments as $payment): ?>
                            <div class="flex justify-between items-center p-3 bg-white rounded-lg border border-green-100">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-check text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['method']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                            <?php if ($payment['notes']): ?>
                                            • <?php echo htmlspecialchars($payment['notes']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="font-semibold text-green-600"><?php echo formatCurrency($payment['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-between items-center mt-8 space-y-4 sm:space-y-0 no-print">
            <div class="flex space-x-3">
                <button onclick="window.print()" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition-all font-medium shadow-lg">
                    <i class="fas fa-print mr-2"></i>Print Invoice
                </button>
                <?php if (isAdmin()): ?>
                <a href="/admin/edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all font-medium shadow-lg">
                    <i class="fas fa-edit mr-2"></i>Edit Invoice
                </a>
                <?php endif; ?>
            </div>
            <?php if (isAdmin()): ?>
            <div class="flex space-x-3">
                <?php if ($balance > 0): ?>
                <a href="/admin/payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all font-medium shadow-lg">
                    <i class="fas fa-plus-circle mr-2"></i>Add Payment
                </a>
                <?php endif; ?>
                <?php if (!empty($payments)): ?>
                <a href="/admin/manage-payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:from-purple-700 hover:to-purple-800 transition-all font-medium shadow-lg">
                    <i class="fas fa-cog mr-2"></i>Manage Payments
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm no-print">
            <p>Generated by <?php echo htmlspecialchars($businessSettings['business_name']); ?> • Professional Business Management</p>
        </div>
    </div>

    <?php endif; ?>
</body>
</html>