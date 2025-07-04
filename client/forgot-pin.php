<?php
session_start();
define('SECURE_CONFIG_LOADER', true);
require_once '../includes/config_loader.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders(false, true);

$success = '';
$error = '';
$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? '';

// Check if already logged in
if (isClientLoggedIn()) {
    header('Location: /client/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    
    // Check rate limiting for PIN reset requests
    checkRateLimit($pdo, 'pin_reset', 3, 15, 60); // Stricter limits for PIN reset
    
    try {
        if ($step === 'request') {
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                throw new Exception('Please enter your email address.');
            }
            
            // Check if email exists
            $client = getClientByEmail($pdo, $email);
            if (!$client) {
                throw new Exception('No account found with this email address.');
            }
            
            if (!$client['is_active']) {
                throw new Exception('Account is disabled. Please contact support.');
            }
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE client_auth SET pin_reset_token = ?, pin_reset_expires = ? WHERE customer_id = ?");
            $stmt->execute([$resetToken, $resetExpires, $client['id']]);
            
            // Log the request
            logClientActivity($pdo, $client['id'], 'pin_reset_requested', 'PIN reset requested via forgot PIN page');
            
            // In a real application, you would send an email here
            // For demo purposes, we'll show the reset link
            $resetLink = "/client/forgot-pin.php?step=reset&token=" . $resetToken;
            
            $success = "PIN reset instructions have been sent to your email address. For demo purposes, here is your reset link: <a href='$resetLink' class='text-blue-600 hover:text-blue-500 underline'>Reset PIN</a>";
            
        } elseif ($step === 'reset') {
            $newPin = trim($_POST['pin'] ?? '');
            $confirmPin = trim($_POST['confirm_pin'] ?? '');
            
            if (empty($newPin) || empty($confirmPin)) {
                throw new Exception('Please enter and confirm your new PIN.');
            }
            
            if ($newPin !== $confirmPin) {
                throw new Exception('PINs do not match.');
            }
            
            // Use comprehensive PIN strength validation
            $pinErrors = validatePINStrength($newPin);
            if (!empty($pinErrors)) {
                throw new Exception('PIN requirements not met: ' . implode(', ', $pinErrors));
            }
            
            // Verify token
            $stmt = $pdo->prepare("
                SELECT c.*, ca.customer_id, ca.pin_reset_expires
                FROM client_auth ca
                JOIN customers c ON c.id = ca.customer_id
                WHERE ca.pin_reset_token = ? AND ca.pin_reset_expires > NOW() AND ca.is_active = 1
            ");
            $stmt->execute([$token]);
            $client = $stmt->fetch();
            
            if (!$client) {
                throw new Exception('Invalid or expired reset token.');
            }
            
            // Update PIN
            $hashedPin = hashClientPIN($newPin);
            $stmt = $pdo->prepare("
                UPDATE client_auth 
                SET pin = ?, pin_reset_token = NULL, pin_reset_expires = NULL, login_attempts = 0, locked_until = NULL 
                WHERE customer_id = ?
            ");
            $stmt->execute([$hashedPin, $client['customer_id']]);
            
            // Log the reset
            logClientActivity($pdo, $client['customer_id'], 'pin_reset_completed', 'PIN successfully reset');
            
            // Clear rate limits for successful PIN reset
            recordSuccessfulAttempt($pdo, 'pin_reset');
            
            $success = 'Your PIN has been successfully reset. You can now <a href="/client/login.php?email=' . urlencode($client['email']) . '" class="text-blue-600 hover:text-blue-500 underline">sign in</a> with your new PIN.';
        }
        
    } catch (Exception $e) {
        // Record failed attempt for rate limiting
        recordFailedAttempt($pdo, 'pin_reset');
        
        // Log technical details securely
        logSecureError("PIN reset operation failed", $e->getMessage(), 'ERROR');
        
        // Show generic error to user
        $error = 'An error occurred during PIN reset. Please try again or contact support.';
    }
}

$businessSettings = getBusinessSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot PIN - <?php echo htmlspecialchars($businessSettings['business_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Simple Header for Login -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-center items-center h-16">
                <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($businessSettings['business_name']); ?></h1>
                <span class="ml-3 px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Client Portal</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-gray-900 rounded-lg flex items-center justify-center">
                    <i class="fas fa-key text-white text-2xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    <?php if ($step === 'request'): ?>
                        Forgot Your PIN?
                    <?php else: ?>
                        Reset Your PIN
                    <?php endif; ?>
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    <?php if ($step === 'request'): ?>
                        Enter your email address and we'll send you instructions to reset your PIN
                    <?php else: ?>
                        Enter your new 4-digit PIN below
                    <?php endif; ?>
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <?php if ($error): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                            <span class="text-red-800"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span class="text-green-800"><?php echo $success; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'request' && !$success): ?>
                    <form method="POST" class="space-y-6">
                        <?php echo getCSRFTokenField(); ?>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500">
                        </div>

                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Reset Instructions
                        </button>
                    </form>
                <?php elseif ($step === 'reset' && !$success): ?>
                    <form method="POST" class="space-y-6">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div>
                            <label for="pin" class="block text-sm font-medium text-gray-700">New 4-Digit PIN</label>
                            <input type="password" id="pin" name="pin" required maxlength="4" pattern="[0-9]{4}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500 text-center text-lg tracking-widest">
                        </div>

                        <div>
                            <label for="confirm_pin" class="block text-sm font-medium text-gray-700">Confirm New PIN</label>
                            <input type="password" id="confirm_pin" name="confirm_pin" required maxlength="4" pattern="[0-9]{4}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-500 focus:border-gray-500 text-center text-lg tracking-widest">
                        </div>

                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Reset PIN
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="/client/login.php" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($businessSettings['business_name']); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-format PIN inputs
        document.getElementById('pin')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('confirm_pin')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // PIN confirmation validation
        document.getElementById('confirm_pin')?.addEventListener('input', function(e) {
            const pin = document.getElementById('pin').value;
            const confirmPin = this.value;
            
            if (confirmPin && pin !== confirmPin) {
                this.setCustomValidity('PINs do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>