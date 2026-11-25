<?php
/**
 * Expense Categories Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Expense Categories';
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            logAudit(getCurrentUserId(), 'create', 'expense_categories', $pdo->lastInsertId(), null, ['name' => $name]);
            redirect(url('modules/finance/expense_categories.php'), 'Expense category added!');
        } else {
            $error = 'Category name is required.';
        }
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM expenses WHERE category_id = ?");
    $chk->execute([$delId]);
    if (($chk->fetch()['cnt'] ?? 0) > 0) {
        redirect(url('modules/finance/expense_categories.php'), 'Cannot delete category that contains existing expenses.', 'danger');
    } else {
        $pdo->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$delId]);
        logAudit(getCurrentUserId(), 'delete', 'expense_categories', $delId);
        redirect(url('modules/finance/expense_categories.php'), 'Category deleted.');
    }
}

// Fetch categories with total expenses
$sql = "SELECT ec.*, COUNT(e.id) as expense_count, COALESCE(SUM(e.amount), 0) as total_spent 
        FROM expense_categories ec 
        LEFT JOIN expenses e ON ec.id = e.category_id 
        WHERE ec.is_active = 1 
        GROUP BY ec.id 
        ORDER BY ec.name ASC";
$categories = $pdo->query($sql)->fetchAll();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Expense Categories</h4>
        <p class="text-muted fs-sm mb-0">Organize operational overheads like rent, staff salary, utilities, and marketing</p>
    </div>
    <a href="<?php echo url('modules/finance/expenses.php'); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Expenses
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Category Head</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add_category">

                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Workshop Tools / Dies">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief description of this expense head"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Create Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Entries</th>
                                <th>Lifetime Spent</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?php echo sanitize($c['name']); ?></strong>
                                </td>
                                <td class="text-muted fs-sm">
                                    <?php echo sanitize($c['description'] ?: '-'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo $c['expense_count']; ?> entries</span>
                                </td>
                                <td>
                                    <strong class="text-danger"><?php echo formatCurrency($c['total_spent']); ?></strong>
                                </td>
                                <td class="text-end">
                                    <?php if ($c['expense_count'] == 0): ?>
                                    <a href="<?php echo url('modules/finance/expense_categories.php?delete=' . $c['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted fs-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

