<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set security headers
setSecurityHeaders();

// Check admin authentication
requireAdmin();

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoices.php');
    exit;
}

if (isset($_POST['invoice_id'])) {
    $invoiceId = (int) $_POST['invoice_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, check if the invoice exists and get its details
        $stmt = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        // Delete related payments first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        
        // Delete related invoice_items
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        
        // Finally, delete the invoice
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        
        // Commit transaction
        $pdo->commit();
        
        // Set success message and redirect
        $_SESSION['success_message'] = "Invoice #" . $invoice['invoice_number'] . " has been successfully deleted.";
        header('Location: invoices.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Log the error
        error_log("Error deleting invoice: " . $e->getMessage());
        
        // Set error message and redirect
        $_SESSION['error_message'] = "Failed to delete invoice. Please try again.";
        header('Location: invoices.php');
        exit;
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
    header('Location: invoices.php');
    exit;
}
?>