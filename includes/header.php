<?php
// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get business settings for branding
require_once __DIR__ . '/functions.php';
if (!isset($businessSettings)) {
    $businessSettings = getBusinessSettings($pdo);
}
$appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
    ? $businessSettings['business_name'] 
    : 'Kinvo';
?>
<!-- Navigation -->
<nav class="bg-white/80 backdrop-blur-lg border-b border-gray-200/50 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl flex items-center justify-center">
                        <i class="fas fa-receipt text-white text-lg"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent"><?php echo htmlspecialchars($appName); ?></h1>
                    <p class="text-xs text-gray-500">Business Management</p>
                </div>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-1">
                <a href="dashboard.php" class="px-4 py-2 text-sm font-medium <?php echo $current_page === 'dashboard' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>Dashboard
                </a>
                <a href="create-invoice.php" class="px-4 py-2 text-sm font-medium <?php echo $current_page === 'create-invoice' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-plus mr-2"></i>New Invoice
                </a>
                <a href="invoices.php" class="px-4 py-2 text-sm font-medium <?php echo $current_page === 'invoices' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-file-invoice mr-2"></i>Invoices
                </a>
                <a href="customers.php" class="px-4 py-2 text-sm font-medium <?php echo in_array($current_page, ['customers', 'customer-detail', 'customer-edit']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-users mr-2"></i>Customers
                </a>
                <a href="payments.php" class="px-4 py-2 text-sm font-medium <?php echo $current_page === 'payments' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-credit-card mr-2"></i>Payments
                </a>
                <a href="settings.php" class="px-4 py-2 text-sm font-medium <?php echo $current_page === 'settings' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-cog mr-2"></i>Settings
                </a>
            </div>

            <!-- Right side - User info and mobile menu -->
            <div class="flex items-center space-x-3">
                <div class="hidden sm:block text-right">
                    <p class="text-sm font-medium text-gray-900">Admin</p>
                    <p class="text-xs text-gray-500">Logged in</p>
                </div>
                <a href="logout.php" class="p-2 text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                
                <!-- Mobile menu button -->
                <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="md:hidden hidden border-t border-gray-200 bg-white">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="block px-3 py-2 text-base font-medium <?php echo $current_page === 'dashboard' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-chart-line mr-3"></i>Dashboard
                </a>
                <a href="create-invoice.php" class="block px-3 py-2 text-base font-medium <?php echo $current_page === 'create-invoice' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-plus mr-3"></i>New Invoice
                </a>
                <a href="invoices.php" class="block px-3 py-2 text-base font-medium <?php echo $current_page === 'invoices' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-file-invoice mr-3"></i>Invoices
                </a>
                <a href="customers.php" class="block px-3 py-2 text-base font-medium <?php echo in_array($current_page, ['customers', 'customer-detail', 'customer-edit']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-users mr-3"></i>Customers
                </a>
                <a href="payments.php" class="block px-3 py-2 text-base font-medium <?php echo $current_page === 'payments' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-credit-card mr-3"></i>Payments
                </a>
                <a href="settings.php" class="block px-3 py-2 text-base font-medium <?php echo $current_page === 'settings' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'; ?> rounded-lg transition-colors">
                    <i class="fas fa-cog mr-3"></i>Settings
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Menu Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    menuButton.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!menuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
            mobileMenu.classList.add('hidden');
        }
    });
});
</script>