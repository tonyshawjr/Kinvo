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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                font-size: 11px !important;
                line-height: 1.2 !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .invoice-container {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 15px !important;
                max-width: none !important;
                width: 100% !important;
                background: white !important;
            }
            
            /* Header styling for print - more compact */
            .print-header {
                border-bottom: 2px solid #000 !important;
                padding-bottom: 10px !important;
                margin-bottom: 15px !important;
            }
            
            .print-header h1 {
                font-size: 16px !important;
                font-weight: bold !important;
                color: #000 !important;
                margin-bottom: 4px !important;
            }
            
            .print-header h2 {
                font-size: 20px !important;
                font-weight: bold !important;
                color: #000 !important;
                margin-bottom: 4px !important;
            }
            
            .print-header .text-2xl {
                font-size: 14px !important;
                font-weight: bold !important;
                color: #000 !important;
            }
            
            .print-header .text-gray-600 {
                font-size: 10px !important;
                line-height: 1.3 !important;
            }
            
            /* Bill To and Invoice Details sections - more compact */
            .print-section {
                margin-bottom: 12px !important;
            }
            
            .print-section h3 {
                font-size: 12px !important;
                font-weight: bold !important;
                color: #000 !important;
                margin-bottom: 5px !important;
                text-transform: uppercase !important;
            }
            
            .print-section .border {
                border: 1px solid #000 !important;
                padding: 8px !important;
                background: #f9f9f9 !important;
            }
            
            .print-section .font-semibold {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 11px !important;
            }
            
            .print-section table td {
                padding: 2px 0 !important;
                font-size: 10px !important;
            }
            
            /* Table styling for print - more compact */
            .print-table {
                border-collapse: collapse !important;
                width: 100% !important;
                margin: 12px 0 !important;
            }
            
            .print-table th {
                background-color: #f0f0f0 !important;
                border: 1px solid #000 !important;
                padding: 6px !important;
                font-weight: bold !important;
                color: #000 !important;
                text-align: left !important;
                font-size: 10px !important;
            }
            
            .print-table td {
                border: 1px solid #000 !important;
                padding: 6px !important;
                color: #000 !important;
                font-size: 10px !important;
            }
            
            .print-table .text-right {
                text-align: right !important;
            }
            
            .print-table .text-center {
                text-align: center !important;
            }
            
            /* Totals section - more compact */
            .print-totals {
                border: 2px solid #000 !important;
                background-color: #f9f9f9 !important;
                padding: 8px !important;
                margin-top: 12px !important;
            }
            
            .print-totals table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            .print-totals td {
                padding: 3px 0 !important;
                color: #000 !important;
                font-size: 10px !important;
            }
            
            .print-totals .font-bold {
                font-weight: bold !important;
            }
            
            .print-totals .text-lg {
                font-size: 12px !important;
            }
            
            .print-totals .border-t-2 {
                border-top: 2px solid #000 !important;
            }
            
            /* Status badge for print */
            .print-status {
                display: inline-block !important;
                padding: 2px 6px !important;
                border: 1px solid #000 !important;
                background: white !important;
                color: #000 !important;
                font-weight: bold !important;
                font-size: 10px !important;
            }
            
            /* Payment instructions - more compact */
            .print-instructions {
                margin-top: 12px !important;
                border: 1px solid #000 !important;
                padding: 8px !important;
                background: #f9f9f9 !important;
            }
            
            .print-instructions h3 {
                font-size: 12px !important;
                font-weight: bold !important;
                color: #000 !important;
                margin-bottom: 5px !important;
                text-transform: uppercase !important;
            }
            
            .print-instructions div {
                color: #000 !important;
                font-size: 10px !important;
                line-height: 1.3 !important;
            }
            
            /* Footer - more compact */
            .print-footer {
                margin-top: 15px !important;
                padding-top: 8px !important;
                border-top: 1px solid #000 !important;
                text-align: center !important;
            }
            
            .print-footer p {
                color: #000 !important;
                font-size: 10px !important;
                margin: 0 !important;
            }
            
            /* Property information styling - more compact */
            .print-property {
                margin-top: 6px !important;
                padding-top: 6px !important;
                border-top: 1px solid #ccc !important;
            }
            
            .print-property .font-medium {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 10px !important;
            }
            
            .print-property div {
                font-size: 9px !important;
                line-height: 1.2 !important;
            }
            
            /* Grid layout adjustments for print */
            .print-grid {
                display: table !important;
                width: 100% !important;
                table-layout: fixed !important;
            }
            
            .print-grid > div {
                display: table-cell !important;
                vertical-align: top !important;
                width: 50% !important;
                padding-right: 10px !important;
            }
            
            .print-grid > div:last-child {
                padding-right: 0 !important;
            }
        }
        
        @page {
            margin: 0.5in;
            size: letter;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php if ($error): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Invoice Not Found</h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php elseif ($invoice): ?>
    
    <!-- Invoice Container -->
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Action Buttons (No Print) -->
        <div class="flex justify-between items-center mb-6 no-print">
            <h1 class="text-2xl font-bold text-gray-900">Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
            <div class="flex space-x-3">
                <button onclick="window.print()" class="inline-flex items-center px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors font-semibold">
                    <i class="fas fa-print mr-2"></i>Print Invoice
                </button>
                <?php if (isAdmin()): ?>
                <a href="/admin/manage-payments.php?invoice_id=<?php echo $invoice['id']; ?>" class="inline-flex items-center px-6 py-3 bg-green-700 text-white rounded-lg hover:bg-green-600 transition-colors font-semibold">
                    <i class="fas fa-credit-card mr-2"></i>Manage Payments
                </a>
                <a href="/admin/edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                    <i class="fas fa-edit mr-2"></i>Edit Invoice
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Professional Invoice Layout -->
        <div class="bg-white invoice-container p-8">
            <!-- Invoice Header -->
            <div class="print-header pb-6 mb-8">
                <div class="flex justify-between items-start print-grid">
                    <!-- Business Information -->
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h1>
                        <div class="text-gray-600 space-y-1">
                            <?php if (!empty($businessSettings['business_phone'])): ?>
                            <div><?php echo htmlspecialchars($businessSettings['business_phone']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($businessSettings['business_email'])): ?>
                            <div><?php echo htmlspecialchars($businessSettings['business_email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($businessSettings['business_ein'])): ?>
                            <div>EIN: <?php echo htmlspecialchars($businessSettings['business_ein']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Invoice Title & Number -->
                    <div class="text-right">
                        <h2 class="text-4xl font-bold text-gray-900 mb-2">INVOICE</h2>
                        <div class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        
                        <?php 
                        $status = getInvoiceStatus($invoice, $pdo);
                        $statusStyles = [
                            'Paid' => 'bg-green-100 text-green-800 border-green-200',
                            'Partial' => 'bg-yellow-100 text-yellow-800 border-yellow-200', 
                            'Unpaid' => 'bg-red-100 text-red-800 border-red-200'
                        ];
                        ?>
                        <div class="inline-flex px-4 py-2 rounded-lg border font-semibold print-status <?php echo $statusStyles[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                            <?php echo $status; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Details & Bill To -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 print-grid">
                <!-- Bill To -->
                <div class="print-section">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">BILL TO:</h3>
                    <div class="border border-gray-300 p-4 bg-gray-50">
                        <div class="font-semibold text-gray-900 text-lg mb-2"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                        <?php if ($invoice['customer_email']): ?>
                        <div class="text-gray-600"><?php echo htmlspecialchars($invoice['customer_email']); ?></div>
                        <?php endif; ?>
                        <?php if ($invoice['customer_phone']): ?>
                        <div class="text-gray-600"><?php echo htmlspecialchars($invoice['customer_phone']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($invoice['property_name']): ?>
                        <div class="mt-3 pt-3 border-t border-gray-200 print-property">
                            <div class="font-medium text-gray-900">Service Location:</div>
                            <div class="text-gray-600"><?php echo htmlspecialchars($invoice['property_name']); ?></div>
                            <?php if ($invoice['property_address']): ?>
                            <div class="text-gray-600 text-sm"><?php echo nl2br(htmlspecialchars($invoice['property_address'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Invoice Details -->
                <div class="print-section">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">INVOICE DETAILS:</h3>
                    <div class="border border-gray-300 p-4 bg-gray-50">
                        <table class="w-full">
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Invoice Date:</td>
                                <td class="py-2 text-gray-900 text-right"><?php echo date('M d, Y', strtotime($invoice['date'])); ?></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Due Date:</td>
                                <td class="py-2 text-gray-900 text-right"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                            </tr>
                            <?php if ($totalPaid > 0): ?>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Amount Paid:</td>
                                <td class="py-2 text-green-600 text-right font-semibold"><?php echo formatCurrency($totalPaid); ?></td>
                            </tr>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Balance Due:</td>
                                <td class="py-2 <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?> text-right font-semibold"><?php echo formatCurrency($balance); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="mb-8">
                <table class="w-full print-table">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-4 py-3 text-left font-semibold text-gray-900">DESCRIPTION</th>
                            <th class="border border-gray-400 px-4 py-3 text-center font-semibold text-gray-900">QTY</th>
                            <th class="border border-gray-400 px-4 py-3 text-right font-semibold text-gray-900">RATE</th>
                            <th class="border border-gray-400 px-4 py-3 text-right font-semibold text-gray-900">AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineItems as $item): ?>
                        <tr>
                            <td class="border border-gray-400 px-4 py-3 text-gray-900"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="border border-gray-400 px-4 py-3 text-center text-gray-900">
                                <?php echo number_format($item['quantity'], $item['quantity'] == floor($item['quantity']) ? 0 : 2); ?>
                            </td>
                            <td class="border border-gray-400 px-4 py-3 text-right text-gray-900">
                                <?php echo formatCurrency($item['unit_price']); ?>
                            </td>
                            <td class="border border-gray-400 px-4 py-3 text-right font-semibold text-gray-900">
                                <?php echo formatCurrency($item['total']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals Section -->
            <div class="flex justify-end mb-8">
                <div class="w-full max-w-sm">
                    <div class="print-totals border border-gray-400 bg-gray-50 p-4">
                        <table class="w-full">
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Subtotal:</td>
                                <td class="py-2 text-gray-900 text-right font-semibold"><?php echo formatCurrency($invoice['subtotal']); ?></td>
                            </tr>
                            <?php if ($invoice['tax_amount'] > 0): ?>
                            <tr>
                                <td class="py-2 text-gray-600 font-medium">Tax (<?php echo number_format($invoice['tax_rate'], 2); ?>%):</td>
                                <td class="py-2 text-gray-900 text-right font-semibold"><?php echo formatCurrency($invoice['tax_amount']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-t-2 border-gray-400">
                                <td class="py-3 text-gray-900 font-bold text-lg">TOTAL:</td>
                                <td class="py-3 text-gray-900 text-right font-bold text-lg"><?php echo formatCurrency($invoice['total']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment Instructions -->
            <?php if (!empty($businessSettings['payment_instructions'])): ?>
            <div class="mb-8 print-instructions">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">PAYMENT INSTRUCTIONS:</h3>
                <div class="border border-gray-300 p-4 bg-gray-50">
                    <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($businessSettings['payment_instructions']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes (Screen only, not printed) -->
            <?php if ($invoice['notes']): ?>
            <div class="mb-8 no-print">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">NOTES:</h3>
                <div class="border border-gray-300 p-4 bg-gray-50">
                    <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($invoice['notes']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History (No Print) -->
            <?php if (!empty($payments)): ?>
            <div class="no-print">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment History:</h3>
                <div class="border border-gray-300 rounded-lg overflow-hidden">
                    <?php foreach ($payments as $payment): ?>
                    <div class="border-b border-gray-200 p-4 bg-white last:border-b-0">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($payment['method']); ?> - <?php echo formatCurrency($payment['amount']); ?></div>
                                <div class="text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    <?php if ($payment['notes']): ?>
                                    • <?php echo htmlspecialchars($payment['notes']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-green-600 font-semibold">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Payment Links (No Print) -->
            <?php if (!empty($businessSettings['cashapp_username']) || !empty($businessSettings['venmo_username'])): ?>
            <div class="no-print mt-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Payment Options:</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php if (!empty($businessSettings['cashapp_username'])): ?>
                    <a href="https://cash.app/$<?php echo htmlspecialchars($businessSettings['cashapp_username']); ?>/<?php echo number_format($balance, 2, '.', ''); ?>" 
                       target="_blank" 
                       class="flex items-center justify-between p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-white"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Cash App</div>
                                <div class="text-sm text-gray-600">$<?php echo htmlspecialchars($businessSettings['cashapp_username']); ?></div>
                            </div>
                        </div>
                        <i class="fas fa-external-link-alt text-green-600"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($businessSettings['venmo_username'])): ?>
                    <a href="https://account.venmo.com/payment-link?amount=<?php echo number_format($balance, 2, '.', ''); ?>&recipients=<?php echo htmlspecialchars($businessSettings['venmo_username']); ?>&txn=pay" 
                       target="_blank" 
                       class="flex items-center justify-between p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                <i class="fab fa-venmo text-white"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Venmo</div>
                                <div class="text-sm text-gray-600">@<?php echo htmlspecialchars($businessSettings['venmo_username']); ?></div>
                            </div>
                        </div>
                        <i class="fas fa-external-link-alt text-blue-600"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="text-center mt-12 pt-8 border-t border-gray-300 print-footer">
                <p class="text-gray-600 text-sm">Thank you for your business!</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>