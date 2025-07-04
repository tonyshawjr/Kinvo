<?php
if (!isset($businessSettings)) {
    $businessSettings = getBusinessSettings($pdo);
}
$businessName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name' 
    ? $businessSettings['business_name'] 
    : 'Kinvo';
?>
<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <p class="text-sm text-gray-600">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($businessName); ?>. All rights reserved.
                </p>
            </div>
            <div class="flex space-x-6">
                <a href="/client/dashboard.php" class="text-sm text-gray-600 hover:text-gray-900">Dashboard</a>
                <a href="/client/invoices.php" class="text-sm text-gray-600 hover:text-gray-900">Invoices</a>
                <a href="/client/payments.php" class="text-sm text-gray-600 hover:text-gray-900">Payments</a>
                <a href="/client/profile.php" class="text-sm text-gray-600 hover:text-gray-900">Profile</a>
            </div>
        </div>
    </div>
</footer>