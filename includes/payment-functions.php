<?php

function getPaymentMethods()
{
    return ['Zelle', 'Cash App', 'Venmo', 'Check', 'Cash', 'Credit Card', 'Bank Transfer', 'PayPal', 'Other'];
}

function getDefaultPaymentMethod()
{
    return 'Zelle';
}

function getInvoiceBalance($pdo, $invoiceId)
{
    $stmt = $pdo->prepare("
        SELECT i.total - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0) AS balance
        FROM invoices i
        WHERE i.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $balance = $stmt->fetchColumn();

    return $balance === false ? null : round((float) $balance, 2);
}

function syncInvoiceStatus($pdo, $invoiceId)
{
    $stmt = $pdo->prepare("
        SELECT i.total, COALESCE(SUM(p.amount), 0) AS total_paid
        FROM invoices i
        LEFT JOIN payments p ON p.invoice_id = i.id
        WHERE i.id = ?
        GROUP BY i.id, i.total
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $status = 'Unpaid';
    if ($row['total_paid'] >= $row['total']) {
        $status = 'Paid';
    } elseif ($row['total_paid'] > 0) {
        $status = 'Partial';
    }

    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$status, $invoiceId]);

    return $status;
}

function recordFullPayments($pdo, array $invoiceIds, $method, $paymentDate, $notes = null)
{
    $result = ['recorded' => 0, 'skipped' => 0, 'total' => 0.00, 'invoices' => []];

    $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
    if (!$invoiceIds) {
        return $result;
    }

    if (!in_array($method, getPaymentMethods(), true)) {
        throw new InvalidArgumentException('Unsupported payment method.');
    }

    $date = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if (!$date || $date->format('Y-m-d') !== $paymentDate) {
        throw new InvalidArgumentException('Invalid payment date.');
    }

    $notes = $notes === null ? null : mb_substr(trim($notes), 0, 500);
    if ($notes === '') {
        $notes = null;
    }

    $insert = $pdo->prepare("
        INSERT INTO payments (invoice_id, method, amount, payment_date, notes)
        VALUES (?, ?, ?, ?, ?)
    ");

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($invoiceIds as $invoiceId) {
            $balance = getInvoiceBalance($pdo, $invoiceId);

            if ($balance === null || $balance <= 0.004) {
                $result['skipped']++;
                continue;
            }

            $balance = round($balance, 2);
            $insert->execute([$invoiceId, $method, $balance, $paymentDate, $notes]);
            syncInvoiceStatus($pdo, $invoiceId);

            $result['recorded']++;
            $result['total'] += $balance;
            $result['invoices'][] = $invoiceId;
        }

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $result['total'] = round($result['total'], 2);

    return $result;
}

function getOutstandingInvoicesByCustomer($pdo)
{
    $stmt = $pdo->query("
        SELECT i.id, i.invoice_number, i.date, i.due_date, i.total,
               c.id AS customer_id, c.name AS customer_name,
               COALESCE(SUM(p.amount), 0) AS total_paid,
               i.total - COALESCE(SUM(p.amount), 0) AS balance_due,
               DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i
        JOIN customers c ON c.id = i.customer_id
        LEFT JOIN payments p ON p.invoice_id = i.id
        GROUP BY i.id
        HAVING balance_due > 0
        ORDER BY c.name ASC, i.due_date ASC
    ");

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['customer_id'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'customer_id' => $row['customer_id'],
                'customer_name' => $row['customer_name'],
                'invoices' => [],
                'balance' => 0.00,
                'oldest_days_overdue' => 0,
            ];
        }
        $grouped[$key]['invoices'][] = $row;
        $grouped[$key]['balance'] += (float) $row['balance_due'];
        $grouped[$key]['oldest_days_overdue'] = max($grouped[$key]['oldest_days_overdue'], (int) $row['days_overdue']);
    }

    uasort($grouped, function ($a, $b) {
        return $b['balance'] <=> $a['balance'];
    });

    return $grouped;
}

function getOutstandingSummary($pdo)
{
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS open_count,
            COALESCE(SUM(balance_due), 0) AS open_balance,
            COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue_count,
            COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN balance_due ELSE 0 END), 0) AS overdue_balance
        FROM (
            SELECT i.id, i.due_date, i.total - COALESCE(SUM(p.amount), 0) AS balance_due
            FROM invoices i
            LEFT JOIN payments p ON p.invoice_id = i.id
            GROUP BY i.id
            HAVING balance_due > 0
        ) open_invoices
    ");

    return $stmt->fetch();
}
