<?php
/**
 * Daily Expense Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Expense Management';
$pdo = getDBConnection();
$error = '';

// Handle add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $expenseDate = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $refNumber = trim($_POST['reference_number'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($categoryId <= 0 || $amount <= 0) {
            $error = 'Please select an expense category and enter a valid expense amount.';
        } else {
            $receiptImage = '';
            if (!empty($_FILES['receipt']['name']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (in_array($ext, $allowed)) {
                    $filename = 'rcpt_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['receipt']['tmp_name'], UPLOAD_DIR . $filename)) {
                        $receiptImage = 'uploads/' . $filename;
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO expenses (category_id, amount, expense_date, payment_method, reference_number, description, receipt_image, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$categoryId, $amount, $expenseDate, $paymentMethod, $refNumber, $description, $receiptImage, getCurrentUserId()]);

            logAudit(getCurrentUserId(), 'create', 'expenses', $pdo->lastInsertId(), null, ['amount' => $amount, 'category_id' => $categoryId]);
            redirect(url('modules/finance/expenses.php'), 'Expense recorded successfully!');
        }
    }
}

// Delete expense
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $delStmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    $delStmt->execute([$delId]);
    logAudit(getCurrentUserId(), 'delete', 'expenses', $delId);
    redirect(url('modules/finance/expenses.php'), 'Expense removed.');
}

// Filters
$catFilter = !empty($_GET['category']) ? (int)$_GET['category'] : '';
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

$sql = "SELECT e.*, ec.name as category_name, u.username as created_by_user 
        FROM expenses e 
        JOIN expense_categories ec ON e.category_id = ec.id 
        LEFT JOIN users u ON e.created_by = u.id 
        WHERE e.expense_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($catFilter) {
    $sql .= " AND e.category_id = ?";
    $params[] = $catFilter;
}

$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalPeriodExpense = array_sum(array_column($expenses, 'amount'));

// Load categories for dropdown
$categories = $pdo->query("SELECT id, name FROM expense_categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Daily Expense Management</h4>
        <p class="text-muted fs-sm mb-0">Record shop rent, staff wages, showroom electricity, tools, and operational overheads</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/finance/expense_categories.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-tags me-1"></i> Expense Heads
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-lg me-1"></i> Record New Expense
        </button>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
</div>
<?php endif; ?>

<!-- Filter & Total Summary -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fs-xs fw-semibold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i> From Date
                </label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fs-xs fw-semibold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i> To Date
                </label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fs-xs fw-semibold text-secondary mb-1">
                    <i class="bi bi-tag me-1"></i> Category
                </label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $catFilter == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($c['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Apply Filter
                    </button>
                    <a href="<?php echo url('modules/finance/expenses.php'); ?>" class="btn btn-outline-secondary px-3" title="Clear Filters">
                        Clear
                    </a>
                </div>
            </div>
        </form>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 fs-xs">
                    <i class="bi bi-cash-stack me-1"></i> Period Total
                </span>
                <span class="text-secondary fs-sm">Total expenditures in this date range:</span>
            </div>
            <div class="d-flex align-items-baseline gap-2">
                <span class="fs-xs text-muted text-uppercase fw-semibold">Net Total:</span>
                <span class="fs-4 fw-bold text-danger mb-0"><?php echo formatCurrency($totalPeriodExpense); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category Head</th>
                        <th>Description / Remarks</th>
                        <th>Payment Mode</th>
                        <th>Ref / Vch #</th>
                        <th>Recorded By</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-light"></i>
                            No expenses found for the selected period.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td class="text-muted fs-sm text-nowrap">
                            <?php echo date('d M Y', strtotime($exp['expense_date'])); ?>
                        </td>
                        <td>
                            <strong class="text-dark"><?php echo sanitize($exp['category_name']); ?></strong>
                        </td>
                        <td>
                            <?php echo sanitize($exp['description'] ?: '-'); ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?php echo strtoupper($exp['payment_method']); ?></span>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($exp['reference_number'] ?: '-'); ?>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($exp['created_by_user'] ?: 'Admin'); ?>
                        </td>
                        <td class="text-end">
                            <strong class="text-danger fs-6"><?php echo formatCurrency($exp['amount']); ?></strong>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo url('modules/finance/expenses.php?delete=' . $exp['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this expense?');" title="Delete">
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Record Daily Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="add_expense">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Expense Category / Head <span class="text-danger">*</span></label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold text-danger" name="amount" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Expense Date</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI / Online</option>
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receipt / Voucher # (Optional)</label>
                        <input type="text" class="form-control" name="reference_number" placeholder="e.g. VCH-0012 or EB-9918">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description / Remarks</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="e.g. Electricity bill paid for October"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receipt Photo / Scan (Optional)</label>
                        <input type="file" class="form-control" name="receipt" accept="image/*,application/pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

