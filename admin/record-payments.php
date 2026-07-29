<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/payment-functions.php';

setSecurityHeaders(true, true);

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    $invoiceIds = $_POST['invoice_ids'] ?? [];
    $method = $_POST['method'] ?? getDefaultPaymentMethod();
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $returnTo = $_POST['return_to'] ?? 'record-payments.php';

    if (!is_array($invoiceIds)) {
        $invoiceIds = [$invoiceIds];
    }

    $allowedReturns = ['record-payments.php', 'invoices.php', 'dashboard.php'];
    if (!in_array($returnTo, $allowedReturns, true)) {
        $returnTo = 'record-payments.php';
    }

    if (!$invoiceIds) {
        setFlashMessage('Select at least one invoice to mark paid.', 'error');
        header('Location: ' . $returnTo);
        exit;
    }

    try {
        $outcome = recordFullPayments($pdo, $invoiceIds, $method, $paymentDate);

        if ($outcome['recorded'] > 0) {
            $noun = $outcome['recorded'] === 1 ? 'invoice' : 'invoices';
            $message = "Marked {$outcome['recorded']} {$noun} paid — " . formatCurrency($outcome['total']) . " recorded as {$method}.";
            if ($outcome['skipped'] > 0) {
                $message .= " {$outcome['skipped']} already had no balance.";
            }
            $_SESSION['success_message'] = $message;
        } else {
            setFlashMessage('Nothing to record — those invoices already have no balance due.', 'error');
        }
    } catch (InvalidArgumentException $e) {
        setFlashMessage($e->getMessage(), 'error');
    } catch (Exception $e) {
        logSecureError('Batch payment recording failed', ['error' => $e->getMessage()]);
        setFlashMessage('Could not record those payments. Nothing was changed.', 'error');
    }

    header('Location: ' . $returnTo);
    exit;
}

$grouped = getOutstandingInvoicesByCustomer($pdo);
$summary = getOutstandingSummary($pdo);
$methods = getPaymentMethods();
$defaultMethod = getDefaultPaymentMethod();
$today = date('Y-m-d');
$businessSettings = getBusinessSettings($pdo);
$appName = !empty($businessSettings['business_name']) && $businessSettings['business_name'] !== 'Your Business Name'
    ? ' - ' . $businessSettings['business_name']
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payments<?php echo htmlspecialchars($appName); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :focus-visible { outline: 3px solid #1d4ed8; outline-offset: 2px; }
        .pay-row:has(input:checked) { background-color: #ecfdf5; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-32">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Record Payments</h2>
            <p class="text-gray-700 mt-1 text-lg">Tick everything that has been paid, then press the green button once.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div role="status" class="mb-6 bg-green-50 border border-green-300 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-700 mr-3" aria-hidden="true"></i>
                    <span class="text-green-900 text-lg"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php displayFlashMessage(); ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-300 p-5">
                <p class="text-sm font-medium text-gray-700">Still owed to you</p>
                <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo formatCurrency($summary['open_balance']); ?></p>
                <p class="text-gray-700 mt-1"><?php echo (int) $summary['open_count']; ?> unpaid invoices</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-300 p-5">
                <p class="text-sm font-medium text-gray-700">Past the due date</p>
                <p class="text-3xl font-bold text-red-700 mt-1"><?php echo formatCurrency($summary['overdue_balance']); ?></p>
                <p class="text-gray-700 mt-1"><?php echo (int) $summary['overdue_count']; ?> overdue invoices</p>
            </div>
        </div>

        <?php if (!$grouped): ?>
        <div class="bg-white rounded-lg border border-gray-300 p-12 text-center">
            <i class="fas fa-check-circle text-5xl text-green-600 mb-4" aria-hidden="true"></i>
            <h3 class="text-2xl font-bold text-gray-900">Everything is paid up</h3>
            <p class="text-gray-700 mt-2 text-lg">No invoices are waiting on payment right now.</p>
            <a href="invoices.php" class="inline-flex items-center mt-6 px-6 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 font-semibold">Back to invoices</a>
        </div>
        <?php else: ?>

        <form method="POST" id="payment-form">
            <?php echo getCSRFTokenField(); ?>
            <input type="hidden" name="return_to" value="record-payments.php">

            <div class="bg-white rounded-lg border border-gray-300 p-6 mb-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="method" class="block text-base font-semibold text-gray-900 mb-2">How were they paid?</label>
                        <select id="method" name="method" class="w-full px-4 py-3 text-lg border border-gray-400 rounded-lg bg-white">
                            <?php foreach ($methods as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $m === $defaultMethod ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="payment_date" class="block text-base font-semibold text-gray-900 mb-2">Date received</label>
                        <input type="date" id="payment_date" name="payment_date" value="<?php echo $today; ?>" max="<?php echo $today; ?>" class="w-full px-4 py-3 text-lg border border-gray-400 rounded-lg">
                    </div>
                </div>
                <p class="text-gray-700 mt-4">Each ticked invoice is recorded as paid in full for the balance shown.</p>
            </div>

            <?php foreach ($grouped as $group): ?>
            <fieldset class="bg-white rounded-lg border border-gray-300 mb-6 overflow-hidden">
                <legend class="sr-only">Unpaid invoices for <?php echo htmlspecialchars($group['customer_name']); ?></legend>
                <div class="bg-gray-100 px-5 py-4 border-b border-gray-300 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($group['customer_name']); ?></h3>
                        <p class="text-gray-700">
                            <?php echo count($group['invoices']); ?> unpaid &middot; <?php echo formatCurrency($group['balance']); ?> owed
                            <?php if ($group['oldest_days_overdue'] > 0): ?>
                            &middot; <span class="text-red-700 font-semibold">oldest <?php echo (int) $group['oldest_days_overdue']; ?> days past due</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <button type="button" class="select-group min-h-[44px] px-5 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700 font-semibold" data-customer="<?php echo (int) $group['customer_id']; ?>">
                        Select all <?php echo count($group['invoices']); ?>
                    </button>
                </div>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($group['invoices'] as $inv): ?>
                    <li class="pay-row">
                        <label class="flex items-center gap-4 px-5 py-4 cursor-pointer min-h-[56px]">
                            <input type="checkbox" name="invoice_ids[]" value="<?php echo (int) $inv['id']; ?>"
                                   data-customer="<?php echo (int) $group['customer_id']; ?>"
                                   data-balance="<?php echo htmlspecialchars(number_format((float) $inv['balance_due'], 2, '.', '')); ?>"
                                   class="pay-check w-6 h-6 rounded border-gray-500 text-green-700">
                            <span class="flex-1">
                                <span class="block text-lg font-semibold text-gray-900"><?php echo formatCurrency($inv['balance_due']); ?></span>
                                <span class="block text-gray-700">
                                    Invoice <?php echo htmlspecialchars($inv['invoice_number']); ?> &middot; dated <?php echo date('M j, Y', strtotime($inv['date'])); ?>
                                    <?php if ((int) $inv['days_overdue'] > 0): ?>
                                    &middot; <span class="text-red-700 font-semibold"><?php echo (int) $inv['days_overdue']; ?> days past due</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <a href="edit-invoice.php?id=<?php echo (int) $inv['id']; ?>" class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center px-3 text-blue-800 hover:bg-blue-50 rounded-lg" aria-label="Open invoice <?php echo htmlspecialchars($inv['invoice_number']); ?>">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                        </label>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </fieldset>
            <?php endforeach; ?>

            <div class="fixed bottom-0 left-0 right-0 bg-white border-t-2 border-gray-300 shadow-lg">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-4">
                    <p role="status" aria-live="polite" class="text-lg text-gray-900" id="running-total">Nothing selected yet.</p>
                    <button type="submit" id="submit-button" disabled
                            class="min-h-[56px] px-8 py-4 bg-green-700 text-white rounded-lg font-bold text-lg hover:bg-green-800 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <i class="fas fa-check mr-2" aria-hidden="true"></i>Mark selected paid
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </main>

    <script>
        (function () {
            const form = document.getElementById('payment-form');
            if (!form) return;

            const checks = Array.from(form.querySelectorAll('.pay-check'));
            const totalEl = document.getElementById('running-total');
            const submitEl = document.getElementById('submit-button');

            function money(value) {
                return '$' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function refresh() {
                const selected = checks.filter(c => c.checked);
                const sum = selected.reduce((acc, c) => acc + parseFloat(c.dataset.balance || '0'), 0);
                submitEl.disabled = selected.length === 0;
                totalEl.textContent = selected.length === 0
                    ? 'Nothing selected yet.'
                    : selected.length + (selected.length === 1 ? ' invoice' : ' invoices') + ' selected — ' + money(sum);
            }

            checks.forEach(c => c.addEventListener('change', refresh));

            form.querySelectorAll('.select-group').forEach(function (button) {
                button.addEventListener('click', function () {
                    const group = checks.filter(c => c.dataset.customer === button.dataset.customer);
                    const turnOn = group.some(c => !c.checked);
                    group.forEach(c => { c.checked = turnOn; });
                    button.textContent = turnOn ? 'Clear these ' + group.length : 'Select all ' + group.length;
                    refresh();
                });
            });

            form.addEventListener('submit', function (event) {
                const selected = checks.filter(c => c.checked);
                const sum = selected.reduce((acc, c) => acc + parseFloat(c.dataset.balance || '0'), 0);
                const noun = selected.length === 1 ? 'invoice' : 'invoices';
                if (!confirm('Mark ' + selected.length + ' ' + noun + ' paid, totalling ' + money(sum) + '?')) {
                    event.preventDefault();
                }
            });

            refresh();
        })();
    </script>
</body>
</html>
