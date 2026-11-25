<?php
/**
 * Delete Product
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$productId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id, name, product_code FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(url('modules/inventory/index.php'), 'Product not found.', 'danger');
}

// Check if referenced in invoices
$invCheck = $pdo->prepare("SELECT COUNT(*) as cnt FROM invoice_items WHERE product_id = ?");
$invCheck->execute([$productId]);
$hasInvoices = ($invCheck->fetch()['cnt'] ?? 0) > 0;

if ($hasInvoices) {
    // Soft delete to protect financial audit trail
    $updateStmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $updateStmt->execute([$productId]);
    logAudit(getCurrentUserId(), 'soft_delete', 'products', $productId, ['name' => $product['name']]);
    redirect(url('modules/inventory/index.php'), "Product {$product['name']} has past invoice records and was safely deactivated.");
} else {
    // Hard delete
    $delStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $delStmt->execute([$productId]);
    logAudit(getCurrentUserId(), 'delete', 'products', $productId, ['name' => $product['name']]);
    redirect(url('modules/inventory/index.php'), "Product {$product['name']} deleted permanently.");
}

