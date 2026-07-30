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
<style>
    html {
        scroll-padding-top: 6rem;
    }

    @media (min-width: 1024px) {
        html {
            scroll-padding-top: 10rem;
        }
    }
</style>
<!-- Modern Header with Navigation Below -->
<header class="bg-white sticky top-0 z-50 shadow-sm">
    <!-- Top Bar with Logo and User Info -->
    <div class="border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-2xl font-bold text-gray-900 hover:text-gray-700 transition-colors">
                        <?php echo htmlspecialchars($appName); ?>
                    </a>
                </div>

                <!-- Right side - User and mobile menu -->
                <div class="flex items-center space-x-6">
                    <div class="hidden lg:flex items-center">
                        <a href="logout.php" class="min-h-[44px] inline-flex items-center text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors">
                            Logout
                        </a>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" type="button" aria-label="Open main menu" aria-expanded="false" aria-controls="mobile-menu" class="lg:hidden min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-700 hover:text-gray-900">
                        <svg class="w-6 h-6" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Navigation - Below Logo -->
    <nav class="hidden lg:block border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-4">
                <a href="dashboard.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo $current_page === 'dashboard' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Dashboard
                </a>
                <a href="invoices.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo in_array($current_page, ['invoices', 'create-invoice', 'edit-invoice']) ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Invoices
                </a>
                <a href="estimates.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo in_array($current_page, ['estimates', 'create-estimate', 'estimate-detail', 'edit-estimate']) ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Estimates
                </a>
                <a href="customers.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo in_array($current_page, ['customers', 'customer-detail', 'customer-edit']) ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Customers
                </a>
                <a href="payments.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo $current_page === 'payments' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Payments
                </a>
                <a href="settings.php" class="min-h-[44px] inline-flex items-center text-base font-semibold <?php echo $current_page === 'settings' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Settings
                </a>
            </div>
        </div>
    </nav>
</header>

<!-- Mobile Menu Overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-[60] lg:hidden hidden opacity-0 transition-opacity duration-300"></div>

<!-- Mobile Menu -->
<div id="mobile-menu" class="fixed inset-y-0 right-0 w-full max-w-sm bg-white shadow-xl z-[70] lg:hidden transform translate-x-full transition-transform duration-300">
    <div class="flex flex-col h-full">
        <!-- Mobile Menu Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Menu</h2>
            <button id="mobile-menu-close" type="button" aria-label="Close main menu" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-gray-700 hover:text-gray-900">
                <svg class="w-5 h-5" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Mobile Navigation -->
        <nav class="flex-1 px-6 py-8 space-y-6 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo $current_page === 'dashboard' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Dashboard
            </a>
            <div class="space-y-2">
                <a href="invoices.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo in_array($current_page, ['invoices', 'create-invoice', 'edit-invoice']) ? 'text-gray-900' : 'text-gray-600'; ?>">
                    Invoices
                </a>
                <a href="create-invoice.php" class="flex items-center min-h-[44px] ml-4 text-base <?php echo $current_page === 'create-invoice' ? 'text-gray-900' : 'text-gray-700'; ?>">
                    + New Invoice
                </a>
            </div>
            <div class="space-y-2">
                <a href="estimates.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo in_array($current_page, ['estimates', 'create-estimate', 'estimate-detail', 'edit-estimate']) ? 'text-gray-900' : 'text-gray-600'; ?>">
                    Estimates
                </a>
                <a href="create-estimate.php" class="flex items-center min-h-[44px] ml-4 text-base <?php echo $current_page === 'create-estimate' ? 'text-gray-900' : 'text-gray-700'; ?>">
                    + New Estimate
                </a>
            </div>
            <a href="customers.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo in_array($current_page, ['customers', 'customer-detail', 'customer-edit']) ? 'text-gray-900' : 'text-gray-600'; ?>">
                Customers
            </a>
            <a href="payments.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo $current_page === 'payments' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Payments
            </a>
            <a href="settings.php" class="flex items-center min-h-[44px] text-lg font-semibold <?php echo $current_page === 'settings' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Settings
            </a>
        </nav>
        
        <!-- Mobile Menu Footer -->
        <div class="p-6 border-t border-gray-200">
            <a href="logout.php" class="flex items-center justify-center min-h-[44px] text-sm font-semibold text-red-700 hover:text-red-800">
                Logout
            </a>
        </div>
    </div>
</div>

<!-- Mobile Menu Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.getElementById('mobile-menu-button');
    const menuClose = document.getElementById('mobile-menu-close');
    const mobileMenu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('mobile-menu-overlay');
    
    function openMenu() {
        overlay.classList.remove('hidden');
        setTimeout(() => {
            overlay.classList.add('opacity-100');
            mobileMenu.classList.remove('translate-x-full');
        }, 10);
        document.body.style.overflow = 'hidden';
    }
    
    function closeMenu() {
        overlay.classList.remove('opacity-100');
        mobileMenu.classList.add('translate-x-full');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
        document.body.style.overflow = '';
    }
    
    menuButton.addEventListener('click', openMenu);
    menuClose.addEventListener('click', closeMenu);
    overlay.addEventListener('click', closeMenu);
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !mobileMenu.classList.contains('translate-x-full')) {
            closeMenu();
        }
    });
});
</script>