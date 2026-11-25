<?php
/**
 * Stock In / Out / Adjustment
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$productId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(url('modules/inventory/index.php'), 'Product not found.', 'danger');
}

$pageTitle = 'Stock Movement / Adjustment';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $actionType = $_POST['action_type'] ?? 'stock_in'; // stock_in, stock_out, adjustment
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $notes = trim($_POST['notes'] ?? '');
        $prevStock = (int)$product['stock_quantity'];

        if ($actionType === 'stock_in') {
            $newStock = $prevStock + $quantity;
            $txType = 'stock_in';
        } elseif ($actionType === 'stock_out') {
            if ($quantity > $prevStock) {
                $error = "Cannot remove {$quantity} items. Only {$prevStock} items in current stock.";
            } else {
                $newStock = $prevStock - $quantity;
                $txType = 'stock_out';
            }
        } elseif ($actionType === 'adjustment') {
            // Direct set to entered quantity
            $newStock = max(0, $quantity);
            $txType = 'adjustment';
            $quantity = abs($newStock - $prevStock);
        }

        if (!$error) {
            $pdo->beginTransaction();
            try {
                // Update product stock
                $upStmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                $upStmt->execute([$newStock, $productId]);

                // Insert into inventory_transactions
                $txStmt = $pdo->prepare("INSERT INTO inventory_transactions (
                    product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, 'manual_adjustment', ?, ?)");
                $txStmt->execute([$productId, $txType, $quantity, $prevStock, $newStock, $notes, getCurrentUserId()]);

                logAudit(getCurrentUserId(), $txType, 'inventory', $productId, ['stock' => $prevStock], ['stock' => $newStock, 'notes' => $notes]);

                $pdo->commit();
                redirect(url('modules/inventory/index.php'), "Stock successfully updated for {$product['name']} (New stock: {$newStock})!");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to update stock: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Stock Movement / Adjust</h4>
                <p class="text-muted fs-sm mb-0">Record new stock arrivals, inventory write-offs, or physical audit adjustments</p>
            </div>
            <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- Product Preview Card -->
        <div class="card mb-4 bg-light border">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-primary mb-1"><?php echo sanitize($product['product_code']); ?></span>
                        <h5 class="mb-1 text-dark fw-bold"><?php echo sanitize($product['name']); ?></h5>
                        <div class="fs-sm text-muted">
                            <?php echo sanitize($product['category_name']); ?> &bull; <?php echo sanitize($product['purity']); ?> &bull; Net Wt: <?php echo formatWeight($product['net_weight']); ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Current Available Stock</small>
                        <span class="fs-3 fw-bold <?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $product['stock_quantity']; ?> pcs
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Movement Operation <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="action_type" id="type_in" value="stock_in" checked>
                                <label class="btn btn-outline-success w-100 py-3" for="type_in">
                                    <i class="bi bi-box-arrow-in-down fs-4 d-block mb-1"></i>
                                    <strong>Stock In</strong>
                                    <div class="fs-xs text-muted">Add new arrival</div>
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="action_type" id="type_out" value="stock_out">
                                <label class="btn btn-outline-danger w-100 py-3" for="type_out">
                                    <i class="bi bi-box-arrow-up fs-4 d-block mb-1"></i>
                                    <strong>Stock Out</strong>
                                    <div class="fs-xs text-muted">Remove/Transfer</div>
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="action_type" id="type_adj" value="adjustment">
                                <label class="btn btn-outline-primary w-100 py-3" for="type_adj">
                                    <i class="bi bi-sliders fs-4 d-block mb-1"></i>
                                    <strong>Audit Set</strong>
                                    <div class="fs-xs text-muted">Set exact count</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" id="quantityLabel">Quantity to Add <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="1" class="form-control form-control-lg fw-bold" name="quantity" id="quantityInput" required value="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason / Reference Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="e.g. New lot received from Swarna Bullion, Karigar challan #4489, or monthly physical audit match."></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-1"></i> Confirm Stock Movement
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('input[name="action_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const lbl = document.getElementById('quantityLabel');
        if (this.value === 'stock_in') {
            lbl.innerText = 'Quantity to Add (pcs) *';
        } else if (this.value === 'stock_out') {
            lbl.innerText = 'Quantity to Remove (pcs) *';
        } else {
            lbl.innerText = 'New Total Stock Count (pcs) *';
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

