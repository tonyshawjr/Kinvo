<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

// Set security headers for public estimate view
setSecurityHeaders(false, true);

$error = '';
$estimate = null;

// Get estimate by unique ID
$uniqueId = $_GET['id'] ?? '';

if (empty($uniqueId)) {
    $error = 'Estimate ID is required.';
} elseif (!preg_match('/^[a-f0-9]{32}$/', $uniqueId)) {
    // Validate that the unique_id is a proper hex format (32 chars)
    $error = 'Invalid estimate ID format.';
} else {
    $estimate = getEstimate($pdo, $uniqueId, true);
    if (!$estimate) {
        $error = 'Estimate not found.';
    }
}

// Only get items and settings if estimate was found
$items = [];
$estimateSettings = [];
if ($estimate) {
    $items = getEstimateItems($pdo, $estimate['id']);
    $estimateSettings = getEstimateSettings($pdo);
}

// Check if online approval is allowed
$allowOnlineApproval = isset($estimateSettings['allow_online_approval']) && $estimateSettings['allow_online_approval'] == '1';

// Handle approval/rejection
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $allowOnlineApproval) {
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject']) && $estimate['status'] === 'Sent') {
        try {
            $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
            
            $stmt = $pdo->prepare("UPDATE estimates SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $estimate['id']]);
            
            // Log activity
            $description = $action === 'approve' 
                ? 'Estimate approved by customer' 
                : 'Estimate rejected by customer';
            logEstimateActivity($pdo, $estimate['id'], $action . 'd', $description);
            
            // Auto-convert if setting is enabled
            if ($action === 'approve' && $estimateSettings['auto_convert_on_approval'] == '1') {
                // TODO: Implement auto-conversion
            }
            
            $message = $action === 'approve' 
                ? 'Thank you! This estimate has been approved.' 
                : 'This estimate has been rejected.';
            $messageType = $action === 'approve' ? 'success' : 'info';
            
            // Refresh estimate data
            $estimate = getEstimate($pdo, $uniqueId, true);
            
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again.';
            $messageType = 'error';
        }
    }
}

// Get business settings for branding
$businessSettings = getBusinessSettings($pdo);

// If there's an error, show error page
if ($error) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Estimate Not Found</title>
        <?php echo getAppleTouchIconTags('../'); ?>
        <meta name="apple-mobile-web-app-title" content="Kinvo">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Estimate Not Found</h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate <?php echo htmlspecialchars($estimate['estimate_number']); ?> - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <?php echo getAppleTouchIconTags('../'); ?>
    <meta name="apple-mobile-web-app-title" content="Kinvo">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-break-avoid {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6 print-break-avoid">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">ESTIMATE</h1>
                    <p class="text-lg text-gray-600"><?php echo htmlspecialchars($estimate['estimate_number']); ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h2>
                    <?php if ($businessSettings['business_phone']): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($businessSettings['business_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($businessSettings['business_email']): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($businessSettings['business_email']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Banner -->
            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php 
                echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
                    ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
                    'bg-blue-50 text-blue-800 border border-blue-200'); 
            ?>">
                <p class="font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
            <?php endif; ?>

            <!-- Estimate Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Bill To</h3>
                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($estimate['customer_name']); ?></p>
                    <?php if ($estimate['customer_email']): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($estimate['customer_email']); ?></p>
                    <?php endif; ?>
                    <?php if ($estimate['customer_phone']): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($estimate['customer_phone']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-3">Estimate Details</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Date:</dt>
                            <dd class="font-medium"><?php echo date('F d, Y', strtotime($estimate['date'])); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Valid Until:</dt>
                            <dd class="font-medium <?php echo strtotime($estimate['expires_date']) < time() ? 'text-red-600' : ''; ?>">
                                <?php echo date('F d, Y', strtotime($estimate['expires_date'])); ?>
                                <?php if (strtotime($estimate['expires_date']) < time()): ?>
                                    <span class="text-xs">(Expired)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">Status:</dt>
                            <dd><?php echo formatEstimateStatus($estimate['status']); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <?php if ($estimate['property_name']): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">Property/Location</h3>
                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($estimate['property_name']); ?></p>
                <?php if ($estimate['property_address']): ?>
                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($estimate['property_address'])); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Line Items -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6 print-break-avoid">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Qty
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Price
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                <?php echo number_format($item['quantity'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                $<?php echo number_format($item['unit_price'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 text-right">
                                $<?php echo number_format($item['total'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-sm font-medium text-gray-900 text-right">
                                Subtotal:
                            </td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 text-right">
                                $<?php echo number_format($estimate['subtotal'], 2); ?>
                            </td>
                        </tr>
                        <?php if ($estimate['tax_rate'] > 0): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-sm font-medium text-gray-900 text-right">
                                Tax (<?php echo number_format($estimate['tax_rate'], 2); ?>%):
                            </td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900 text-right">
                                $<?php echo number_format($estimate['tax_amount'], 2); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-lg font-bold text-gray-900 text-right">
                                Total:
                            </td>
                            <td class="px-6 py-4 text-lg font-bold text-gray-900 text-right">
                                $<?php echo number_format($estimate['total'], 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Notes and Terms -->
        <?php if ($estimate['notes'] || $estimate['terms']): ?>
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6 print-break-avoid">
            <?php if ($estimate['notes']): ?>
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-700 uppercase tracking-wide mb-2">Notes</h3>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($estimate['notes'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($estimate['terms']): ?>
            <div>
                <h3 class="text-sm font-medium text-gray-700 uppercase tracking-wide mb-2">Terms & Conditions</h3>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($estimate['terms'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <?php if ($estimate['status'] === 'Sent' && $allowOnlineApproval): ?>
        <div class="bg-white rounded-lg shadow-sm p-6 no-print">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Approve or Reject This Estimate</h3>
            <p class="text-gray-600 mb-6">Please review the estimate above and click one of the buttons below to approve or reject it.</p>
            
            <form method="POST" class="flex flex-col sm:flex-row gap-4">
                <button type="submit" name="action" value="approve" 
                        class="flex-1 px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-check mr-2"></i>Approve Estimate
                </button>
                <button type="submit" name="action" value="reject" 
                        class="flex-1 px-6 py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-times mr-2"></i>Reject Estimate
                </button>
            </form>
        </div>
        <?php elseif ($estimate['status'] === 'Approved'): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center no-print">
            <i class="fas fa-check-circle text-4xl text-green-600 mb-3"></i>
            <h3 class="text-lg font-semibold text-green-900 mb-2">This estimate has been approved</h3>
            <p class="text-green-700">We'll contact you shortly to proceed with the work.</p>
        </div>
        <?php elseif ($estimate['status'] === 'Rejected'): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center no-print">
            <i class="fas fa-times-circle text-4xl text-red-600 mb-3"></i>
            <h3 class="text-lg font-semibold text-red-900 mb-2">This estimate has been rejected</h3>
        </div>
        <?php elseif ($estimate['status'] === 'Expired'): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center no-print">
            <i class="fas fa-clock text-4xl text-yellow-600 mb-3"></i>
            <h3 class="text-lg font-semibold text-yellow-900 mb-2">This estimate has expired</h3>
            <p class="text-yellow-700">Please contact us if you'd like an updated estimate.</p>
        </div>
        <?php endif; ?>

        <!-- Print Button -->
        <div class="mt-6 text-center no-print">
            <button onclick="window.print()" class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-print mr-2"></i>Print Estimate
            </button>
        </div>
    </div>
</body>
</html>