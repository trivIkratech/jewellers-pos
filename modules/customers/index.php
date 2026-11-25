<?php
/**
 * Customer Management - Directory
 * Streamlined, Low Cognitive Load & Perfectly Aligned
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Customer Management';
$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? ''); // balance, all

$sql = "SELECT c.*, COUNT(i.id) as invoice_count 
        FROM customers c 
        LEFT JOIN invoices i ON c.id = i.customer_id AND i.status != 'cancelled' 
        WHERE c.is_active = 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.gstin LIKE ? OR c.city LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term, $term];
}

if ($filter === 'balance') {
    $sql .= " AND c.outstanding_balance > 0";
}

$sql .= " GROUP BY c.id ORDER BY c.outstanding_balance DESC, c.total_purchases DESC, c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$statsStmt = $pdo->query("SELECT 
    COUNT(*) as total_customers, 
    COALESCE(SUM(total_purchases), 0) as total_spent, 
    COALESCE(SUM(outstanding_balance), 0) as total_due,
    SUM(CASE WHEN outstanding_balance > 0 THEN 1 ELSE 0 END) as debtors_count
    FROM customers WHERE is_active = 1");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Helper for clean currency display (avoids visual clutter of trailing .00)
function formatCleanMoney($amount) {
    $val = (float)$amount;
    if (floor($val) == $val) {
        return '₹' . number_format($val, 0);
    }
    return '₹' . number_format($val, 2);
}

// Pastel Palette for Avatars
$avatarPalettes = [
    ['bg' => '#e0e7ff', 'text' => '#4338ca', 'border' => '#c7d2fe'],
    ['bg' => '#fef3c7', 'text' => '#b45309', 'border' => '#fde68a'],
    ['bg' => '#e0f2fe', 'text' => '#0369a1', 'border' => '#bae6fd'],
    ['bg' => '#fce7f3', 'text' => '#be185d', 'border' => '#fbcfe8'],
    ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#bbf7d0'],
    ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'border' => '#e9d5ff'],
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- 1. Top Metrics (Balanced, No Overflow) -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon info">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_customers'] ?? 0); ?></h3>
                <p class="text-muted mb-0">Registered Customers</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon success">
                <i class="bi bi-bag-check"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-dark"><?php echo formatCleanMoney($stats['total_spent'] ?? 0); ?></h3>
                <p class="text-muted mb-0">Total Purchases</p>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon <?php echo ($stats['total_due'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                <i class="bi <?php echo ($stats['total_due'] ?? 0) > 0 ? 'bi-exclamation-triangle' : 'bi-check-circle'; ?>"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 <?php echo ($stats['total_due'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCleanMoney($stats['total_due'] ?? 0); ?>
                </h3>
                <p class="text-muted mb-0">Outstanding Dues</p>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card border-0 shadow-xs h-100">
            <div class="stat-icon warning">
                <i class="bi bi-person-exclamation"></i>
            </div>
            <div class="stat-details">
                <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['debtors_count'] ?? 0); ?> Customers</h3>
                <p class="text-muted mb-0">With Pending Dues</p>
            </div>
        </div>
    </div>
</div>

<!-- 2. Controls & Filter Bar -->
<div class="card mb-4 border-0 shadow-xs">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            
            <!-- Search & Filters -->
            <form method="GET" action="" class="d-flex align-items-center flex-wrap gap-2 flex-grow-1" style="max-width: 650px;">
                <div class="input-group input-group-sm flex-grow-1" style="min-width: 200px; max-width: 320px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Search customer, phone, city..." value="<?php echo sanitize($search); ?>">
                </div>

                <select class="form-select form-select-sm" name="filter" onchange="this.form.submit()" style="width: auto; min-width: 170px;">
                    <option value="">All Customers</option>
                    <option value="balance" <?php echo $filter === 'balance' ? 'selected' : ''; ?>>Pending Balance Only</option>
                </select>

                <?php if ($search !== '' || $filter !== ''): ?>
                <a href="<?php echo url('modules/customers/index.php'); ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </a>
                <?php endif; ?>
            </form>

            <!-- Add Customer Button (Never Clipped) -->
            <div class="d-flex align-items-center gap-2">
                <a href="<?php echo url('modules/customers/add.php'); ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold text-nowrap d-inline-flex align-items-center gap-1 shadow-xs" style="white-space: nowrap !important; height: 34px;">
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Add New Customer</span>
                </a>
            </div>

        </div>
    </div>
</div>

<!-- 3. Customers Directory Table (Clean 6-Column Hierarchy, Zero Horizontal Overflow) -->
<div class="card border-0 shadow-xs">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="width: 100%; table-layout: fixed;">
                <thead class="table-light border-bottom text-secondary fs-xs text-uppercase tracking-wider">
                    <tr>
                        <th style="width: 32%; padding-left: 16px;">Customer Profile</th>
                        <th style="width: 17%;">Contact & City</th>
                        <th style="width: 11%;" class="text-center">Invoices</th>
                        <th style="width: 14%;" class="text-end">Total Purchases</th>
                        <th style="width: 13%;" class="text-end">Balance Due</th>
                        <th style="width: 13%; padding-right: 16px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <div class="empty-state py-3">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border mb-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-people text-muted fs-3"></i>
                                </div>
                                <h6 class="fw-bold text-dark">No customer records found</h6>
                                <p class="text-muted fs-sm mb-3">Add regular jewellery clients to track purchase history, bills, and outstanding ledgers.</p>
                                <a href="<?php echo url('modules/customers/add.php'); ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-person-plus-fill me-1"></i> Add First Customer
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($customers as $c): 
                        $hasDue = (float)$c['outstanding_balance'] > 0;
                        $paletteIdx = abs(crc32($c['name'])) % count($avatarPalettes);
                        $pal = $avatarPalettes[$paletteIdx];
                        $initials = strtoupper(substr($c['name'], 0, 1));
                    ?>
                    <tr>
                        <!-- 1. Customer Profile -->
                        <td style="padding-left: 16px; overflow: hidden;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-2xs flex-shrink-0" 
                                     style="width: 36px; height: 36px; font-size: 14px; background-color: <?php echo $pal['bg']; ?>; color: <?php echo $pal['text']; ?>; border: 1px solid <?php echo $pal['border']; ?>;">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="min-w-0 flex-grow-1" style="overflow: hidden;">
                                    <a href="<?php echo url('modules/customers/view.php?id=' . $c['id']); ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate fs-6 hover-primary">
                                        <?php echo sanitize($c['name']); ?>
                                    </a>
                                    <div class="text-truncate fs-xs text-muted">
                                        <?php if ($c['gstin']): ?>
                                        <span class="badge bg-light text-secondary border font-monospace me-1" style="font-size: 9px; vertical-align: middle;">GST: <?php echo sanitize($c['gstin']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($c['email']): ?>
                                        <span class="text-muted"><?php echo sanitize($c['email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- 2. Contact & City -->
                        <td style="overflow: hidden;">
                            <a href="tel:<?php echo sanitize($c['phone']); ?>" class="text-dark text-decoration-none fw-semibold font-monospace fs-sm d-block text-truncate">
                                <?php echo sanitize($c['phone']); ?>
                            </a>
                            <div class="text-muted fs-xs text-truncate">
                                <i class="bi bi-geo-alt me-1 text-secondary"></i><?php echo sanitize($c['city'] ?: ($c['address'] ? substr($c['address'], 0, 18) . '...' : 'Walk-in')); ?>
                            </div>
                        </td>

                        <!-- 3. Invoices Count -->
                        <td class="text-center">
                            <?php if ($c['invoice_count'] > 0): ?>
                            <span class="badge bg-light text-dark border px-2 py-1 fw-semibold font-monospace">
                                <i class="bi bi-receipt text-primary me-1"></i><?php echo $c['invoice_count']; ?> <?php echo $c['invoice_count'] == 1 ? 'bill' : 'bills'; ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted fs-xs">0 bills</span>
                            <?php endif; ?>
                        </td>

                        <!-- 4. Total Purchases -->
                        <td class="text-end">
                            <span class="fw-bold text-dark fs-6"><?php echo formatCleanMoney($c['total_purchases']); ?></span>
                        </td>

                        <!-- 5. Outstanding Balance Due -->
                        <td class="text-end">
                            <?php if ($hasDue): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fw-bold fs-xs">
                                <i class="bi bi-exclamation-circle-fill me-1"></i><?php echo formatCleanMoney($c['outstanding_balance']); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-semibold fs-xs">
                                <i class="bi bi-check2 me-1"></i>Cleared
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- 6. Actions (Pill View + Dropdown) -->
                        <td class="text-end" style="padding-right: 16px;">
                            <div class="d-inline-flex align-items-center gap-1">
                                <a href="<?php echo url('modules/customers/view.php?id=' . $c['id']); ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fw-semibold text-nowrap" title="View Customer Ledger & History">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                
                                <div class="dropdown">
                                    <button class="btn btn-xs btn-light border rounded-circle p-0 d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" style="width: 26px; height: 26px;" title="More options">
                                        <i class="bi bi-three-dots-vertical fs-xs"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-sm">
                                        <li>
                                            <a class="dropdown-item" href="<?php echo url('modules/customers/view.php?id=' . $c['id']); ?>">
                                                <i class="bi bi-file-earmark-text text-primary me-2"></i> Purchase History
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo url('modules/billing/index.php?customer_id=' . $c['id']); ?>">
                                                <i class="bi bi-cart-plus text-success me-2"></i> Create New Bill
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo url('modules/customers/edit.php?id=' . $c['id']); ?>">
                                                <i class="bi bi-pencil text-secondary me-2"></i> Edit Details
                                            </a>
                                        </li>
                                        <?php if (isAdmin()): ?>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="<?php echo url('modules/customers/delete.php?id=' . $c['id']); ?>" onclick="return confirm('Delete customer <?php echo addslashes($c['name']); ?>?');">
                                                <i class="bi bi-trash me-2"></i> Delete Customer
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.hover-primary:hover {
    color: var(--primary) !important;
}
.shadow-xs {
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.shadow-2xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.table td, .table th {
    vertical-align: middle;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
