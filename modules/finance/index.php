<?php
/**
 * Revenue & Expense Management - Financial Overview & P&L
 * Streamlined, Modern Visual Hierarchy & Low Cognitive Load
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Revenue & Expense Management';
$pdo = getDBConnection();

// Date range filters
$period = trim($_GET['period'] ?? 'this_month');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$today = date('Y-m-d');

if ($period === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($period === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($period === 'this_year') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
} elseif ($period === 'custom' && $startDate && $endDate) {
    // Custom dates preserved
} else {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

// 1. Sales Revenue (Finalized Invoices within date range)
$salesSql = "SELECT COALESCE(SUM(final_amount), 0) as total_sales, 
                    COALESCE(SUM(paid_amount), 0) as total_collected,
                    COALESCE(SUM(due_amount), 0) as total_uncollected,
                    COUNT(*) as invoice_count 
             FROM invoices 
             WHERE status != 'cancelled' AND invoice_date BETWEEN ? AND ?";
$salesStmt = $pdo->prepare($salesSql);
$salesStmt->execute([$startDate, $endDate]);
$salesData = $salesStmt->fetch(PDO::FETCH_ASSOC);

$salesRevenue = (float)$salesData['total_sales'];
$collectedSales = (float)$salesData['total_collected'];
$uncollectedDues = (float)$salesData['total_uncollected'];
$totalBills = (int)$salesData['invoice_count'];

// 2. Other Non-Sales Revenue
$otherRevSql = "SELECT COALESCE(SUM(amount), 0) as total_other FROM revenue WHERE revenue_date BETWEEN ? AND ?";
$otherRevStmt = $pdo->prepare($otherRevSql);
$otherRevStmt->execute([$startDate, $endDate]);
$otherRevenue = (float)$otherRevStmt->fetch(PDO::FETCH_ASSOC)['total_other'];

$totalRevenue = $salesRevenue + $otherRevenue;

// 3. Operational Expenses
$expSql = "SELECT COALESCE(SUM(amount), 0) as total_exp, COUNT(*) as exp_count 
           FROM expenses WHERE expense_date BETWEEN ? AND ?";
$expStmt = $pdo->prepare($expSql);
$expStmt->execute([$startDate, $endDate]);
$expData = $expStmt->fetch(PDO::FETCH_ASSOC);
$totalExpenses = (float)$expData['total_exp'];
$expCount = (int)$expData['exp_count'];

// 4. Net Profit / Loss & Margin
$netProfit = $totalRevenue - $totalExpenses;
$profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

// 5. Expense breakdown by category
$expCatSql = "SELECT ec.name as category_name, COALESCE(SUM(e.amount), 0) as total, COUNT(e.id) as count 
              FROM expense_categories ec 
              LEFT JOIN expenses e ON ec.id = e.category_id AND e.expense_date BETWEEN ? AND ? 
              GROUP BY ec.id 
              HAVING total > 0 
              ORDER BY total DESC";
$expCatStmt = $pdo->prepare($expCatSql);
$expCatStmt->execute([$startDate, $endDate]);
$expenseByCategory = $expCatStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Payment methods breakdown from actual payments made in date range
$payMethodSql = "SELECT payment_method, SUM(amount) as total 
                 FROM payments 
                 WHERE payment_date BETWEEN ? AND ? 
                 GROUP BY payment_method 
                 ORDER BY total DESC";
$payMethodStmt = $pdo->prepare($payMethodSql);
$payMethodStmt->execute([$startDate, $endDate]);
$paymentMethods = $payMethodStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCollections = array_sum(array_column($paymentMethods, 'total'));

// Helper for clean currency display (eliminates visual noise of trailing .00)
function formatCleanMoney($amount) {
    $val = (float)$amount;
    if (floor($val) == $val) {
        return '₹' . number_format($val, 0);
    }
    return '₹' . number_format($val, 2);
}

// Payment method metadata helper
function getPaymentMethodMeta($method) {
    $m = strtolower($method);
    return match($m) {
        'upi' => ['name' => 'UPI / QR Scan', 'icon' => 'bi-qr-code-scan', 'color' => '#0d9488', 'bg' => '#ccfbf1'],
        'card' => ['name' => 'Card (POS Machine)', 'icon' => 'bi-credit-card', 'color' => '#2563eb', 'bg' => '#dbeafe'],
        'cash' => ['name' => 'Cash in Counter', 'icon' => 'bi-cash-stack', 'color' => '#16a34a', 'bg' => '#dcfce7'],
        'bank_transfer', 'bank' => ['name' => 'Bank Transfer / NEFT', 'icon' => 'bi-bank', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
        'cheque' => ['name' => 'Cheque Payment', 'icon' => 'bi-card-heading', 'color' => '#ea580c', 'bg' => '#ffedd5'],
        default => ['name' => ucfirst($method), 'icon' => 'bi-wallet2', 'color' => '#64748b', 'bg' => '#f1f5f9']
    };
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- 1. Top Period & Action Header -->
<div class="card mb-4 border-0 shadow-xs">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            
            <!-- Title & Active Period -->
            <div>
                <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2" style="font-size: 1rem;"><i class="bi bi-graph-up"></i></span>
                    Financial Overview & P&L
                </h4>
                <div class="text-muted fs-xs d-flex align-items-center gap-2">
                    <i class="bi bi-calendar3 text-secondary"></i>
                    <span>Period: <strong class="text-dark"><?php echo date('d M Y', strtotime($startDate)); ?></strong> to <strong class="text-dark"><?php echo date('d M Y', strtotime($endDate)); ?></strong></span>
                </div>
            </div>

            <!-- Filters & Actions Group -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                
                <!-- Period Toggle Tabs -->
                <div class="btn-group btn-group-sm bg-light p-1 rounded-pill border">
                    <a href="<?php echo url('modules/finance/index.php?period=today'); ?>" class="btn rounded-pill px-3 py-1 fw-semibold <?php echo $period === 'today' ? 'btn-primary text-white shadow-xs' : 'btn-light text-secondary border-0'; ?>">Today</a>
                    <a href="<?php echo url('modules/finance/index.php?period=this_week'); ?>" class="btn rounded-pill px-3 py-1 fw-semibold <?php echo $period === 'this_week' ? 'btn-primary text-white shadow-xs' : 'btn-light text-secondary border-0'; ?>">This Week</a>
                    <a href="<?php echo url('modules/finance/index.php?period=this_month'); ?>" class="btn rounded-pill px-3 py-1 fw-semibold <?php echo $period === 'this_month' ? 'btn-primary text-white shadow-xs' : 'btn-light text-secondary border-0'; ?>">This Month</a>
                    <a href="<?php echo url('modules/finance/index.php?period=this_year'); ?>" class="btn rounded-pill px-3 py-1 fw-semibold <?php echo $period === 'this_year' ? 'btn-primary text-white shadow-xs' : 'btn-light text-secondary border-0'; ?>">This Year</a>
                </div>

                <!-- Primary Action Buttons -->
                <div class="d-flex gap-2 ms-lg-2">
                    <a href="<?php echo url('modules/finance/expenses.php'); ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs">
                        <i class="bi bi-dash-circle"></i>
                        <span>Record Expense</span>
                    </a>
                    <a href="<?php echo url('modules/finance/revenue.php'); ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs">
                        <i class="bi bi-plus-circle"></i>
                        <span>Other Income</span>
                    </a>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- 2. Main P&L Metrics: 4 Unique, High-Value Stats (Zero Duplication) -->
<div class="row g-3 mb-4">
    
    <!-- 1. Revenue -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon info">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-dark"><?php echo formatCleanMoney($totalRevenue); ?></h3>
                <p class="text-muted mb-0">Total Sales & Revenue</p>
                <small class="text-secondary fs-xs">
                    <?php echo $totalBills; ?> bills <?php if ($otherRevenue > 0): ?>• +<?php echo formatCleanMoney($otherRevenue); ?> other<?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- 2. Operational Expenses -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon danger">
                <i class="bi bi-arrow-down-circle"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-dark"><?php echo formatCleanMoney($totalExpenses); ?></h3>
                <p class="text-muted mb-0">Operating Expenses</p>
                <small class="text-secondary fs-xs"><?php echo $expCount; ?> expense <?php echo $expCount == 1 ? 'entry' : 'entries'; ?></small>
            </div>
        </div>
    </div>

    <!-- 3. Net Profit & Margin -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon <?php echo $netProfit >= 0 ? 'success' : 'danger'; ?>">
                <i class="bi <?php echo $netProfit >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo formatCleanMoney($netProfit); ?>
                </h3>
                <p class="text-muted mb-0">Net Business Profit</p>
                <small class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?> fs-xs fw-semibold">
                    <?php echo $netProfit >= 0 ? '▲ ' : '▼ '; ?><?php echo $profitMargin; ?>% Net Margin
                </small>
            </div>
        </div>
    </div>

    <!-- 4. Uncollected Dues (Critical for Jewellers) -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-warning-emphasis"><?php echo formatCleanMoney($uncollectedDues); ?></h3>
                <p class="text-muted mb-0">Uncollected Dues</p>
                <small class="text-secondary fs-xs">Pending from POS customers</small>
            </div>
        </div>
    </div>

</div>

<!-- 3. Financial Statements Grid -->
<div class="row g-4 mb-4">
    
    <!-- LEFT: Profit & Loss Statement (P&L) -->
    <div class="col-lg-7">
        <div class="card h-100 border-0 shadow-xs">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-spreadsheet-fill text-primary"></i>
                    Profit & Loss Account Statement
                </h6>
                <span class="badge bg-light text-secondary border fs-xs">Accrual Basis</span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" style="width: 100%;">
                        <thead class="table-light border-bottom text-secondary fs-xs text-uppercase tracking-wider">
                            <tr>
                                <th style="padding-left: 20px;">Accounting Particulars</th>
                                <th style="width: 170px; padding-right: 20px;" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                            <!-- A. REVENUE HEADER -->
                            <tr class="bg-light" style="border-top: 1px solid #e2e8f0;">
                                <td colspan="2" style="padding-left: 20px;" class="py-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-xs fw-bold text-uppercase">
                                        <i class="bi bi-plus-lg me-1"></i> A. Operating Revenue
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-left: 32px;" class="text-dark">
                                    Jewellery Sales Invoiced
                                    <small class="text-muted d-block fs-xs"><?php echo $totalBills; ?> finalized tax & retail invoices</small>
                                </td>
                                <td style="padding-right: 20px;" class="text-end fw-semibold text-dark font-monospace fs-sm">
                                    <?php echo formatCleanMoney($salesRevenue); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-left: 32px;" class="text-dark">
                                    Other Operating Income / Advances
                                    <small class="text-muted d-block fs-xs">Miscellaneous store revenue</small>
                                </td>
                                <td style="padding-right: 20px;" class="text-end fw-semibold text-dark font-monospace fs-sm">
                                    <?php echo formatCleanMoney($otherRevenue); ?>
                                </td>
                            </tr>
                            
                            <!-- Revenue Subtotal Row -->
                            <tr style="background-color: #f0fdf4; border-top: 1px solid #bbf7d0; border-bottom: 2px solid #86efac;">
                                <td style="padding-left: 20px;" class="fw-bold text-success py-2">
                                    Total Revenue (A)
                                </td>
                                <td style="padding-right: 20px;" class="text-end fw-bold text-success font-monospace fs-6 py-2">
                                    <?php echo formatCleanMoney($totalRevenue); ?>
                                </td>
                            </tr>

                            <!-- B. EXPENSES HEADER -->
                            <tr class="bg-light" style="border-top: 1px solid #e2e8f0;">
                                <td colspan="2" style="padding-left: 20px;" class="py-2 pt-3">
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-xs fw-bold text-uppercase">
                                        <i class="bi bi-dash-lg me-1"></i> B. Operational Expenses
                                    </span>
                                </td>
                            </tr>

                            <?php if (empty($expenseByCategory)): ?>
                            <tr>
                                <td colspan="2" class="text-center py-3 text-muted fs-sm">No expense entries recorded in this period.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($expenseByCategory as $ec): 
                                $catShare = $totalExpenses > 0 ? round(($ec['total'] / $totalExpenses) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td style="padding-left: 32px;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-dark fw-medium"><?php echo sanitize($ec['category_name']); ?></span>
                                        <span class="text-muted fs-xs"><?php echo $ec['count']; ?> <?php echo $ec['count'] == 1 ? 'entry' : 'entries'; ?> • <?php echo $catShare; ?>%</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 4px; max-width: 200px;">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $catShare; ?>%"></div>
                                    </div>
                                </td>
                                <td style="padding-right: 20px;" class="text-end fw-semibold text-danger font-monospace fs-sm">
                                    - <?php echo formatCleanMoney($ec['total']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Expenses Subtotal Row -->
                            <tr style="background-color: #fef2f2; border-top: 1px solid #fecaca; border-bottom: 2px solid #fca5a5;">
                                <td style="padding-left: 20px;" class="fw-bold text-danger py-2">
                                    Total Operational Expenses (B)
                                </td>
                                <td style="padding-right: 20px;" class="text-end fw-bold text-danger font-monospace fs-6 py-2">
                                    - <?php echo formatCleanMoney($totalExpenses); ?>
                                </td>
                            </tr>

                            <!-- C. NET BOTTOM LINE -->
                            <tr style="background-color: <?php echo $netProfit >= 0 ? '#ecfdf5' : '#fff1f2'; ?>; border-top: 2px solid #0f172a;">
                                <td style="padding-left: 20px;" class="py-3">
                                    <div class="fw-bold fs-6 text-dark text-uppercase">Net Profit / Earnings (A - B)</div>
                                    <small class="text-muted fs-xs">Net store margin after operational deductions</small>
                                </td>
                                <td style="padding-right: 20px;" class="text-end py-3">
                                    <div class="fw-bold font-monospace fs-5 <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCleanMoney($netProfit); ?>
                                    </div>
                                    <span class="badge <?php echo $netProfit >= 0 ? 'bg-success text-white' : 'bg-danger text-white'; ?> fs-xs px-2 py-0">
                                        <?php echo $profitMargin; ?>% Margin
                                    </span>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light py-2 px-3 fs-xs text-muted border-top">
                <i class="bi bi-info-circle me-1"></i>
                Revenue calculated on accrual basis. Customer credit balances and dues are tracked in real-time.
            </div>
        </div>
    </div>

    <!-- RIGHT: Cash Inflow Modes & Realization -->
    <div class="col-lg-5">
        
        <!-- 1. Cash Inflows by Payment Mode -->
        <div class="card mb-4 border-0 shadow-xs">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-credit-card-2-front-fill text-primary"></i>
                    Collections by Payment Mode
                </h6>
                <span class="badge bg-light text-secondary border fs-xs font-monospace"><?php echo formatCleanMoney($totalCollections); ?></span>
            </div>
            
            <div class="card-body p-3">
                <?php if (empty($paymentMethods)): ?>
                <div class="text-center py-4 text-muted fs-sm">
                    <i class="bi bi-wallet2 fs-2 d-block mb-1 text-light"></i>
                    No collections recorded in this period.
                </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($paymentMethods as $pm): 
                        $meta = getPaymentMethodMeta($pm['payment_method']);
                        $sharePct = $totalCollections > 0 ? round(($pm['total'] / $totalCollections) * 100, 1) : 0;
                    ?>
                    <div class="p-2 border rounded-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background-color: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                                    <i class="bi <?php echo $meta['icon']; ?> fs-6"></i>
                                </span>
                                <div>
                                    <div class="fw-bold text-dark fs-sm"><?php echo $meta['name']; ?></div>
                                    <small class="text-muted fs-xs"><?php echo $sharePct; ?>% of total collections</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-dark font-monospace fs-sm"><?php echo formatCleanMoney($pm['total']); ?></div>
                            </div>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar" style="width: <?php echo $sharePct; ?>%; background-color: <?php echo $meta['color']; ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Sales Realization Card (Cash Collected vs Uncollected) -->
        <div class="card border-0 shadow-xs">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-pie-chart-fill text-primary"></i>
                    Sales Realization & Recovery
                </h6>
                <?php 
                $recoveryPct = $salesRevenue > 0 ? round(($collectedSales / $salesRevenue) * 100, 1) : 100;
                ?>
                <span class="badge <?php echo $recoveryPct >= 90 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle'; ?> px-2 py-1 fs-xs fw-bold">
                    <?php echo $recoveryPct; ?>% Realized
                </span>
            </div>
            
            <div class="card-body p-3">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fs-sm">Total Sales Invoiced</span>
                        <strong class="text-dark font-monospace fs-sm"><?php echo formatCleanMoney($salesRevenue); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 text-success">
                        <span class="fs-sm d-flex align-items-center gap-1">
                            <i class="bi bi-check-circle-fill"></i> Collected at Checkout
                        </span>
                        <strong class="font-monospace fs-sm"><?php echo formatCleanMoney($collectedSales); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-danger border-top pt-2">
                        <span class="fs-sm d-flex align-items-center gap-1">
                            <i class="bi bi-hourglass-split"></i> Pending Customer Dues
                        </span>
                        <strong class="font-monospace fs-sm"><?php echo formatCleanMoney($uncollectedDues); ?></strong>
                    </div>
                </div>

                <!-- Visual Stacked Bar -->
                <div class="progress" style="height: 10px; border-radius: 6px;">
                    <div class="progress-bar bg-success" style="width: <?php echo min(100, $recoveryPct); ?>%" title="Collected: <?php echo $recoveryPct; ?>%"></div>
                    <div class="progress-bar bg-danger" style="width: <?php echo max(0, 100 - $recoveryPct); ?>%" title="Pending: <?php echo round(100 - $recoveryPct, 1); ?>%"></div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-2 fs-xs text-muted">
                    <span class="text-success fw-semibold"><i class="bi bi-square-fill me-1" style="font-size: 8px;"></i><?php echo $recoveryPct; ?>% Collected</span>
                    <span class="text-danger fw-semibold"><i class="bi bi-square-fill me-1" style="font-size: 8px;"></i><?php echo round(100 - $recoveryPct, 1); ?>% Due</span>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
