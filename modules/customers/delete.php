<?php
/**
 * Delete Customer
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$customerId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id, name FROM customers WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect(url('modules/customers/index.php'), 'Customer not found.', 'danger');
}

// Check if customer has invoices
$chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE customer_id = ?");
$chk->execute([$customerId]);
$hasInvoices = ($chk->fetch()['cnt'] ?? 0) > 0;

if ($hasInvoices) {
    // Soft delete / deactivate
    $up = $pdo->prepare("UPDATE customers SET is_active = 0 WHERE id = ?");
    $up->execute([$customerId]);
    logAudit(getCurrentUserId(), 'soft_delete', 'customers', $customerId, ['name' => $customer['name']]);
    redirect(url('modules/customers/index.php'), "Customer {$customer['name']} has past invoices and was safely deactivated.");
} else {
    // Hard delete
    $del = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $del->execute([$customerId]);
    logAudit(getCurrentUserId(), 'delete', 'customers', $customerId, ['name' => $customer['name']]);
    redirect(url('modules/customers/index.php'), "Customer {$customer['name']} removed permanently.");
}

