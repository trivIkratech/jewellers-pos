<?php
/**
 * Other Revenue & Income Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Other Revenue & Income';
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_revenue') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $source = trim($_POST['source'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $revDate = !empty($_POST['revenue_date']) ? $_POST['revenue_date'] : date('Y-m-d');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $refNumber = trim($_POST['reference_number'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isAdvance = !empty($_POST['is_advance']) ? 1 : 0;
        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        if (empty($source) || $amount <= 0) {
            $error = 'Please enter revenue source and amount.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO revenue (source, amount, revenue_date, payment_method, reference_number, description, is_advance, customer_id, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$source, $amount, $revDate, $paymentMethod, $refNumber, $description, $isAdvance, $customerId, getCurrentUserId()]);

            logAudit(getCurrentUserId(), 'create', 'revenue', $pdo->lastInsertId(), null, ['amount' => $amount, 'source' => $source]);
            redirect(url('modules/finance/revenue.php'), 'Revenue income entry recorded!');
        }
    }
}

// Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM revenue WHERE id = ?")->execute([$delId]);
    logAudit(getCurrentUserId(), 'delete', 'revenue', $delId);
    redirect(url('modules/finance/revenue.php'), 'Revenue entry removed.');
}

// Fetch all other revenue entries
$sql = "SELECT r.*, c.name as customer_name, u.username as created_by_user 
        FROM revenue r 
        LEFT JOIN customers c ON r.customer_id = c.id 
        LEFT JOIN users u ON r.created_by = u.id 
        ORDER BY r.revenue_date DESC, r.id DESC";
$revenues = $pdo->query($sql)->fetchAll();

// Customers for advance link
$customers = $pdo->query("SELECT id, name, phone FROM customers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Other Revenue & Income</h4>
        <p class="text-muted fs-sm mb-0">Record non-sales income: order booking advances, jewellery repair fees, old gold melting margins, and appraisal charges</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/finance/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-graph-up me-1"></i> P&L Statement
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRevenueModal">
            <i class="bi bi-plus-lg me-1"></i> Add Revenue Entry
        </button>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
</div>
<?php endif; ?>

<!-- Revenue Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Income Source</th>
                        <th>Linked Customer</th>
                        <th>Payment Mode</th>
                        <th>Ref #</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($revenues)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            No other revenue entries recorded.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($revenues as $r): ?>
                    <tr>
                        <td class="text-muted fs-sm text-nowrap">
                            <?php echo date('d M Y', strtotime($r['revenue_date'])); ?>
                        </td>
                        <td>
                            <strong class="text-dark d-block"><?php echo sanitize($r['source']); ?></strong>
                            <?php if ($r['is_advance']): ?>
                            <span class="badge bg-warning text-dark fs-xs">Order Advance</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['customer_name']): ?>
                            <span class="text-primary"><i class="bi bi-person me-1"></i><?php echo sanitize($r['customer_name']); ?></span>
                            <?php else: ?>
                            <span class="text-muted fs-xs">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?php echo strtoupper($r['payment_method']); ?></span>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($r['reference_number'] ?: '-'); ?>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($r['description'] ?: '-'); ?>
                        </td>
                        <td class="text-end">
                            <strong class="text-success fs-6"><?php echo formatCurrency($r['amount']); ?></strong>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo url('modules/finance/revenue.php?delete=' . $r['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this revenue entry?');">
                                <i class="bi bi-trash"></i>
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

<!-- Add Revenue Modal -->
<div class="modal fade" id="addRevenueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Record Other Revenue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="add_revenue">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Revenue Source / Head <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="source" required placeholder="e.g. Order Booking Advance, Repair Service, Scrap Margin">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold text-success" name="amount" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="revenue_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI / Online</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Customer (Optional)</label>
                        <select class="form-select" name="customer_id">
                            <option value="">None / General Counter</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?> (<?php echo sanitize($c['phone']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_advance" id="isAdvance">
                        <label class="form-check-label" for="isAdvance">This is an advance deposit against a custom jewellery order</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction / Reference #</label>
                        <input type="text" class="form-control" name="reference_number" placeholder="Optional reference">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description / Remarks</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="e.g. Advance paid for bridal set delivery next month"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Revenue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

