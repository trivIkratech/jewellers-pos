<?php
/**
 * AJAX Search Products
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$pdo = getDBConnection();

if (empty($query)) {
    // Return top 15 in-stock products
    $stmt = $pdo->query("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.is_active = 1 AND p.stock_quantity > 0 
                         ORDER BY p.name ASC LIMIT 15");
    $products = $stmt->fetchAll();
} else {
    // Search by product_code, barcode, or name
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.is_active = 1 AND (p.product_code LIKE ? OR p.barcode LIKE ? OR p.name LIKE ?) 
                           ORDER BY p.stock_quantity > 0 DESC, p.name ASC LIMIT 20");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $products = $stmt->fetchAll();
}

echo json_encode(['products' => $products]);

