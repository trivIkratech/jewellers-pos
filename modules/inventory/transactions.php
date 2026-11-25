<?php
/**
 * Inventory Transactions / Movement History
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Stock Movement History';
$pdo = getDBConnection();

$productId = !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$typeFilter = trim($_GET['type'] ?? '');

$sql = "SELECT it.*, p.name as product_name, p.product_code, p.purity, u.username as user_name 
        FROM inventory_transactions it 
        JOIN products p ON it.product_id = p.id 
        LEFT JOIN users u ON it.created_by = u.id 
        WHERE 1=1";
$params = [];

if ($productId) {
    $sql .= " AND it.product_id = ?";
    $params[] = $productId;
}

if ($typeFilter !== '') {
    $sql .= " AND it.transaction_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY it.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Product list for filter
$prodListStmt = $pdo->query("SELECT id, name, product_code FROM products WHERE is_active = 1 ORDER BY name ASC");
$allProducts = $prodListStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Stock Movement History</h4>
        <p class="text-muted fs-sm mb-0">Complete audit log of inventory ins, outs, sales deductions, returns, and adjustments</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i> Inventory List
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <select class="form-select form-select-sm" name="product_id" onchange="this.form.submit()">
                    <option value="">All Products</option>
                    <?php foreach ($allProducts as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $productId == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($p['product_code']); ?> - <?php echo sanitize($p['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="">All Movement Types</option>
                    <option value="sale" <?php echo $typeFilter === 'sale' ? 'selected' : ''; ?>>Sale (POS Invoiced)</option>
                    <option value="stock_in" <?php echo $typeFilter === 'stock_in' ? 'selected' : ''; ?>>Stock In (New Lot)</option>
                    <option value="stock_out" <?php echo $typeFilter === 'stock_out' ? 'selected' : ''; ?>>Stock Out (Remove)</option>
                    <option value="adjustment" <?php echo $typeFilter === 'adjustment' ? 'selected' : ''; ?>>Adjustment (Audit)</option>
                    <option value="cancel_reversal" <?php echo $typeFilter === 'cancel_reversal' ? 'selected' : ''; ?>>Cancel Reversal (Restored)</option>
                </select>
            </div>
            <div class="col-md-3">
                <?php if ($productId || $typeFilter): ?>
                <a href="<?php echo url('modules/inventory/transactions.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x-circle"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>Movement Type</th>
                        <th>Quantity Change</th>
                        <th>Stock (Before &rarr; After)</th>
                        <th>Reference / Remarks</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            No stock movement records found for the selected filter.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $t): 
                        $type = $t['transaction_type'];
                        if ($type === 'sale') {
                            $badge = '<span class="badge bg-danger"><i class="bi bi-cart-dash me-1"></i>Sale</span>';
                            $qtyDisplay = '<span class="text-danger fw-bold">-' . $t['quantity'] . '</span>';
                        } elseif ($type === 'stock_in') {
                            $badge = '<span class="badge bg-success"><i class="bi bi-box-arrow-in-down me-1"></i>Stock In</span>';
                            $qtyDisplay = '<span class="text-success fw-bold">+' . $t['quantity'] . '</span>';
                        } elseif ($type === 'stock_out') {
                            $badge = '<span class="badge bg-danger"><i class="bi bi-box-arrow-up me-1"></i>Stock Out</span>';
                            $qtyDisplay = '<span class="text-danger fw-bold">-' . $t['quantity'] . '</span>';
                        } elseif ($type === 'cancel_reversal') {
                            $badge = '<span class="badge bg-info"><i class="bi bi-arrow-counterclockwise me-1"></i>Reversal</span>';
                            $qtyDisplay = '<span class="text-info fw-bold">+' . $t['quantity'] . '</span>';
                        } else {
                            $badge = '<span class="badge bg-secondary"><i class="bi bi-sliders me-1"></i>Audit Set</span>';
                            $qtyDisplay = '<span class="text-muted fw-bold">' . $t['quantity'] . '</span>';
                        }
                    ?>
                    <tr>
                        <td class="text-muted fs-sm text-nowrap">
                            <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?>
                        </td>
                        <td>
                            <strong class="text-dark d-block"><?php echo sanitize($t['product_name']); ?></strong>
                            <span class="badge bg-light text-dark border"><?php echo sanitize($t['product_code']); ?></span>
                            <small class="text-muted"><?php echo sanitize($t['purity']); ?></small>
                        </td>
                        <td>
                            <?php echo $badge; ?>
                        </td>
                        <td>
                            <?php echo $qtyDisplay; ?>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo $t['previous_stock']; ?></span>
                            <i class="bi bi-arrow-right mx-1 text-muted"></i>
                            <strong class="text-dark"><?php echo $t['new_stock']; ?></strong>
                        </td>
                        <td>
                            <?php echo sanitize($t['notes'] ?: '-'); ?>
                        </td>
                        <td class="text-muted fs-sm">
                            <i class="bi bi-person me-1"></i><?php echo sanitize($t['user_name'] ?: 'System'); ?>
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

