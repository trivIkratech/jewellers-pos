<?php
/**
 * Dashboard Chart Data AJAX Endpoint
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$currentYear = date('Y');

// 1. Monthly Sales (Last 6 or 12 months)
$monthlySales = array_fill(1, 12, 0.0);
$monthlyExpenses = array_fill(1, 12, 0.0);

// Sales by month
$salesStmt = $pdo->prepare("SELECT MONTH(invoice_date) as m, SUM(final_amount) as total 
                            FROM invoices 
                            WHERE YEAR(invoice_date) = ? AND status != 'cancelled' 
                            GROUP BY MONTH(invoice_date)");
$salesStmt->execute([$currentYear]);
while ($row = $salesStmt->fetch()) {
    $monthlySales[(int)$row['m']] = (float)$row['total'];
}

// Expenses by month
$expStmt = $pdo->prepare("SELECT MONTH(expense_date) as m, SUM(amount) as total 
                          FROM expenses 
                          WHERE YEAR(expense_date) = ? 
                          GROUP BY MONTH(expense_date)");
$expStmt->execute([$currentYear]);
while ($row = $expStmt->fetch()) {
    $monthlyExpenses[(int)$row['m']] = (float)$row['total'];
}

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$salesData = [];
$expenseData = [];
for ($i = 1; $i <= 12; $i++) {
    $salesData[] = $monthlySales[$i];
    $expenseData[] = $monthlyExpenses[$i];
}

// 2. Sales by Metal Type (Gold, Silver, Diamond, Platinum, Other)
$metalSalesStmt = $pdo->query("SELECT ii.metal_type, SUM(ii.item_total) as total 
                               FROM invoice_items ii 
                               JOIN invoices inv ON ii.invoice_id = inv.id 
                               WHERE inv.status != 'cancelled' 
                               GROUP BY ii.metal_type");
$metalLabels = [];
$metalTotals = [];
while ($row = $metalSalesStmt->fetch()) {
    $metalLabels[] = ucfirst($row['metal_type'] ?: 'Other');
    $metalTotals[] = (float)$row['total'];
}
if (empty($metalLabels)) {
    $metalLabels = ['Gold', 'Silver', 'Diamond', 'Platinum'];
    $metalTotals = [0, 0, 0, 0];
}

echo json_encode([
    'months' => $months,
    'sales' => $salesData,
    'expenses' => $expenseData,
    'metals' => [
        'labels' => $metalLabels,
        'data' => $metalTotals
    ]
]);

