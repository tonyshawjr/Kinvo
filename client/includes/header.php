<?php
// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get business settings for branding
if (!isset($businessSettings)) {
    $businessSettings = getBusinessSettings($pdo);
}
$appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
    ? $businessSettings['business_name'] 
    : 'Kinvo';
?>
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
                    <span class="ml-3 px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Client Portal</span>
                </div>

                <!-- Right side - User and mobile menu -->
                <div class="flex items-center space-x-6">
                    <div class="hidden lg:flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['client_customer_name'] ?? 'Client'); ?></span>
                        <a href="/client/logout.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors">
                            Logout
                        </a>
                    </div>
                    
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="lg:hidden p-2 text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <a href="/client/dashboard.php" class="text-base font-semibold <?php echo $current_page === 'dashboard' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Dashboard
                </a>
                <a href="/client/invoices.php" class="text-base font-semibold <?php echo $current_page === 'invoices' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Invoices
                </a>
                <a href="/client/estimates.php" class="text-base font-semibold <?php echo $current_page === 'estimates' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Estimates
                </a>
                <a href="/client/payments.php" class="text-base font-semibold <?php echo $current_page === 'payments' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Payments
                </a>
                <a href="/client/statements.php" class="text-base font-semibold <?php echo $current_page === 'statements' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Statements
                </a>
                <a href="/client/profile.php" class="text-base font-semibold <?php echo $current_page === 'profile' ? 'text-gray-900 border-b-2 border-gray-900 pb-1' : 'text-gray-600 hover:text-gray-900 transition-colors'; ?>">
                    Profile
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
            <button id="mobile-menu-close" class="p-2 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Mobile Navigation -->
        <nav class="flex-1 px-6 py-8 space-y-6 overflow-y-auto">
            <a href="/client/dashboard.php" class="block text-lg font-semibold <?php echo $current_page === 'dashboard' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Dashboard
            </a>
            <a href="/client/invoices.php" class="block text-lg font-semibold <?php echo $current_page === 'invoices' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Invoices
            </a>
            <a href="/client/estimates.php" class="block text-lg font-semibold <?php echo $current_page === 'estimates' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Estimates
            </a>
            <a href="/client/payments.php" class="block text-lg font-semibold <?php echo $current_page === 'payments' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Payments
            </a>
            <a href="/client/statements.php" class="block text-lg font-semibold <?php echo $current_page === 'statements' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Statements
            </a>
            <a href="/client/profile.php" class="block text-lg font-semibold <?php echo $current_page === 'profile' ? 'text-gray-900' : 'text-gray-600'; ?>">
                Profile
            </a>
        </nav>
        
        <!-- Mobile Menu Footer -->
        <div class="p-6 border-t border-gray-200">
            <div class="text-sm text-gray-600 mb-4">
                Welcome, <?php echo htmlspecialchars($_SESSION['client_name'] ?? 'Client'); ?>
            </div>
            <a href="/client/logout.php" class="block text-center text-sm font-semibold text-red-600 hover:text-red-700">
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