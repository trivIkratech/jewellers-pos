<?php
/**
 * Dashboard Module
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Dashboard';
$pdo = getDBConnection();

$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// 1. Today's Total Sales
$todaySalesStmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) as total, COUNT(*) as count 
                                FROM invoices 
                                WHERE invoice_date = ? AND status != 'cancelled'");
$todaySalesStmt->execute([$today]);
$todaySalesData = $todaySalesStmt->fetch();
$todaySales = (float)$todaySalesData['total'];
$todayBillsCount = (int)$todaySalesData['count'];

// 2. Total Sales Revenue (All time or this month)
$totalRevenueStmt = $pdo->query("SELECT COALESCE(SUM(final_amount), 0) as total FROM invoices WHERE status != 'cancelled'");
$totalRevenue = (float)$totalRevenueStmt->fetch()['total'];

// Other revenue
$otherRevStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM revenue");
$totalOtherRevenue = (float)$otherRevStmt->fetch()['total'];
$grandRevenue = $totalRevenue + $totalOtherRevenue;

// 3. Total Expenses
$totalExpensesStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses");
$totalExpenses = (float)$totalExpensesStmt->fetch()['total'];

// 4. Net Profit (Grand Revenue - Total Expenses)
$netProfit = $grandRevenue - $totalExpenses;

// 5. Total Customers
$customersCountStmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE is_active = 1");
$totalCustomers = (int)$customersCountStmt->fetch()['total'];

// 6. Total Jewellery Items & Total Stock Qty
$productsCountStmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(stock_quantity), 0) as total_qty FROM products WHERE is_active = 1");
$productsData = $productsCountStmt->fetch();
$totalProducts = (int)$productsData['count'];
$totalStockQty = (int)$productsData['total_qty'];

// 7. Low Stock Count & Items
$lowStockStmt = $pdo->query("SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.stock_quantity <= p.min_stock_level AND p.is_active = 1 
                            ORDER BY p.stock_quantity ASC LIMIT 5");
$lowStockItems = $lowStockStmt->fetchAll();
$lowStockCount = count($lowStockItems);

// 8. Recent Invoices
$recentBillsStmt = $pdo->query("SELECT i.*, c.name as cust_name 
                               FROM invoices i 
                               LEFT JOIN customers c ON i.customer_id = c.id 
                               ORDER BY i.created_at DESC LIMIT 6");
$recentBills = $recentBillsStmt->fetchAll();

// 9. Recent Expenses / Payments
$recentExpensesStmt = $pdo->query("SELECT e.*, ec.name as category_name 
                                  FROM expenses e 
                                  LEFT JOIN expense_categories ec ON e.category_id = ec.id 
                                  ORDER BY e.created_at DESC LIMIT 5");
$recentExpenses = $recentExpensesStmt->fetchAll();

// 10. Current Metal Rates
$metalRates = getCurrentMetalRates();

// 11. Live Business Alerts & Notifications for Mobile Feed
$liveAlerts = [];

// 0. Pending Discount Approval Requests (Top Priority Alert)
try {
    $pendingDiscStmt = $pdo->query("SELECT * FROM discount_approvals WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $pendingDiscounts = $pendingDiscStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pendingDiscounts as $pDisc) {
        $discLabel = $pDisc['requested_discount_type'] === 'percentage' 
            ? $pDisc['requested_discount_value'] . '%' 
            : '₹' . number_format($pDisc['requested_discount_value'], 2);
        
        $liveAlerts[] = [
            'id' => $pDisc['id'],
            'is_discount_request' => true,
            'category' => 'Discount Approval',
            'type' => 'warning',
            'icon' => 'bi-percent',
            'title' => 'Discount Request: ' . sanitize($pDisc['customer_name'] ?: 'Customer'),
            'message' => 'Salesperson <strong>' . sanitize($pDisc['salesperson_name']) . '</strong> requested <strong>' . $discLabel . '</strong> (₹' . number_format($pDisc['requested_discount_amount'], 2) . ') on Subtotal ₹' . number_format($pDisc['bill_subtotal'], 2),
            'badge' => 'Approval Needed',
            'badge_class' => 'bg-warning text-dark',
            'link' => '#',
            'action_text' => 'Review / Edit',
            'raw_discount_data' => $pDisc,
            'time' => date('h:i A', strtotime($pDisc['created_at']))
        ];
    }
} catch (Exception $e) {}

// A. Low Stock Alerts
foreach ($lowStockItems as $item) {
    $isOut = (int)$item['stock_quantity'] <= 0;
    $liveAlerts[] = [
        'category' => 'Stock Alert',
        'type' => 'danger',
        'icon' => 'bi-exclamation-triangle-fill',
        'title' => 'Low Stock: ' . sanitize($item['name']),
        'message' => 'Code: <strong>' . sanitize($item['product_code']) . '</strong> | ' . ($isOut ? '<span class="text-danger fw-bold">Out of stock (0 left)</span>' : '<span class="text-danger">' . $item['stock_quantity'] . ' pcs left</span> (Min: ' . $item['min_stock_level'] . ')'),
        'badge' => $isOut ? 'Out of Stock' : 'Low Stock',
        'badge_class' => 'bg-danger text-white',
        'link' => url('modules/inventory/stock_adjust.php?id=' . $item['id']),
        'action_text' => '+ Restock',
        'time' => 'Action required'
    ];
}

// B. Customer Pending Dues Alerts
try {
    $dueCustomersStmt = $pdo->query("SELECT id, name, phone, outstanding_balance FROM customers WHERE outstanding_balance > 0 AND is_active = 1 ORDER BY outstanding_balance DESC LIMIT 3");
    $dueCustomers = $dueCustomersStmt->fetchAll();
    foreach ($dueCustomers as $dueCust) {
        $liveAlerts[] = [
            'category' => 'Payment Due',
            'type' => 'warning',
            'icon' => 'bi-wallet2',
            'title' => 'Pending Balance: ' . sanitize($dueCust['name']),
            'message' => 'Due Amount: <strong class="text-danger">₹' . number_format($dueCust['outstanding_balance'], 2) . '</strong> &bull; ' . sanitize($dueCust['phone']),
            'badge' => 'Balance Due',
            'badge_class' => 'bg-warning text-dark',
            'link' => url('modules/customers/view.php?id=' . $dueCust['id']),
            'action_text' => 'View Due',
            'time' => 'Collect payment'
        ];
    }
} catch (Exception $e) {}

// C. Latest Bill Notification
if (!empty($recentBills)) {
    $latestBill = $recentBills[0];
    $liveAlerts[] = [
        'category' => 'Billing',
        'type' => 'success',
        'icon' => 'bi-receipt-cutoff',
        'title' => 'Invoice ' . sanitize($latestBill['invoice_number']) . ' Generated',
        'message' => 'Total: <strong class="text-success">₹' . number_format($latestBill['final_amount'], 2) . '</strong> | ' . sanitize($latestBill['customer_name'] ?: 'Walk-in Customer'),
        'badge' => 'Recent Sale',
        'badge_class' => 'bg-success text-white',
        'link' => url('modules/bill-history/view.php?id=' . $latestBill['id']),
        'action_text' => 'View Bill',
        'time' => date('d M, h:i A', strtotime($latestBill['created_at']))
    ];
}

// D. Recent Expense Notification
if (!empty($recentExpenses)) {
    $latestExp = $recentExpenses[0];
    $liveAlerts[] = [
        'category' => 'Expense',
        'type' => 'primary',
        'icon' => 'bi-cash-stack',
        'title' => 'Expense: ' . sanitize($latestExp['category_name'] ?: 'Recorded Expense'),
        'message' => 'Amount: <strong class="text-danger">-₹' . number_format($latestExp['amount'], 2) . '</strong> | ' . strtoupper($latestExp['payment_method']),
        'badge' => 'Expense Entry',
        'badge_class' => 'bg-primary text-white',
        'link' => url('modules/finance/expenses.php'),
        'action_text' => 'Manage',
        'time' => date('d M Y', strtotime($latestExp['expense_date']))
    ];
}

// E. Daily Metal Rates Active Alert
$liveAlerts[] = [
    'category' => 'Rates',
    'type' => 'warning',
    'icon' => 'bi-gem',
    'title' => 'Live Metal Rates Active',
    'message' => 'Gold 24K: <strong>₹' . number_format($metalRates['gold_24k'] ?? 0, 2) . '/g</strong> | 22K: ₹' . number_format($metalRates['gold_22k'] ?? 0, 2) . ' | Silver: ₹' . number_format($metalRates['silver_999'] ?? 0, 2),
    'badge' => 'Today Rates',
    'badge_class' => 'bg-warning text-dark',
    'link' => url('modules/settings/metal_rates.php'),
    'action_text' => 'Update',
    'time' => date('d M Y')
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metal Rates Ticker Banner (Desktop: Full Row Ticker) -->
<div class="card mb-4 d-none d-md-block" style="background: linear-gradient(135deg, #213C72 0%, #152549 100%); color: #fff; border: none;">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock-history me-1"></i> Today's Rates</span>
                <span class="text-white-50 fs-sm"><?php echo date('d M Y'); ?></span>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-4 text-nowrap">
                <div>
                    <small class="text-white-50 d-block">Gold 24K (per g)</small>
                    <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_24k'] ?? 0, 2); ?></strong>
                </div>
                <div style="border-left: 1px solid rgba(255,255,255,0.15); height: 28px;"></div>
                <div>
                    <small class="text-white-50 d-block">Gold 22K (per g)</small>
                    <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_22k'] ?? 0, 2); ?></strong>
                </div>
                <div style="border-left: 1px solid rgba(255,255,255,0.15); height: 28px;"></div>
                <div>
                    <small class="text-white-50 d-block">Gold 18K (per g)</small>
                    <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_18k'] ?? 0, 2); ?></strong>
                </div>
                <div style="border-left: 1px solid rgba(255,255,255,0.15); height: 28px;"></div>
                <div>
                    <small class="text-white-50 d-block">Silver 999 (per g)</small>
                    <strong class="text-light fs-6">₹<?php echo number_format($metalRates['silver_999'] ?? 0, 2); ?></strong>
                </div>
                <div style="border-left: 1px solid rgba(255,255,255,0.15); height: 28px;"></div>
                <div>
                    <small class="text-white-50 d-block">Platinum (per g)</small>
                    <strong class="text-light fs-6">₹<?php echo number_format($metalRates['platinum'] ?? 0, 2); ?></strong>
                </div>
            </div>
            <?php if (isAdmin()): ?>
            <a href="<?php echo url('modules/settings/metal_rates.php'); ?>" class="btn btn-sm btn-outline-light">
                <i class="bi bi-pencil-square"></i> Update Rates
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Metal Rates Compact Slidable Carousel (Mobile Only: d-md-none) -->
<div class="mobile-rates-card mb-3 d-md-none">
    <div class="card-body p-2 px-3">
        <!-- Compact Header -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 11px; font-weight: 600;">
                    <i class="bi bi-clock-history me-1"></i> Today's Rates
                </span>
                <span class="text-white-50" style="font-size: 11.5px;"><?php echo date('d M Y'); ?></span>
            </div>
            <?php if (isAdmin()): ?>
            <a href="<?php echo url('modules/settings/metal_rates.php'); ?>" class="btn btn-xs btn-outline-light py-0 px-2" style="font-size: 11px; border-radius: 6px;">
                <i class="bi bi-pencil-square me-1"></i>Update
            </a>
            <?php endif; ?>
        </div>

        <!-- Slidable Carousel -->
        <div id="metalRatesMobileCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3500" data-bs-touch="true">
            <div class="carousel-inner text-center py-1">
                <!-- Slide 1: Gold 24K & Gold 22K -->
                <div class="carousel-item active">
                    <div class="d-flex align-items-center justify-content-around">
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Gold 24K (per g)</span>
                            <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_24k'] ?? 0, 2); ?></strong>
                        </div>
                        <div style="width: 1px; height: 26px; background: rgba(255,255,255,0.2);"></div>
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Gold 22K (per g)</span>
                            <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_22k'] ?? 0, 2); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Slide 2: Gold 18K & Silver 999 -->
                <div class="carousel-item">
                    <div class="d-flex align-items-center justify-content-around">
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Gold 18K (per g)</span>
                            <strong class="text-warning fs-6">₹<?php echo number_format($metalRates['gold_18k'] ?? 0, 2); ?></strong>
                        </div>
                        <div style="width: 1px; height: 26px; background: rgba(255,255,255,0.2);"></div>
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Silver 999 (per g)</span>
                            <strong class="text-light fs-6">₹<?php echo number_format($metalRates['silver_999'] ?? 0, 2); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Slide 3: Platinum & Live Market -->
                <div class="carousel-item">
                    <div class="d-flex align-items-center justify-content-around">
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Platinum (per g)</span>
                            <strong class="text-light fs-6">₹<?php echo number_format($metalRates['platinum'] ?? 0, 2); ?></strong>
                        </div>
                        <div style="width: 1px; height: 26px; background: rgba(255,255,255,0.2);"></div>
                        <div class="flex-fill">
                            <span class="text-white-50 d-block" style="font-size: 11px;">Market Status</span>
                            <span class="text-success fw-bold" style="font-size: 13px;"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Live Today</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carousel Controls & Indicator Dots -->
            <div class="d-flex align-items-center justify-content-between mt-1 pt-1" style="border-top: 1px solid rgba(255,255,255,0.1);">
                <button class="btn btn-link text-white-50 p-0 text-decoration-none" type="button" data-bs-target="#metalRatesMobileCarousel" data-bs-slide="prev" aria-label="Previous Rate" style="font-size: 13px;">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <div class="carousel-indicators position-static m-0 p-0 justify-content-center gap-1" style="z-index: 1;">
                    <button type="button" data-bs-target="#metalRatesMobileCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#metalRatesMobileCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#metalRatesMobileCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <button class="btn btn-link text-white-50 p-0 text-decoration-none" type="button" data-bs-target="#metalRatesMobileCarousel" data-bs-slide="next" aria-label="Next Rate" style="font-size: 13px;">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Buttons -->
<div class="quick-actions">
    <a href="<?php echo url('modules/billing/index.php'); ?>" class="quick-action-btn text-decoration-none">
        <i class="bi bi-plus-circle-fill text-primary"></i>
        <span>Create New Bill</span>
    </a>
    <a href="<?php echo url('modules/inventory/add.php'); ?>" class="quick-action-btn text-decoration-none">
        <i class="bi bi-box-seam text-success"></i>
        <span>Add Jewellery Item</span>
    </a>
    <a href="<?php echo url('modules/customers/add.php'); ?>" class="quick-action-btn text-decoration-none">
        <i class="bi bi-person-plus text-info"></i>
        <span>Add Customer</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="<?php echo url('modules/finance/expenses.php'); ?>" class="quick-action-btn text-decoration-none">
        <i class="bi bi-cash-stack text-danger"></i>
        <span>Record Expense</span>
    </a>
    <?php endif; ?>
</div>

<!-- Mobile Alerts & Notifications Feed (Mobile Only: d-lg-none) -->
<div class="d-lg-none mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3 px-1">
        <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2" style="font-size: 15px;">
            <i class="bi bi-bell-fill text-danger"></i> Alerts & Notifications
        </h6>
        <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 11px;">
            <?php echo count($liveAlerts); ?> Updates
        </span>
    </div>

    <div class="d-flex flex-column gap-2">
        <?php foreach ($liveAlerts as $alert): ?>
        <div class="mobile-alert-card" style="border-left: 4px solid <?php 
            echo $alert['type'] === 'danger' ? '#ef4444' : ($alert['type'] === 'warning' ? '#f59e0b' : ($alert['type'] === 'success' ? '#10b981' : '#3b82f6')); 
        ?> !important;">
            <div class="d-flex align-items-start gap-2 flex-fill min-w-0" style="min-width: 0 !important; overflow: hidden !important;">
                <div class="mobile-alert-icon" style="background: <?php 
                    echo $alert['type'] === 'danger' ? '#fef2f2' : ($alert['type'] === 'warning' ? '#fffbeb' : ($alert['type'] === 'success' ? '#ecfdf5' : '#eff6ff')); 
                ?>; color: <?php 
                    echo $alert['type'] === 'danger' ? '#dc2626' : ($alert['type'] === 'warning' ? '#d97706' : ($alert['type'] === 'success' ? '#059669' : '#2563eb')); 
                ?>;">
                    <i class="bi <?php echo $alert['icon']; ?>"></i>
                </div>
                <div class="mobile-alert-content" style="min-width: 0 !important; overflow: hidden !important;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge <?php echo $alert['badge_class']; ?>" style="font-size: 10px; font-weight: 600; padding: 2px 6px;">
                            <?php echo $alert['badge']; ?>
                        </span>
                        <span class="text-muted" style="font-size: 11px;"><?php echo $alert['time']; ?></span>
                    </div>
                    <div class="fw-semibold text-dark text-truncate mb-1" style="font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo $alert['title']; ?>
                    </div>
                    <div class="text-muted text-truncate" style="font-size: 11px; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo $alert['message']; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($alert['raw_discount_data'])): ?>
            <div class="mobile-alert-action">
                <button type="button" class="btn btn-xs btn-warning text-dark fw-bold text-nowrap px-2 py-1" style="font-size: 11px; border-radius: 6px;" onclick='openAdminDiscountModal(<?php echo json_encode($alert["raw_discount_data"]); ?>)'>
                    <i class="bi bi-pencil-square me-1"></i>Review / Edit
                </button>
            </div>
            <?php elseif (!empty($alert['link'])): ?>
            <div class="mobile-alert-action">
                <a href="<?php echo $alert['link']; ?>" class="btn btn-xs <?php 
                    echo $alert['type'] === 'danger' ? 'btn-outline-danger' : ($alert['type'] === 'warning' ? 'btn-outline-warning text-dark' : ($alert['type'] === 'success' ? 'btn-outline-success' : 'btn-outline-primary')); 
                ?> text-nowrap px-2 py-1" style="font-size: 11px; font-weight: 500; border-radius: 6px;">
                    <?php echo $alert['action_text']; ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="row g-3 mb-4 d-none d-lg-flex hide-on-mobile">
    <!-- Today's Total Sales -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-cart-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($todaySales); ?></h3>
                <p>Today's Sales (<?php echo $todayBillsCount; ?> bills)</p>
            </div>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($grandRevenue); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
    </div>

    <!-- Total Expenses -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="bi bi-arrow-down-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($totalExpenses); ?></h3>
                <p>Total Expenses</p>
            </div>
        </div>
    </div>

    <!-- Net Profit -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo $netProfit >= 0 ? 'success' : 'danger'; ?>">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo formatCurrency($netProfit); ?>
                </h3>
                <p>Net Profit / Balance</p>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Stats Grid (Desktop Only: d-none d-lg-flex hide-on-mobile) -->
<div class="row g-3 mb-4 d-none d-lg-flex hide-on-mobile">
    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($totalCustomers); ?></h3>
                <p>Registered Customers</p>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-gem"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($totalProducts); ?> <small class="fs-sm text-muted">(<?php echo $totalStockQty; ?> pcs)</small></h3>
                <p>Jewellery Inventory Stock</p>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo $lowStockCount > 0 ? 'warning' : 'success'; ?>">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo $lowStockCount > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo number_format($lowStockCount); ?> Items
                </h3>
                <p>Low Stock Alerts</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row (Desktop Only: d-none d-lg-flex hide-on-mobile) -->
<div class="row g-4 mb-4 d-none d-lg-flex hide-on-mobile">
    <!-- Monthly Sales & Expenses Bar Chart (Desktop Only: d-none d-lg-block hide-on-mobile) -->
    <div class="col-lg-8 d-none d-lg-block hide-on-mobile" id="salesExpensesChartCol">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h5 class="card-title mb-0">Sales vs Expenses Trend (<?php echo $currentYear; ?>)</h5>
                    <small class="text-muted">Monthly comparison of business revenue and expenditures</small>
                </div>
            </div>
            <div class="card-body">
                <div style="height: 300px; position: relative;">
                    <canvas id="salesExpensesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales by Metal Type Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Sales Overview by Metal</h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div style="height: 240px; width: 100%; position: relative;">
                    <canvas id="metalSalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row: Recent Bills & Low Stock / Recent Expenses (Desktop Only: d-none d-lg-flex hide-on-mobile) -->
<div class="row g-4 d-none d-lg-flex hide-on-mobile">
    <!-- Recent Bills -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Invoices</h5>
                <a href="<?php echo url('modules/bill-history/index.php'); ?>" class="btn btn-sm btn-outline-primary">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBills)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No invoices generated yet.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentBills as $bill): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo sanitize($bill['invoice_number']); ?></strong>
                                </td>
                                <td>
                                    <?php echo sanitize($bill['customer_name'] ?: 'Cash Customer'); ?>
                                </td>
                                <td class="text-muted fs-sm">
                                    <?php echo date('d M Y', strtotime($bill['invoice_date'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($bill['final_amount']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $pStatus = $bill['payment_status'];
                                    $badgeClass = $pStatus === 'paid' ? 'paid' : ($pStatus === 'partial' ? 'partial' : 'unpaid');
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($pStatus); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo url('modules/bill-history/view.php?id=' . $bill['id']); ?>" class="btn btn-sm btn-outline-secondary" title="View Bill">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo url('modules/bill-history/print.php?id=' . $bill['id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Bill">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert & Expenses -->
    <div class="col-lg-5">
        <!-- Low Stock Alerts -->
        <div class="card mb-4">
            <div class="card-header bg-warning-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <h6 class="mb-0 text-dark fw-bold">Low Stock Warning</h6>
                </div>
                <a href="<?php echo url('modules/inventory/index.php?filter=low_stock'); ?>" class="btn btn-sm btn-outline-secondary">
                    View Stock
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockItems)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">
                                    <i class="bi bi-check-circle text-success me-1"></i> All stock levels are sufficient.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?php echo sanitize($item['product_code']); ?></span></td>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 150px;"><?php echo sanitize($item['name']); ?></div>
                                    <small class="text-muted"><?php echo sanitize($item['purity']); ?> | <?php echo formatWeight($item['net_weight']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-danger"><?php echo $item['stock_quantity']; ?> left</span>
                                </td>
                                <td>
                                    <a href="<?php echo url('modules/inventory/stock_adjust.php?id=' . $item['id']); ?>" class="btn btn-sm btn-outline-primary">
                                        + Add Stock
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Expenses -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold">Recent Expenses</h6>
                <?php if (isAdmin()): ?>
                <a href="<?php echo url('modules/finance/expenses.php'); ?>" class="btn btn-sm btn-outline-secondary">
                    Manage
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table mb-0">
                        <tbody>
                            <?php if (empty($recentExpenses)): ?>
                            <tr>
                                <td class="text-center py-3 text-muted">No recent expenses recorded.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentExpenses as $exp): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo sanitize($exp['category_name'] ?: 'Expense'); ?></div>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($exp['expense_date'])); ?> &bull; <?php echo strtoupper($exp['payment_method']); ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="text-danger fw-bold">-<?php echo formatCurrency($exp['amount']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Discount Approval Modal -->
<div class="modal fade" id="adminDiscountModal" tabindex="-1" aria-labelledby="adminDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 14px;">
            <div class="modal-header bg-light py-2 px-3 border-bottom">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="adminDiscountModalLabel">
                    <i class="bi bi-shield-check text-primary"></i> Review & Approve Discount
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="admModalRequestId" value="">
                <input type="hidden" id="admModalSubtotalRaw" value="0">

                <!-- Request Context Card -->
                <div class="p-2 mb-3 rounded bg-light border">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Salesperson:</small>
                        <strong class="fs-sm text-dark" id="admModalSalesperson">-</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Customer:</small>
                        <strong class="fs-sm text-dark" id="admModalCustomer">-</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Bill Gross Subtotal:</small>
                        <strong class="fs-sm text-primary" id="admModalSubtotal">₹0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Requested Discount:</small>
                        <strong class="fs-sm text-warning" id="admModalRequested">-</strong>
                    </div>
                    <div class="mt-1 pt-1 border-top fs-xs text-muted" id="admModalReasonWrapper" style="display: none;">
                        <span>Reason: </span><span id="admModalReason" class="fst-italic text-dark"></span>
                    </div>
                </div>

                <!-- Admin Edit Section -->
                <div class="mb-3">
                    <label class="form-label fw-bold fs-sm text-dark mb-1">
                        Modify / Set Approved Discount:
                    </label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-xs text-muted mb-1">Discount Type</label>
                            <select class="form-select form-select-sm" id="admModalDiscountType" onchange="recalcAdminDiscountModal()">
                                <option value="fixed">Fixed Amount (₹)</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-xs text-muted mb-1">Discount Value</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="admModalDiscountValue" min="0" oninput="recalcAdminDiscountModal()">
                        </div>
                    </div>
                </div>

                <!-- Dynamic Live Calculation Box -->
                <div class="p-2 rounded border text-center my-2" style="background: #FFFBEB; border-color: #FDE68A !important;">
                    <small class="text-muted d-block fs-xs">Final Approved Discount Deduction</small>
                    <strong class="fs-4 text-danger" id="admModalCalcAmount">-₹0.00</strong>
                    <div class="fs-xs fw-semibold text-dark mt-1" id="admModalNewTotal">New Bill Total: ₹0.00</div>
                </div>

                <!-- Admin Remarks -->
                <div class="mb-2">
                    <label class="form-label fs-xs text-muted mb-1">Admin Remarks / Note (Optional):</label>
                    <input type="text" class="form-control form-control-sm" id="admModalAdminNotes" placeholder="e.g. Approved 3% for customer loyalty concession">
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnRejectDiscount" onclick="submitAdminDiscountDecision('reject')">
                    <i class="bi bi-x-circle me-1"></i> Reject Request
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-success px-3" id="btnApproveDiscount" onclick="submitAdminDiscountDecision('approve')">
                        <i class="bi bi-check-circle-fill me-1"></i> Approve Discount
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openAdminDiscountModal(req) {
    if (!req) return;
    document.getElementById('admModalRequestId').value = req.id;
    document.getElementById('admModalSubtotalRaw').value = req.bill_subtotal;
    document.getElementById('admModalSalesperson').innerText = req.salesperson_name || 'Staff';
    document.getElementById('admModalCustomer').innerText = req.customer_name || 'Walk-in Customer';
    document.getElementById('admModalSubtotal').innerText = '₹' + parseFloat(req.bill_subtotal).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    
    const reqLabel = req.requested_discount_type === 'percentage' 
        ? req.requested_discount_value + '% (₹' + parseFloat(req.requested_discount_amount).toLocaleString('en-IN') + ')' 
        : '₹' + parseFloat(req.requested_discount_value).toLocaleString('en-IN');
    document.getElementById('admModalRequested').innerText = reqLabel;
    
    if (req.reason) {
        document.getElementById('admModalReason').innerText = req.reason;
        document.getElementById('admModalReasonWrapper').style.display = 'block';
    } else {
        document.getElementById('admModalReasonWrapper').style.display = 'none';
    }

    document.getElementById('admModalDiscountType').value = req.requested_discount_type;
    document.getElementById('admModalDiscountValue').value = req.requested_discount_value;
    document.getElementById('admModalAdminNotes').value = '';

    recalcAdminDiscountModal();

    const modalEl = document.getElementById('adminDiscountModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function recalcAdminDiscountModal() {
    const subtotal = parseFloat(document.getElementById('admModalSubtotalRaw').value) || 0;
    const type = document.getElementById('admModalDiscountType').value;
    const val = parseFloat(document.getElementById('admModalDiscountValue').value) || 0;
    
    let amount = 0;
    if (type === 'percentage') {
        amount = (subtotal * val) / 100;
    } else {
        amount = Math.min(subtotal, val);
    }
    
    const newTotal = Math.max(0, subtotal - amount);
    document.getElementById('admModalCalcAmount').innerText = '-₹' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    document.getElementById('admModalNewTotal').innerText = 'New Bill Total: ₹' + newTotal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function submitAdminDiscountDecision(decision) {
    const reqId = document.getElementById('admModalRequestId').value;
    const type = document.getElementById('admModalDiscountType').value;
    const val = document.getElementById('admModalDiscountValue').value;
    const notes = document.getElementById('admModalAdminNotes').value;

    const btn = decision === 'approve' ? document.getElementById('btnApproveDiscount') : document.getElementById('btnRejectDiscount');
    if (btn) btn.disabled = true;

    const formData = new FormData();
    formData.append('request_id', reqId);
    formData.append('decision', decision);
    formData.append('approved_type', type);
    formData.append('approved_value', val);
    formData.append('admin_notes', notes);

    fetch('<?php echo url("modules/billing/ajax_discount_approval.php?action=admin_action"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (btn) btn.disabled = false;
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('adminDiscountModal')).hide();
            alert(res.message);
            window.location.reload();
        } else {
            alert(res.message || 'Error updating discount');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        alert('Network error submitting decision');
    });
}
</script>

<?php
$extraJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Fetch Chart Data
    fetch("' . url('modules/dashboard/ajax/chart_data.php') . '")
        .then(response => response.json())
        .then(data => {
            // 1. Sales vs Expenses Bar Chart
            const ctx1 = document.getElementById("salesExpensesChart").getContext("2d");
            new Chart(ctx1, {
                type: "bar",
                data: {
                    labels: data.months,
                    datasets: [
                        {
                            label: "Sales (₹)",
                            data: data.sales,
                            backgroundColor: "rgba(33, 60, 114, 0.85)",
                            borderRadius: 4
                        },
                        {
                            label: "Expenses (₹)",
                            data: data.expenses,
                            backgroundColor: "rgba(220, 85, 85, 0.85)",
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "top" },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": ₹" + context.raw.toLocaleString("en-IN");
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return "₹" + value.toLocaleString("en-IN");
                                }
                            }
                        }
                    }
                }
            });

            // 2. Sales by Metal Doughnut Chart
            const ctx2 = document.getElementById("metalSalesChart").getContext("2d");
            new Chart(ctx2, {
                type: "doughnut",
                data: {
                    labels: data.metals.labels,
                    datasets: [{
                        data: data.metals.data,
                        backgroundColor: [
                            "#F4B740", // Gold
                            "#9CA3AF", // Silver
                            "#60A5FA", // Diamond
                            "#A78BFA", // Platinum
                            "#34D399"  // Other
                        ],
                        borderWidth: 2,
                        borderColor: "#ffffff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: "bottom" },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ": ₹" + context.raw.toLocaleString("en-IN");
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(err => console.error("Error loading chart data:", err));
});
</script>
';
include __DIR__ . '/../../includes/footer.php';
?>

