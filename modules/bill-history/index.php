<?php
/**
 * Bill History Module
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Bill History & Invoices';
$pdo = getDBConnection();

// Filters
$billType = trim($_GET['type'] ?? $_GET['bill_type'] ?? ''); // 'all', 'gst', 'non_gst'
$search = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

if ($billType === 'gst') {
    $pageTitle = 'GST Bills & Tax Invoices';
} elseif ($billType === 'non_gst') {
    $pageTitle = 'Non-GST Bills & Retail Estimates';
}

$sql = "SELECT i.*, COUNT(ii.id) as items_count 
        FROM invoices i 
        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
        WHERE 1=1";
$params = [];

if ($billType === 'gst') {
    $sql .= " AND (i.invoice_type = 'tax_invoice' OR i.total_tax > 0)";
} elseif ($billType === 'non_gst') {
    $sql .= " AND (i.invoice_type = 'estimate' OR (i.total_tax = 0 AND (i.invoice_type IS NULL OR i.invoice_type != 'tax_invoice')))";
}

if ($search !== '') {
    $sql .= " AND (i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($startDate !== '') {
    $sql .= " AND i.invoice_date >= ?";
    $params[] = $startDate;
}

if ($endDate !== '') {
    $sql .= " AND i.invoice_date <= ?";
    $params[] = $endDate;
}

if ($paymentStatus !== '') {
    $sql .= " AND i.payment_status = ?";
    $params[] = $paymentStatus;
}

if ($statusFilter !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY i.id ORDER BY i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Comprehensive Statistics for GST, Non-GST & Totals
$statStmt = $pdo->query("SELECT 
    COUNT(*) as total_bills, 
    COALESCE(SUM(CASE WHEN status != 'cancelled' THEN final_amount ELSE 0 END), 0) as total_amount,
    COALESCE(SUM(CASE WHEN status != 'cancelled' THEN paid_amount ELSE 0 END), 0) as total_paid,
    COALESCE(SUM(CASE WHEN status != 'cancelled' THEN due_amount ELSE 0 END), 0) as total_due,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
    
    SUM(CASE WHEN (invoice_type = 'tax_invoice' OR total_tax > 0) THEN 1 ELSE 0 END) as gst_bills_count,
    COALESCE(SUM(CASE WHEN status != 'cancelled' AND (invoice_type = 'tax_invoice' OR total_tax > 0) THEN final_amount ELSE 0 END), 0) as gst_total_amount,
    COALESCE(SUM(CASE WHEN status != 'cancelled' AND (invoice_type = 'tax_invoice' OR total_tax > 0) THEN total_tax ELSE 0 END), 0) as gst_tax_collected,
    
    SUM(CASE WHEN (invoice_type = 'estimate' OR (total_tax = 0 AND (invoice_type IS NULL OR invoice_type != 'tax_invoice'))) THEN 1 ELSE 0 END) as non_gst_bills_count,
    COALESCE(SUM(CASE WHEN status != 'cancelled' AND (invoice_type = 'estimate' OR (total_tax = 0 AND (invoice_type IS NULL OR invoice_type != 'tax_invoice'))) THEN final_amount ELSE 0 END), 0) as non_gst_total_amount
    FROM invoices");
$stats = $statStmt->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Tabbed Segmented Switcher for GST vs Non-GST Bills -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?php echo url('modules/bill-history/index.php'); ?>" class="btn btn-sm <?php echo empty($billType) ? 'btn-primary' : 'btn-outline-secondary bg-white text-dark'; ?> fw-semibold px-3 py-2 shadow-xs d-flex align-items-center gap-2">
            <i class="bi bi-collection"></i>
            <span>All Bills</span>
            <span class="badge <?php echo empty($billType) ? 'bg-white text-primary' : 'bg-light text-dark border'; ?>"><?php echo number_format($stats['total_bills'] ?? 0); ?></span>
        </a>
        <a href="<?php echo url('modules/bill-history/index.php?type=gst'); ?>" class="btn btn-sm <?php echo $billType === 'gst' ? 'btn-success' : 'btn-outline-secondary bg-white text-dark'; ?> fw-semibold px-3 py-2 shadow-xs d-flex align-items-center gap-2">
            <i class="bi bi-shield-check text-success <?php echo $billType === 'gst' ? 'text-white' : ''; ?>"></i>
            <span>GST Bills (Tax Invoices)</span>
            <span class="badge <?php echo $billType === 'gst' ? 'bg-white text-success' : 'bg-success-subtle text-success border border-success-subtle'; ?>"><?php echo number_format($stats['gst_bills_count'] ?? 0); ?></span>
        </a>
        <a href="<?php echo url('modules/bill-history/index.php?type=non_gst'); ?>" class="btn btn-sm <?php echo $billType === 'non_gst' ? 'btn-secondary' : 'btn-outline-secondary bg-white text-dark'; ?> fw-semibold px-3 py-2 shadow-xs d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text text-secondary <?php echo $billType === 'non_gst' ? 'text-white' : ''; ?>"></i>
            <span>Non-GST Bills (Retail Estimates)</span>
            <span class="badge <?php echo $billType === 'non_gst' ? 'bg-white text-secondary' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"><?php echo number_format($stats['non_gst_bills_count'] ?? 0); ?></span>
        </a>
    </div>

    <!-- Quick Action: Export / Download & Create New Bill -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-dark fw-semibold px-3 py-2 shadow-xs dropdown-toggle d-inline-flex align-items-center gap-1" data-bs-toggle="dropdown">
                <i class="bi bi-download"></i>
                <span>Download / Export</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <h6 class="dropdown-header text-uppercase fs-xs">Export Current Selection (<?php echo $billType === 'gst' ? 'GST Bills' : ($billType === 'non_gst' ? 'Non-GST Bills' : 'All Bills'); ?>)</h6>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?php echo url('modules/bill-history/export.php?format=csv&type=' . ($billType ?: 'all') . ($search ? '&search=' . urlencode($search) : '') . ($startDate ? '&start_date=' . $startDate : '') . ($endDate ? '&end_date=' . $endDate : '')); ?>">
                        <i class="bi bi-file-earmark-spreadsheet text-success"></i>
                        <span>Download Excel / CSV (.csv)</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?php echo url('modules/bill-history/export.php?format=pdf&type=' . ($billType ?: 'all') . ($search ? '&search=' . urlencode($search) : '') . ($startDate ? '&start_date=' . $startDate : '') . ($endDate ? '&end_date=' . $endDate : '')); ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                        <span>Download PDF / Print Report</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <h6 class="dropdown-header text-uppercase fs-xs">Individual Category Exports</h6>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?php echo url('modules/bill-history/export.php?format=csv&type=gst'); ?>">
                        <i class="bi bi-shield-check text-success"></i>
                        <span>Export GST Bills (Tax Invoices)</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?php echo url('modules/bill-history/export.php?format=csv&type=non_gst'); ?>">
                        <i class="bi bi-file-earmark-text text-secondary"></i>
                        <span>Export Non-GST Bills (Estimates)</span>
                    </a>
                </li>
            </ul>
        </div>
        <a href="<?php echo url('modules/billing/index.php?type=' . ($billType === 'non_gst' ? 'non_gst' : 'gst')); ?>" class="btn btn-sm btn-primary fw-semibold px-3 py-2 shadow-xs d-inline-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i>
            <span>New <?php echo $billType === 'non_gst' ? 'Non-GST Bill' : ($billType === 'gst' ? 'GST Bill' : 'Bill'); ?></span>
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <?php if ($billType === 'gst'): ?>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['gst_bills_count'] ?? 0); ?></h3>
                <p>GST Bills Generated</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['gst_total_amount'] ?? 0); ?></h3>
                <p>GST Invoiced Value</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-percent"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['gst_tax_collected'] ?? 0); ?></h3>
                <p>Total GST Tax Collected</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo ($stats['total_due'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo ($stats['total_due'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCurrency($stats['total_due'] ?? 0); ?>
                </h3>
                <p>Pending Receivables Due</p>
            </div>
        </div>
    </div>
    <?php elseif ($billType === 'non_gst'): ?>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon secondary">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['non_gst_bills_count'] ?? 0); ?></h3>
                <p>Non-GST Bills (Estimates)</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['non_gst_total_amount'] ?? 0); ?></h3>
                <p>Non-GST Estimate Sales</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-shield-slash"></i>
            </div>
            <div class="stat-details">
                <h3>₹0.00</h3>
                <p>Tax (Zero GST / Estimate)</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo ($stats['total_due'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo ($stats['total_due'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCurrency($stats['total_due'] ?? 0); ?>
                </h3>
                <p>Pending Receivables Due</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['total_bills'] ?? 0); ?></h3>
                <p>Total Generated Bills</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['gst_total_amount'] ?? 0); ?></h3>
                <p>GST Sales (<?php echo $stats['gst_bills_count']; ?> bills)</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['non_gst_total_amount'] ?? 0); ?></h3>
                <p>Non-GST Sales (<?php echo $stats['non_gst_bills_count']; ?> bills)</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo ($stats['total_due'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo ($stats['total_due'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCurrency($stats['total_due'] ?? 0); ?>
                </h3>
                <p>Pending Receivables Due</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Search & Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Invoice #, Customer, Mobile..." value="<?php echo sanitize($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="" <?php echo empty($billType) ? 'selected' : ''; ?>>All Bill Types</option>
                    <option value="gst" <?php echo $billType === 'gst' ? 'selected' : ''; ?>>🟢 GST Bills Only</option>
                    <option value="non_gst" <?php echo $billType === 'non_gst' ? 'selected' : ''; ?>>📄 Non-GST Bills Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="start_date" placeholder="From date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="end_date" placeholder="To date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="payment_status" onchange="this.form.submit()">
                    <option value="">Payment: All</option>
                    <option value="paid" <?php echo $paymentStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="partial" <?php echo $paymentStatus === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="unpaid" <?php echo $paymentStatus === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                    <i class="bi bi-filter"></i>
                </button>
                <?php if ($search || $startDate || $endDate || $paymentStatus || $statusFilter || $billType): ?>
                <a href="<?php echo url('modules/bill-history/index.php'); ?>" class="btn btn-sm btn-outline-secondary" title="Clear Filters">
                    <i class="bi bi-x-circle"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Bill Amount</th>
                        <th>Paid</th>
                        <th>Due Balance</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt fs-1 text-light d-block mb-2"></i>
                            No invoices found matching your filters.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        $isCancelled = $inv['status'] === 'cancelled';
                    ?>
                    <tr class="<?php echo $isCancelled ? 'bg-light text-muted' : ''; ?>">
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <a href="<?php echo url('modules/bill-history/view.php?id=' . $inv['id']); ?>" class="fw-bold text-primary text-decoration-none">
                                    <?php echo sanitize($inv['invoice_number']); ?>
                                </a>
                                <?php if ($inv['total_tax'] > 0 || $inv['invoice_type'] === 'tax_invoice'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px; padding: 2px 6px;">
                                    <i class="bi bi-shield-check me-1"></i>GST Bill
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle" style="font-size: 10px; padding: 2px 6px;">
                                    <i class="bi bi-file-earmark-text text-secondary me-1"></i>Non-GST
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-muted fs-sm text-nowrap">
                            <?php echo date('d M Y', strtotime($inv['invoice_date'])); ?>
                        </td>
                        <td>
                            <strong class="text-dark d-block"><?php echo sanitize($inv['customer_name'] ?: 'Cash Customer'); ?></strong>
                            <?php if ($inv['customer_phone']): ?>
                            <small class="text-muted"><i class="bi bi-telephone"></i> <?php echo sanitize($inv['customer_phone']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?php echo $inv['items_count']; ?> pcs</span>
                        </td>
                        <td>
                            <strong class="text-dark <?php echo $isCancelled ? 'text-decoration-line-through text-muted' : ''; ?>">
                                <?php echo formatCurrency($inv['final_amount']); ?>
                            </strong>
                        </td>
                        <td>
                            <span class="text-success fw-semibold"><?php echo formatCurrency($inv['paid_amount']); ?></span>
                        </td>
                        <td>
                            <?php if ($inv['due_amount'] > 0 && !$isCancelled): ?>
                            <span class="badge bg-danger">₹<?php echo number_format($inv['due_amount'], 2); ?></span>
                            <?php else: ?>
                            <span class="text-muted fs-xs">₹0.00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $inv['payment_status']; ?>">
                                <?php echo ucfirst($inv['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isCancelled): ?>
                            <span class="badge bg-secondary" title="<?php echo sanitize($inv['cancel_reason']); ?>">Cancelled</span>
                            <?php else: ?>
                            <span class="badge bg-success-light text-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    Options
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end action-dropdown">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/view.php?id=' . $inv['id']); ?>">
                                            <i class="bi bi-eye text-primary"></i> View Invoice
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/print.php?id=' . $inv['id'] . '&download=pdf'); ?>" target="_blank">
                                            <i class="bi bi-file-earmark-pdf text-danger"></i> Download PDF
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/export.php?id=' . $inv['id'] . '&format=csv'); ?>">
                                            <i class="bi bi-file-earmark-spreadsheet text-success"></i> Download Excel/CSV
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/print.php?id=' . $inv['id'] . '&format=a4'); ?>" target="_blank">
                                            <i class="bi bi-printer text-primary"></i> Print A4 Invoice
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/print.php?id=' . $inv['id'] . '&format=thermal'); ?>" target="_blank">
                                            <i class="bi bi-receipt-cutoff text-info"></i> Print Thermal (80mm)
                                        </a>
                                    </li>
                                    <?php if ($inv['due_amount'] > 0 && !$isCancelled): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/bill-history/add_payment.php?id=' . $inv['id']); ?>">
                                            <i class="bi bi-cash-coin text-warning"></i> Record Due Payment
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (!$isCancelled && isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="<?php echo url('modules/bill-history/cancel.php?id=' . $inv['id']); ?>" onclick="return confirm('Cancel invoice <?php echo $inv['invoice_number']; ?>? Stock will be automatically restored.');">
                                            <i class="bi bi-x-circle"></i> Cancel Invoice
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

