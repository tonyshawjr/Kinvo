<?php

function getServiceCategories()
{
    return ['Lawn Care', 'Repairs', 'Installation', 'Cleaning', 'Materials', 'Other'];
}

function getServices($pdo, $activeOnly = true)
{
    $sql = "SELECT id, name, category, default_price, is_active, times_used FROM services";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY times_used DESC, name ASC";

    return $pdo->query($sql)->fetchAll();
}

function getServicesGrouped($pdo)
{
    $grouped = [];
    foreach (getServices($pdo) as $service) {
        $key = $service['category'] ?: 'Other';
        $grouped[$key][] = $service;
    }

    $order = getServiceCategories();
    uksort($grouped, function ($a, $b) use ($order) {
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });

    return $grouped;
}

function saveService($pdo, $name, $category, $defaultPrice, $id = null)
{
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') {
        throw new InvalidArgumentException('A job name is required.');
    }
    if (mb_strlen($name) > 191) {
        $name = mb_substr($name, 0, 191);
    }

    $category = in_array($category, getServiceCategories(), true) ? $category : 'Other';
    $defaultPrice = round((float) $defaultPrice, 2);
    if ($defaultPrice < 0) {
        throw new InvalidArgumentException('Price cannot be negative.');
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE services SET name = ?, category = ?, default_price = ? WHERE id = ?");
        $stmt->execute([$name, $category, $defaultPrice, (int) $id]);

        return (int) $id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO services (name, category, default_price) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE category = VALUES(category), default_price = VALUES(default_price)
    ");
    $stmt->execute([$name, $category, $defaultPrice]);

    $existing = $pdo->prepare("SELECT id FROM services WHERE name = ?");
    $existing->execute([$name]);

    return (int) $existing->fetchColumn();
}

function rememberServiceUsage($pdo, array $descriptions)
{
    $descriptions = array_filter(array_map(function ($d) {
        return trim(preg_replace('/\s+/', ' ', (string) $d));
    }, $descriptions));

    if (!$descriptions) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE services SET times_used = times_used + 1 WHERE name = ?");
    foreach (array_unique($descriptions) as $description) {
        $stmt->execute([$description]);
    }
}

function setServiceActive($pdo, $id, $isActive)
{
    $stmt = $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?");
    $stmt->execute([$isActive ? 1 : 0, (int) $id]);
}

function getMileageRate($pdo)
{
    $settings = getBusinessSettings($pdo);

    return isset($settings['mileage_rate']) ? (float) $settings['mileage_rate'] : 0.0;
}

function getPropertyMileage($pdo, $propertyId)
{
    if (!$propertyId) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT distance_miles FROM customer_properties WHERE id = ?");
    $stmt->execute([(int) $propertyId]);
    $miles = $stmt->fetchColumn();

    return ($miles === false || $miles === null) ? null : (float) $miles;
}

function buildMileageLine($pdo, $propertyId)
{
    $miles = getPropertyMileage($pdo, $propertyId);
    $rate = getMileageRate($pdo);

    if (!$miles || $miles <= 0 || $rate <= 0) {
        return null;
    }

    $roundTrip = round($miles * 2, 2);

    return [
        'description' => 'Mileage — ' . rtrim(rtrim(number_format($roundTrip, 2), '0'), '.') . ' miles round trip',
        'quantity' => $roundTrip,
        'unit_price' => $rate,
        'total' => round($roundTrip * $rate, 2),
    ];
}

function getLastInvoiceForProperty($pdo, $customerId, $propertyId = null)
{
    if ($propertyId) {
        $stmt = $pdo->prepare("
            SELECT * FROM invoices
            WHERE customer_id = ? AND property_id = ?
            ORDER BY date DESC, id DESC LIMIT 1
        ");
        $stmt->execute([(int) $customerId, (int) $propertyId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM invoices
            WHERE customer_id = ?
            ORDER BY date DESC, id DESC LIMIT 1
        ");
        $stmt->execute([(int) $customerId]);
    }

    $invoice = $stmt->fetch();
    if (!$invoice) {
        return null;
    }

    $items = $pdo->prepare("SELECT description, quantity, unit_price, total FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $items->execute([$invoice['id']]);
    $invoice['items'] = $items->fetchAll();

    return $invoice;
}
