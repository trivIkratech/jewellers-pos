<?php
/**
 * AJAX Customer Search & Quick Add
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

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'search';

if ($action === 'lookup_phone') {
    $phone = trim($_GET['phone'] ?? '');
    $digitsOnly = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digitsOnly) < 4) {
        echo json_encode(['found' => false]);
        exit;
    }
    // Search by exact phone, or ending with the digits
    $stmt = $pdo->prepare("SELECT id, name, phone, email, address, gstin, city, outstanding_balance, total_purchases 
                           FROM customers 
                           WHERE phone = ? OR phone LIKE ? 
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$phone, '%' . $digitsOnly]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cust) {
        echo json_encode(['found' => true, 'customer' => $cust]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

if ($action === 'quick_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Customer name and phone number are required.']);
        exit;
    }
    
    // Check if customer with phone already exists
    $stmt = $pdo->prepare("SELECT id, name, phone, gstin, address, outstanding_balance FROM customers WHERE phone = ?");
    $stmt->execute([$phone]);
    $existing = $stmt->fetch();
    if ($existing) {
        echo json_encode(['success' => true, 'customer' => $existing, 'message' => 'Existing customer selected.']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, city, state, pincode, gstin) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $address, $city, $state, $pincode, $gstin]);
    $customerId = $pdo->lastInsertId();
    
    logAudit(getCurrentUserId(), 'create', 'customers', $customerId, null, ['name' => $name, 'phone' => $phone]);
    
    $customer = [
        'id' => $customerId,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'gstin' => $gstin,
        'outstanding_balance' => 0.00
    ];
    
    echo json_encode(['success' => true, 'customer' => $customer, 'message' => 'Customer added successfully.']);
    exit;
}

// Search
$query = trim($_GET['q'] ?? '');
if (empty($query)) {
    $stmt = $pdo->query("SELECT id, name, phone, email, address, gstin, outstanding_balance 
                         FROM customers WHERE is_active = 1 ORDER BY name ASC LIMIT 10");
    $customers = $stmt->fetchAll();
} else {
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare("SELECT id, name, phone, email, address, gstin, outstanding_balance 
                           FROM customers 
                           WHERE is_active = 1 AND (name LIKE ? OR phone LIKE ? OR gstin LIKE ?) 
                           ORDER BY name ASC LIMIT 15");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $customers = $stmt->fetchAll();
}

echo json_encode(['customers' => $customers]);

