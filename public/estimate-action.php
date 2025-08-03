<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/estimate-functions.php';

// This file handles estimate approval/rejection from email links
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if (!in_array($action, ['approve', 'reject']) || !$id) {
    die('Invalid request');
}

$estimate = getEstimate($pdo, $id, true);
if (!$estimate || $estimate['status'] !== 'Sent') {
    die('Estimate not available for approval');
}

$estimateSettings = getEstimateSettings($pdo);
if ($estimateSettings['allow_online_approval'] != '1') {
    die('Online approval is not enabled');
}

try {
    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
    
    $stmt = $pdo->prepare("UPDATE estimates SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $estimate['id']]);
    
    // Log activity
    $description = $action === 'approve' 
        ? 'Estimate approved by customer via email link' 
        : 'Estimate rejected by customer via email link';
    logEstimateActivity($pdo, $estimate['id'], $action . 'd', $description);
    
    // Redirect to public view with message
    header("Location: view-estimate.php?id={$estimate['unique_id']}");
    exit;
    
} catch (Exception $e) {
    die('An error occurred. Please try again.');
}