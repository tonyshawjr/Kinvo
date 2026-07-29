<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/service-functions.php';

setSecurityHeaders(true, true);

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            saveService($pdo, $_POST['name'] ?? '', $_POST['category'] ?? 'Other', $_POST['default_price'] ?? 0, $_POST['id'] ?? null);
            $_SESSION['success_message'] = 'Saved.';
        } elseif ($action === 'remove' && !empty($_POST['id'])) {
            setServiceActive($pdo, $_POST['id'], false);
            $_SESSION['success_message'] = 'Removed from the list. Invoices already sent are not affected.';
        }
    } catch (InvalidArgumentException $e) {
        setFlashMessage($e->getMessage(), 'error');
    } catch (Exception $e) {
        logSecureError('Service save failed', ['error' => $e->getMessage()]);
        setFlashMessage('Could not save that. Nothing was changed.', 'error');
    }

    header('Location: services.php');
    exit;
}

$grouped = getServicesGrouped($pdo);
$categories = getServiceCategories();
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
    <title>Saved Jobs<?php echo htmlspecialchars($appName); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :focus-visible { outline: 3px solid #1d4ed8; outline-offset: 2px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900">Saved Jobs</h2>
            <p class="text-gray-700 mt-1 text-lg">The jobs you can tap when making an invoice. The amount here is just the starting price &mdash; you can change it on any invoice without changing it here.</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div role="status" class="mb-6 bg-green-50 border border-green-300 rounded-lg p-4">
            <span class="text-green-900 text-lg"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php displayFlashMessage(); ?>

        <section aria-labelledby="add-job-heading" class="bg-white rounded-lg border border-gray-300 p-6 mb-8">
            <h3 id="add-job-heading" class="text-xl font-bold text-gray-900 mb-4">Add a job</h3>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
                <?php echo getCSRFTokenField(); ?>
                <input type="hidden" name="action" value="save">
                <div class="sm:col-span-6">
                    <label for="new-name" class="block text-base font-semibold text-gray-900 mb-2">What is it called?</label>
                    <input type="text" id="new-name" name="name" required maxlength="191" placeholder="Cut &amp; trim grass" class="w-full px-4 py-3 text-lg border border-gray-400 rounded-lg">
                </div>
                <div class="sm:col-span-3">
                    <label for="new-category" class="block text-base font-semibold text-gray-900 mb-2">Group</label>
                    <select id="new-category" name="category" class="w-full px-4 py-3 text-lg border border-gray-400 rounded-lg bg-white">
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label for="new-price" class="block text-base font-semibold text-gray-900 mb-2">Usual amount</label>
                    <div class="flex gap-2">
                        <input type="number" id="new-price" name="default_price" step="0.01" min="0" value="0.00" class="w-full px-4 py-3 text-lg border border-gray-400 rounded-lg">
                        <button type="submit" class="min-h-[44px] px-5 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800">Add</button>
                    </div>
                </div>
            </form>
        </section>

        <?php if (!$grouped): ?>
        <div class="bg-white rounded-lg border border-gray-300 p-10 text-center">
            <p class="text-lg text-gray-700">No saved jobs yet. Add your first one above.</p>
        </div>
        <?php else: ?>
        <?php foreach ($grouped as $category => $services): ?>
        <section aria-labelledby="cat-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $category))); ?>" class="bg-white rounded-lg border border-gray-300 mb-6 overflow-hidden">
            <h3 id="cat-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $category))); ?>" class="bg-gray-100 px-5 py-4 border-b border-gray-300 text-xl font-bold text-gray-900">
                <?php echo htmlspecialchars($category); ?>
            </h3>
            <ul class="divide-y divide-gray-200">
                <?php foreach ($services as $service): ?>
                <li class="px-5 py-4">
                    <form method="POST" class="flex flex-wrap items-end gap-3">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?php echo (int) $service['id']; ?>">
                        <div class="flex-1 min-w-[200px]">
                            <label for="name-<?php echo (int) $service['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Job</label>
                            <input type="text" id="name-<?php echo (int) $service['id']; ?>" name="name" value="<?php echo htmlspecialchars($service['name']); ?>" maxlength="191" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <div class="w-40">
                            <label for="cat-sel-<?php echo (int) $service['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                            <select id="cat-sel-<?php echo (int) $service['id']; ?>" name="category" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base bg-white">
                                <?php foreach ($categories as $option): ?>
                                <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $service['category'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-32">
                            <label for="price-<?php echo (int) $service['id']; ?>" class="block text-sm font-medium text-gray-700 mb-1">Usual amount</label>
                            <input type="number" id="price-<?php echo (int) $service['id']; ?>" name="default_price" step="0.01" min="0" value="<?php echo number_format((float) $service['default_price'], 2, '.', ''); ?>" class="w-full px-3 py-3 border border-gray-400 rounded-lg text-base">
                        </div>
                        <button type="submit" class="min-h-[44px] px-4 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800">Save</button>
                    </form>
                    <form method="POST" class="mt-2" onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($service['name']), ENT_QUOTES); ?> from the list?');">
                        <?php echo getCSRFTokenField(); ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="id" value="<?php echo (int) $service['id']; ?>">
                        <button type="submit" class="min-h-[44px] inline-flex items-center text-gray-700 hover:text-red-700 font-medium">
                            <i class="fas fa-trash mr-2" aria-hidden="true"></i>Remove from list
                        </button>
                        <?php if ($service['times_used'] > 0): ?>
                        <span class="ml-3 text-gray-700">used <?php echo (int) $service['times_used']; ?> time<?php echo $service['times_used'] == 1 ? '' : 's'; ?></span>
                        <?php endif; ?>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
