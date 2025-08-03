<?php
/**
 * Estimate Helper Functions
 * 
 * Functions for managing estimates throughout the application
 */

/**
 * Generate the next estimate number
 */
function generateEstimateNumber($pdo) {
    try {
        // Get the prefix from settings
        $stmt = $pdo->prepare("SELECT setting_value FROM estimate_settings WHERE setting_key = 'number_prefix'");
        $stmt->execute();
        $prefix = $stmt->fetchColumn() ?: 'EST';
        
        // Format: PREFIX-YYYYMM##
        $yearMonth = date('Ym');
        $pattern = $prefix . '-' . $yearMonth . '%';
        
        // Find the highest estimate number for this month
        $stmt = $pdo->prepare("
            SELECT estimate_number 
            FROM estimates 
            WHERE estimate_number LIKE ? 
            ORDER BY estimate_number DESC 
            LIMIT 1
        ");
        $stmt->execute([$pattern]);
        $lastNumber = $stmt->fetchColumn();
        
        if ($lastNumber) {
            // Extract the sequential number and increment
            $parts = explode($yearMonth, $lastNumber);
            $sequence = isset($parts[1]) ? intval($parts[1]) + 1 : 1;
        } else {
            $sequence = 1;
        }
        
        // Format with leading zeros
        return $prefix . '-' . $yearMonth . str_pad($sequence, 2, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        error_log("Error generating estimate number: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate unique ID for public estimate viewing
 */
function generateEstimateUniqueId() {
    return bin2hex(random_bytes(16));
}

/**
 * Calculate estimate totals
 */
function calculateEstimateTotals($pdo, $estimateId) {
    try {
        // Get all line items
        $stmt = $pdo->prepare("
            SELECT SUM(total) as subtotal 
            FROM estimate_items 
            WHERE estimate_id = ?
        ");
        $stmt->execute([$estimateId]);
        $subtotal = $stmt->fetchColumn() ?: 0;
        
        // Get tax rate
        $stmt = $pdo->prepare("
            SELECT tax_rate 
            FROM estimates 
            WHERE id = ?
        ");
        $stmt->execute([$estimateId]);
        $taxRate = $stmt->fetchColumn() ?: 0;
        
        // Calculate tax and total
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = $subtotal + $taxAmount;
        
        // Update estimate
        $stmt = $pdo->prepare("
            UPDATE estimates 
            SET subtotal = ?, tax_amount = ?, total = ? 
            WHERE id = ?
        ");
        $stmt->execute([$subtotal, $taxAmount, $total, $estimateId]);
        
        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating estimate totals: " . $e->getMessage());
        return false;
    }
}

/**
 * Log estimate activity
 */
function logEstimateActivity($pdo, $estimateId, $action, $description = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO estimate_activity (estimate_id, action, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$estimateId, $action, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Error logging estimate activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and update expired estimates
 */
function updateExpiredEstimates($pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE estimates 
            SET status = 'Expired' 
            WHERE status IN ('Draft', 'Sent') 
            AND expires_date < CURDATE()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Error updating expired estimates: " . $e->getMessage());
        return false;
    }
}

/**
 * Get estimate by ID or unique ID
 */
function getEstimate($pdo, $identifier, $byUniqueId = false) {
    try {
        $column = $byUniqueId ? 'unique_id' : 'id';
        
        // Check if customer_properties table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'customer_properties'");
        $hasPropertiesTable = $stmt->rowCount() > 0;
        
        if ($hasPropertiesTable) {
            // Query with property join
            $stmt = $pdo->prepare("
                SELECT e.*, c.name as customer_name, c.email as customer_email,
                       c.phone as customer_phone,
                       cp.property_name as property_name, cp.address as property_address
                FROM estimates e
                LEFT JOIN customers c ON e.customer_id = c.id
                LEFT JOIN customer_properties cp ON e.property_id = cp.id
                WHERE e.$column = ?
            ");
        } else {
            // Query without property join
            $stmt = $pdo->prepare("
                SELECT e.*, c.name as customer_name, c.email as customer_email,
                       c.phone as customer_phone,
                       NULL as property_name, NULL as property_address
                FROM estimates e
                LEFT JOIN customers c ON e.customer_id = c.id
                WHERE e.$column = ?
            ");
        }
        
        $stmt->execute([$identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting estimate: " . $e->getMessage());
        return false;
    }
}

/**
 * Get estimate items
 */
function getEstimateItems($pdo, $estimateId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM estimate_items 
            WHERE estimate_id = ? 
            ORDER BY id
        ");
        $stmt->execute([$estimateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting estimate items: " . $e->getMessage());
        return [];
    }
}

/**
 * Can estimate be edited?
 */
function canEditEstimate($estimate) {
    return $estimate['status'] === 'Draft';
}

/**
 * Can estimate be converted to invoice?
 */
function canConvertEstimate($estimate) {
    return $estimate['status'] === 'Approved' && empty($estimate['converted_invoice_id']);
}

/**
 * Get estimate settings
 */
function getEstimateSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM estimate_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting estimate settings: " . $e->getMessage());
        return [
            'default_expiration' => '30',
            'email_by_default' => '0',
            'number_prefix' => 'EST',
            'allow_online_approval' => '1',
            'auto_convert_on_approval' => '0'
        ];
    }
}

/**
 * Format estimate status for display
 */
function formatEstimateStatus($status) {
    $statusClasses = [
        'Draft' => 'bg-gray-100 text-gray-800',
        'Sent' => 'bg-blue-100 text-blue-800',
        'Approved' => 'bg-green-100 text-green-800',
        'Rejected' => 'bg-red-100 text-red-800',
        'Expired' => 'bg-yellow-100 text-yellow-800'
    ];
    
    $class = isset($statusClasses[$status]) ? $statusClasses[$status] : 'bg-gray-100 text-gray-800';
    return '<span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full ' . $class . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Complete removal of estimates feature
 */
function removeEstimatesFeature($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Drop tables in correct order (dependencies first)
        $pdo->exec("DROP TABLE IF EXISTS estimate_activity");
        $pdo->exec("DROP TABLE IF EXISTS estimate_items");
        $pdo->exec("DROP TABLE IF EXISTS estimates");
        $pdo->exec("DROP TABLE IF EXISTS estimate_settings");
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error removing estimates feature: " . $e->getMessage());
        return false;
    }
}