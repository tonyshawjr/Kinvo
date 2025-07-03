    <?php
// Ensure we have business settings available
if (!isset($businessSettings)) {
    require_once __DIR__ . '/functions.php';
    if (isset($pdo)) {
        $businessSettings = getBusinessSettings($pdo);
    } else {
        // Fallback if no database connection
        $businessSettings = [
            'business_name' => 'Your Business Name',
            'business_phone' => '',
            'business_email' => '',
            'business_ein' => '',
            'cashapp_username' => '',
            'venmo_username' => ''
        ];
    }
}
?>

<!-- Footer -->
    <footer class="bg-white/80 backdrop-blur-lg border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Company Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h3>
                        <p class="text-sm text-gray-600 mb-4">Professional business management system.</p>
                        <div class="flex space-x-4">
                            <?php if (!empty($businessSettings['cashapp_username'])): ?>
                            <a href="https://cash.app/$<?php echo htmlspecialchars($businessSettings['cashapp_username']); ?>" target="_blank" class="text-gray-400 hover:text-green-600 transition-colors" title="Cash App">
                                <i class="fas fa-dollar-sign"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($businessSettings['venmo_username'])): ?>
                            <a href="https://venmo.com/u/<?php echo htmlspecialchars($businessSettings['venmo_username']); ?>" target="_blank" class="text-gray-400 hover:text-blue-600 transition-colors" title="Venmo">
                                <i class="fab fa-venmo"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                        <ul class="space-y-2">
                            <?php if (isAdmin()): ?>
                            <li><a href="/admin/dashboard.php" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Dashboard</a></li>
                            <li><a href="/admin/create-invoice.php" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Create Invoice</a></li>
                            <li><a href="/admin/customers.php" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Customers</a></li>
                            <li><a href="/admin/settings.php" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Settings</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Contact Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact</h3>
                        <ul class="space-y-2">
                            <?php if (!empty($businessSettings['business_phone'])): ?>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>
                                <a href="tel:<?php echo htmlspecialchars($businessSettings['business_phone']); ?>" class="hover:text-blue-600 transition-colors">
                                    <?php echo htmlspecialchars($businessSettings['business_phone']); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($businessSettings['business_email'])): ?>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                <a href="mailto:<?php echo htmlspecialchars($businessSettings['business_email']); ?>" class="hover:text-blue-600 transition-colors">
                                    <?php echo htmlspecialchars($businessSettings['business_email']); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($businessSettings['business_ein'])): ?>
                            <li class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-building mr-2 text-gray-400"></i>
                                EIN: <?php echo htmlspecialchars($businessSettings['business_ein']); ?>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Bottom Bar -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <p class="text-sm text-gray-500">
                            &copy; <?php echo date('Y'); ?> Kinvo. All rights reserved.
                        </p>
                        <p class="text-sm text-gray-500 mt-2 md:mt-0">
                            Powered by <strong>Kinvo</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>