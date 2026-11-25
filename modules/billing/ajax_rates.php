<?php
/**
 * AJAX Live Metal Rates
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

$rates = getCurrentMetalRates();
echo json_encode(['rates' => $rates]);

