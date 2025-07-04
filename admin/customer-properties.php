<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(true, true);

requireAdmin();

$customerId = $_GET['customer_id'] ?? null;
$success = false;
$error = '';

if (!$customerId) {
    header('Location: customers.php');
    exit;
}

// Add authorization check for customer ownership
requireResourceOwnership($pdo, 'customer', $customerId);

// Get customer information
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Create table if it doesn't exist (auto-migration)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customer_properties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            property_name VARCHAR(255) NOT NULL,
            address TEXT,
            property_type ENUM('AirBnB', 'Personal Home', 'Rental Property', 'Business', 'Other') DEFAULT 'AirBnB',
            notes TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )
    ");
    
    // Add property_id column to invoices table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN property_id INT NULL AFTER customer_id");
    }
} catch (Exception $e) {
    // Table probably already exists
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO customer_properties (customer_id, property_name, address, property_type, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customerId,
                    $_POST['property_name'],
                    $_POST['address'],
                    $_POST['property_type'],
                    $_POST['notes']
                ]);
                $success = "Property added successfully!";
            } elseif ($_POST['action'] === 'toggle') {
                $propertyId = $_POST['property_id'];
                // Verify property belongs to this customer
                $stmt = $pdo->prepare("SELECT id FROM customer_properties WHERE id = ? AND customer_id = ?");
                $stmt->execute([$propertyId, $customerId]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    die('Property not found or access denied.');
                }
                
                $stmt = $pdo->prepare("UPDATE customer_properties SET is_active = NOT is_active WHERE id = ? AND customer_id = ?");
                $stmt->execute([$propertyId, $customerId]);
                $success = "Property status updated!";
            } elseif ($_POST['action'] === 'delete') {
                $propertyId = $_POST['property_id'];
                // Verify property belongs to this customer
                $stmt = $pdo->prepare("SELECT id FROM customer_properties WHERE id = ? AND customer_id = ?");
                $stmt->execute([$propertyId, $customerId]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    die('Property not found or access denied.');
                }
                
                // Check if property has invoices (only if property_id column exists)
                $invoiceCount = 0;
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE property_id = ?");
                        $stmt->execute([$propertyId]);
                        $invoiceCount = $stmt->fetchColumn();
                    }
                } catch (Exception $e) {
                    // Column doesn't exist yet, safe to delete
                }
                
                if ($invoiceCount > 0) {
                    $error = "Cannot delete property - it has {$invoiceCount} invoice(s) associated with it.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM customer_properties WHERE id = ? AND customer_id = ?");
                    $stmt->execute([$propertyId, $customerId]);
                    $success = "Property deleted successfully!";
                }
            }
        }
    } catch (Exception $e) {
        handleDatabaseError('customer property operation', $e, 'property management');
    }
}

// Get all properties for this customer
try {
    // Check if property_id column exists in invoices table
    $stmt = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'property_id'");
    $hasPropertyColumn = $stmt->rowCount() > 0;
    
    if ($hasPropertyColumn) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COUNT(i.id) as invoice_count,
                   MAX(i.date) as last_invoice_date
            FROM customer_properties p 
            LEFT JOIN invoices i ON p.id = i.property_id 
            WHERE p.customer_id = ? 
            GROUP BY p.id 
            ORDER BY p.created_at DESC
        ");
    } else {
        // If property_id column doesn't exist yet, just get properties without invoice counts
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   0 as invoice_count,
                   NULL as last_invoice_date
            FROM customer_properties p 
            WHERE p.customer_id = ? 
            ORDER BY p.created_at DESC
        ");
    }
    $stmt->execute([$customerId]);
    $properties = $stmt->fetchAll();
} catch (Exception $e) {
    // If customer_properties table doesn't exist yet, return empty array
    $properties = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - <?php echo htmlspecialchars($customer['name']); ?><?php 
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

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-4">
                <a href="customer-detail.php?id=<?php echo $customer['id']; ?>" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Properties</h2>
                    <p class="text-gray-600 mt-1">Manage properties for <?php echo htmlspecialchars($customer['name']); ?></p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add New Property Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-plus mr-3 text-gray-600"></i>
                            Add New Property
                        </h3>
                    </div>
                    <form method="POST" class="p-6 space-y-6">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-home mr-1"></i>Property Name *
                            </label>
                            <input type="text" name="property_name" required
                                   placeholder="e.g., Downtown AirBnB, Main House"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
                            <p class="mt-1 text-sm text-gray-500">A short name to identify the property</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>Address
                            </label>
                            <textarea name="address" rows="3"
                                      placeholder="123 Main St, City, State 12345"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 resize-none"></textarea>
                            <p class="mt-1 text-sm text-gray-500">Full address of the property</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1"></i>Property Type
                            </label>
                            <select name="property_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
                                <option value="AirBnB">AirBnB</option>
                                <option value="Personal Home">Personal Home</option>
                                <option value="Rental Property">Rental Property</option>
                                <option value="Business">Business</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-1"></i>Notes
                            </label>
                            <textarea name="notes" rows="3"
                                      placeholder="Special instructions, access codes, etc."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 resize-none"></textarea>
                        </div>

                        <button type="submit" class="w-full px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-semibold">
                            <i class="fas fa-plus mr-2"></i>Add Property
                        </button>
                    </form>
                </div>
            </div>

            <!-- Properties List -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-building mr-3 text-gray-600"></i>
                            Customer Properties
                        </h3>
                        <p class="text-sm text-gray-600 mt-1"><?php echo count($properties); ?> propert<?php echo count($properties) != 1 ? 'ies' : 'y'; ?></p>
                    </div>
                    <div class="p-6">
                        <?php if (empty($properties)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-building text-gray-400 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">No Properties Yet</h4>
                            <p class="text-gray-600">Add the first property for this customer using the form on the left.</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($properties as $property): ?>
                            <div class="border border-gray-200 rounded-lg p-6 <?php echo $property['is_active'] ? 'bg-white' : 'bg-gray-50'; ?>">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($property['property_name']); ?></h4>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $property['property_type'] === 'AirBnB' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo htmlspecialchars($property['property_type']); ?>
                                            </span>
                                            <?php if (!$property['is_active']): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($property['address']): ?>
                                        <div class="flex items-start space-x-2 text-sm text-gray-600 mb-2">
                                            <i class="fas fa-map-marker-alt mt-0.5"></i>
                                            <span><?php echo nl2br(htmlspecialchars($property['address'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($property['notes']): ?>
                                        <div class="flex items-start space-x-2 text-sm text-gray-600 mb-3">
                                            <i class="fas fa-sticky-note mt-0.5"></i>
                                            <span><?php echo nl2br(htmlspecialchars($property['notes'])); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="flex items-center space-x-4 text-sm text-gray-500">
                                            <span><i class="fas fa-file-invoice mr-1"></i><?php echo $property['invoice_count']; ?> invoice<?php echo $property['invoice_count'] != 1 ? 's' : ''; ?></span>
                                            <?php if ($property['last_invoice_date']): ?>
                                            <span><i class="fas fa-clock mr-1"></i>Last: <?php echo date('M d, Y', strtotime($property['last_invoice_date'])); ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-calendar mr-1"></i>Added <?php echo date('M d, Y', strtotime($property['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 ml-4">
                                        <a href="create-invoice.php?customer_id=<?php echo $customerId; ?>&property_id=<?php echo $property['id']; ?>" 
                                           class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Create Invoice">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Toggle active status for this property?')">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                            <button type="submit" class="p-2 <?php echo $property['is_active'] ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> rounded-lg transition-colors" 
                                                    title="<?php echo $property['is_active'] ? 'Deactivate' : 'Activate'; ?> Property">
                                                <i class="fas <?php echo $property['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if ($property['invoice_count'] == 0): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this property? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                            <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Property">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <div class="p-2 text-gray-400" title="Cannot delete - has invoices">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>