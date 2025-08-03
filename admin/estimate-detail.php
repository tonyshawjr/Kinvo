<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

// Get and validate estimate ID
$estimateId = $_GET['id'] ?? '';
if (!$estimateId || !is_numeric($estimateId) || $estimateId < 1) {
    header('Location: estimates.php');
    exit;
}

// Get estimate details
$estimate = getEstimate($pdo, (int)$estimateId);
if (!$estimate) {
    header('Location: estimates.php');
    exit;
}

// Get estimate items
$items = getEstimateItems($pdo, $estimateId);

// Get activity log
$stmt = $pdo->prepare("
    SELECT * FROM estimate_activity 
    WHERE estimate_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$estimateId]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if estimate can be edited or converted
$canEdit = canEditEstimate($estimate);
$canConvert = canConvertEstimate($estimate);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRFToken();
    
    $action = $_POST['action'];
    $success = false;
    $error = '';
    
    try {
        switch ($action) {
            case 'send':
                if ($estimate['status'] === 'Draft') {
                    $stmt = $pdo->prepare("UPDATE estimates SET status = 'Sent' WHERE id = ?");
                    $stmt->execute([$estimateId]);
                    logEstimateActivity($pdo, $estimateId, 'sent', 'Estimate marked as sent');
                    $success = true;
                }
                break;
                
            case 'delete':
                if ($canEdit) {
                    // Delete estimate (cascade will handle items and activity)
                    $stmt = $pdo->prepare("DELETE FROM estimates WHERE id = ?");
                    $stmt->execute([$estimateId]);
                    header('Location: estimates.php?deleted=1');
                    exit;
                }
                break;
                
            case 'approve':
                if ($estimate['status'] === 'Sent') {
                    $stmt = $pdo->prepare("UPDATE estimates SET status = 'Approved' WHERE id = ?");
                    $stmt->execute([$estimateId]);
                    logEstimateActivity($pdo, $estimateId, 'approved', 'Estimate approved by admin');
                    $success = true;
                }
                break;
                
            case 'reject':
                if ($estimate['status'] === 'Sent') {
                    $stmt = $pdo->prepare("UPDATE estimates SET status = 'Rejected' WHERE id = ?");
                    $stmt->execute([$estimateId]);
                    logEstimateActivity($pdo, $estimateId, 'rejected', 'Estimate rejected by admin');
                    $success = true;
                }
                break;
        }
        
        if ($success) {
            header("Location: estimate-detail.php?id=$estimateId&success=1");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Public estimate URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$baseUrl = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$publicUrl = $protocol . $_SERVER['HTTP_HOST'] . $baseUrl . "/public/view-estimate.php?id=" . $estimate['unique_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate <?php echo htmlspecialchars($estimate['estimate_number']); ?> - Kinvo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Estimate <?php echo htmlspecialchars($estimate['estimate_number']); ?>
                    </h1>
                    <p class="mt-1 text-gray-600">
                        Created on <?php echo date('F d, Y', strtotime($estimate['created_at'])); ?>
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <?php echo formatEstimateStatus($estimate['status']); ?>
                    <?php if ($estimate['converted_invoice_id']): ?>
                        <a href="invoice-detail.php?id=<?php echo $estimate['converted_invoice_id']; ?>" 
                           class="ml-2 text-sm text-green-600 hover:text-green-700">
                            <i class="fas fa-file-invoice"></i> View Invoice
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            Estimate updated successfully!
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex flex-wrap gap-3">
                <?php if ($canEdit): ?>
                    <a href="edit-estimate.php?id=<?php echo $estimateId; ?>" 
                       class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </a>
                <?php endif; ?>
                
                <?php if ($estimate['status'] === 'Draft'): ?>
                    <form method="POST" class="inline">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="send">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Mark as Sent
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($estimate['status'] === 'Sent'): ?>
                    <form method="POST" class="inline">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-check mr-2"></i>Approve
                        </button>
                    </form>
                    
                    <form method="POST" class="inline">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-times mr-2"></i>Reject
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($canConvert): ?>
                    <a href="convert-estimate.php?id=<?php echo $estimateId; ?>" 
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-file-invoice mr-2"></i>Convert to Invoice
                    </a>
                <?php endif; ?>
                
                <button onclick="copyPublicLink()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class="fas fa-link mr-2"></i>Copy Public Link
                </button>
                
                <a href="<?php echo htmlspecialchars($publicUrl); ?>" target="_blank" 
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class="fas fa-external-link-alt mr-2"></i>View Public
                </a>
                
                <?php if ($canEdit): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this estimate?');">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Info -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($estimate['customer_name']); ?></p>
                        </div>
                        <?php if ($estimate['customer_email']): ?>
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($estimate['customer_email']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($estimate['customer_phone']): ?>
                        <div>
                            <p class="text-sm text-gray-600">Phone</p>
                            <p class="font-medium"><?php echo htmlspecialchars($estimate['customer_phone']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($estimate['property_name']): ?>
                        <div>
                            <p class="text-sm text-gray-600">Property</p>
                            <p class="font-medium"><?php echo htmlspecialchars($estimate['property_name']); ?></p>
                            <?php if ($estimate['property_address']): ?>
                                <p class="text-sm text-gray-500"><?php echo nl2br(htmlspecialchars($estimate['property_address'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Qty
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Price
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
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
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo number_format($item['quantity'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        $<?php echo number_format($item['unit_price'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
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
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                        $<?php echo number_format($estimate['subtotal'], 2); ?>
                                    </td>
                                </tr>
                                <?php if ($estimate['tax_rate'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-3 text-sm font-medium text-gray-900 text-right">
                                        Tax (<?php echo number_format($estimate['tax_rate'], 2); ?>%):
                                    </td>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                        $<?php echo number_format($estimate['tax_amount'], 2); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-3 text-lg font-bold text-gray-900 text-right">
                                        Total:
                                    </td>
                                    <td class="px-6 py-3 text-lg font-bold text-gray-900">
                                        $<?php echo number_format($estimate['total'], 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($estimate['notes'] || $estimate['terms']): ?>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <?php if ($estimate['notes']): ?>
                    <div class="mb-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Notes</h3>
                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($estimate['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($estimate['terms']): ?>
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Terms & Conditions</h3>
                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($estimate['terms'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Estimate Info -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Estimate Details</h2>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm text-gray-600">Estimate Date</dt>
                            <dd class="font-medium"><?php echo date('F d, Y', strtotime($estimate['date'])); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-600">Expires On</dt>
                            <dd class="font-medium <?php echo strtotime($estimate['expires_date']) < time() ? 'text-red-600' : ''; ?>">
                                <?php echo date('F d, Y', strtotime($estimate['expires_date'])); ?>
                                <?php if (strtotime($estimate['expires_date']) < time()): ?>
                                    <span class="text-xs">(Expired)</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-600">Total Amount</dt>
                            <dd class="text-2xl font-bold text-gray-900">$<?php echo number_format($estimate['total'], 2); ?></dd>
                        </div>
                    </dl>
                </div>

                <!-- Activity Log -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Activity Log</h2>
                    <div class="space-y-3">
                        <?php foreach ($activities as $activity): ?>
                        <div class="text-sm">
                            <p class="font-medium text-gray-900">
                                <?php echo ucfirst($activity['action']); ?>
                            </p>
                            <?php if ($activity['description']): ?>
                                <p class="text-gray-600"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500">
                                <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copyPublicLink() {
        const url = '<?php echo $publicUrl; ?>';
        navigator.clipboard.writeText(url).then(function() {
            alert('Public link copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
            prompt('Copy this link:', url);
        });
    }
    </script>
</body>
</html>