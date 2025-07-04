<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireAdmin();

$error = '';
$step = $_GET['step'] ?? 1;

// Handle reset confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    if ($_POST['confirm_reset'] === 'DELETE EVERYTHING') {
        try {
            // Get table prefix if exists
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
            
            // List of tables to drop (in correct order due to foreign keys)
            $tables = [
                'payments',
                'invoice_items', 
                'invoices',
                'customer_properties',
                'customers',
                'business_settings'
            ];
            
            // Drop each table
            foreach ($tables as $table) {
                $fullTableName = $prefix . $table;
                $pdo->exec("DROP TABLE IF EXISTS `$fullTableName`");
            }
            
            // Delete config file
            if (file_exists('../includes/config.php')) {
                unlink('../includes/config.php');
            }
            
            // Delete .installed marker
            if (file_exists('../includes/.installed')) {
                unlink('../includes/.installed');
            }
            
            // Destroy session
            session_destroy();
            
            // Redirect to installer
            header('Location: ../install.php');
            exit;
            
        } catch (Exception $e) {
            $error = "Reset failed: " . $e->getMessage();
        }
    } else {
        $error = "Confirmation text did not match. Please type exactly: DELETE EVERYTHING";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Installation - Kinvo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-4">
                <a href="settings.php" class="p-2 text-gray-500 hover:text-gray-700 transition-colors">
                    ←
                </a>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900">Reset Installation</h2>
                    <p class="text-gray-600 mt-1">Completely remove all data and start fresh</p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8 shadow-sm">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <span class="text-red-600 text-xl font-bold">!</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-red-900 mb-2">Error</h3>
                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- Step 1: Warning -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                <h3 class="text-lg font-semibold text-red-900 flex items-center">
                    <span class="mr-3 text-red-600 text-xl">⚠</span>
                    Extreme Caution Required
                </h3>
            </div>
            <div class="p-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                    <h4 class="font-bold text-red-900 mb-3">This action will permanently delete:</h4>
                    <ul class="space-y-2 text-red-800">
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>All customers and their information</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>All invoices and invoice items</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>All payment records</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>All customer properties/locations</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>All business settings and configuration</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-600 mt-0.5 mr-2">×</span>
                            <span>The admin password</span>
                        </li>
                    </ul>
                    <p class="mt-4 font-bold text-red-900">This action CANNOT be undone!</p>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <span class="text-yellow-600 mt-0.5 mr-3 text-lg">ℹ</span>
                        <div>
                            <h4 class="font-semibold text-yellow-900">Why would you do this?</h4>
                            <ul class="mt-2 text-sm text-yellow-800 space-y-1">
                                <li>• Testing the installation process</li>
                                <li>• Starting fresh with a clean system</li>
                                <li>• Removing test data before going live</li>
                                <li>• Troubleshooting installation issues</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <span class="text-blue-600 mt-0.5 mr-3 text-lg">💡</span>
                        <div>
                            <h4 class="font-semibold text-blue-900">Before proceeding:</h4>
                            <ul class="mt-2 text-sm text-blue-800 space-y-1">
                                <li>• Export any important invoices as PDFs</li>
                                <li>• Note down customer information you need</li>
                                <li>• Save your business settings</li>
                                <li>• Consider backing up the database instead</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <a href="settings.php" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <a href="?step=2" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        I Understand, Continue
                    </a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Step 2: Final Confirmation -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-red-50 px-6 py-4 border-b border-red-200">
                <h3 class="text-lg font-semibold text-red-900 flex items-center">
                    <span class="mr-3 text-red-600 text-xl">☠</span>
                    Final Confirmation Required
                </h3>
            </div>
            <form method="POST" class="p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-4">
                        <span class="text-red-600 text-3xl">💣</span>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-2">Point of No Return</h4>
                    <p class="text-gray-600">To confirm you want to completely reset Kinvo and delete ALL data,<br>
                    please type the following text exactly:</p>
                    <p class="mt-4 text-2xl font-mono font-bold text-red-600">DELETE EVERYTHING</p>
                </div>

                <div class="max-w-md mx-auto mb-6">
                    <input type="text" name="confirm_reset" 
                           placeholder="Type confirmation text here"
                           class="w-full px-4 py-3 border-2 border-red-300 rounded-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 text-center text-lg font-mono"
                           autocomplete="off">
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h5 class="font-semibold text-gray-900 mb-2">What happens next:</h5>
                    <ol class="text-sm text-gray-700 space-y-1 list-decimal list-inside">
                        <li>All database tables will be dropped</li>
                        <li>Configuration files will be deleted</li>
                        <li>You will be logged out</li>
                        <li>You will be redirected to the installation wizard</li>
                        <li>You'll need to set up Kinvo from scratch</li>
                    </ol>
                </div>

                <div class="flex justify-between">
                    <a href="?step=1" class="inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        Back
                    </a>
                    <button type="submit" class="inline-flex items-center px-8 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-bold">
                        Reset Everything
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>