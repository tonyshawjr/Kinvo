<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Manual includes with error checking
try {
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    require_once '../includes/estimate-functions.php';
} catch (Exception $e) {
    die("Include error: " . $e->getMessage());
}

// Require admin authentication
requireAdmin();

// Get estimate ID
$estimateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($estimateId <= 0) {
    die("Invalid estimate ID");
}

// Get estimate
try {
    $estimate = getEstimate($pdo, $estimateId);
    if (!$estimate) {
        die("Estimate not found");
    }
    
    // Get items
    $items = getEstimateItems($pdo, $estimateId);
    
} catch (Exception $e) {
    die("Error loading estimate: " . $e->getMessage());
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber($pdo);
        $uniqueId = generateUniqueId();
        
        // Create invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (customer_id, invoice_number, date, due_date, 
                                status, subtotal, tax_rate, tax_amount, total, notes, unique_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notes = "Created from Estimate " . $estimate['estimate_number'];
        
        $stmt->execute([
            $estimate['customer_id'],
            $invoiceNumber,
            date('Y-m-d'),
            date('Y-m-d', strtotime('+30 days')),
            'Unpaid',
            $estimate['subtotal'],
            $estimate['tax_rate'],
            $estimate['tax_amount'],
            $estimate['total'],
            $notes,
            $uniqueId
        ]);
        
        $invoiceId = $pdo->lastInsertId();
        
        // Copy line items
        $stmt = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $invoiceId,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['total']
            ]);
        }
        
        // Update estimate
        $stmt = $pdo->prepare("UPDATE estimates SET converted_invoice_id = ? WHERE id = ?");
        $stmt->execute([$invoiceId, $estimateId]);
        
        $pdo->commit();
        $success = true;
        
        // Redirect to view invoice page
        header("Location: ../public/view-invoice.php?id=$uniqueId&converted=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Estimate to Invoice - Kinvo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Convert Estimate to Invoice</h1>
    
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <p class="font-medium">Error: <?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Estimate Details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-gray-600">Estimate Number</dt>
                    <dd class="font-medium"><?php echo htmlspecialchars($estimate['estimate_number']); ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-600">Customer</dt>
                    <dd class="font-medium"><?php echo htmlspecialchars($estimate['customer_name']); ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-600">Total Amount</dt>
                    <dd class="font-medium text-lg">$<?php echo number_format($estimate['total'], 2); ?></dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-600">Line Items</dt>
                    <dd class="font-medium"><?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?></dd>
                </div>
            </dl>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">What will happen:</h3>
            <ul class="list-disc list-inside text-blue-800 space-y-1">
                <li>A new invoice will be created with today's date</li>
                <li>The due date will be set to 30 days from today</li>
                <li>All line items and amounts will be copied exactly</li>
                <li>The estimate will be marked as converted</li>
                <li>You'll be redirected to edit the new invoice</li>
            </ul>
        </div>
        
        <form method="POST" class="flex flex-col sm:flex-row gap-4">
            <button type="submit" class="px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-file-invoice mr-2"></i>Convert to Invoice
            </button>
            <a href="estimate-detail.php?id=<?php echo $estimateId; ?>" 
               class="px-6 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors text-center">
                Cancel
            </a>
        </form>
    </div>
</body>
</html>