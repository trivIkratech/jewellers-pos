<?php
/**
 * Categories Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Jewellery Categories';
$pdo = getDBConnection();
$error = '';

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $metalType = trim($_POST['metal_type'] ?? 'gold');
        $desc = trim($_POST['description'] ?? '');

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, metal_type, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $metalType, $desc]);
            logAudit(getCurrentUserId(), 'create', 'categories', $pdo->lastInsertId(), null, ['name' => $name]);
            redirect(url('modules/inventory/categories.php'), 'Category added successfully!');
        } else {
            $error = 'Category name is required.';
        }
    }
}

// Delete Category
if (isset($_GET['delete'])) {
    requireAdmin();
    $delId = (int)$_GET['delete'];
    // Check if products exist in category
    $chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM products WHERE category_id = ? AND is_active = 1");
    $chk->execute([$delId]);
    if (($chk->fetch()['cnt'] ?? 0) > 0) {
        redirect(url('modules/inventory/categories.php'), 'Cannot delete category that contains active products.', 'danger');
    } else {
        $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $delStmt->execute([$delId]);
        logAudit(getCurrentUserId(), 'delete', 'categories', $delId);
        redirect(url('modules/inventory/categories.php'), 'Category removed.');
    }
}

// Fetch categories with product counts
$sql = "SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.metal_type ASC, c.name ASC";
$categories = $pdo->query($sql)->fetchAll();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Jewellery Categories</h4>
        <p class="text-muted fs-sm mb-0">Organize your shop catalogue by metal type, jewellery ornaments, and styles</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Inventory List
        </a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Add Category Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add New Category</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Gold Mangalsutra">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Metal Type</label>
                        <select class="form-select" name="metal_type">
                            <option value="gold">Gold</option>
                            <option value="silver">Silver</option>
                            <option value="diamond">Diamond</option>
                            <option value="platinum">Platinum</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Brief notes"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Create Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Category List Table -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Metal Type</th>
                                <th>Active Items</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?php echo sanitize($cat['name']); ?></strong>
                                    <?php if ($cat['description']): ?>
                                    <div class="fs-xs text-muted"><?php echo sanitize($cat['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary-light text-primary"><?php echo ucfirst($cat['metal_type']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo $cat['product_count']; ?> items</span>
                                </td>
                                <td class="text-end">
                                    <?php if ($cat['product_count'] == 0 && isAdmin()): ?>
                                    <a href="<?php echo url('modules/inventory/categories.php?delete=' . $cat['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category?');">
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

