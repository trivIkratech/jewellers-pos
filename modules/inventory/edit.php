<?php
/**
 * Edit Product
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$productId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(url('modules/inventory/index.php'), 'Product not found.', 'danger');
}

$pageTitle = 'Edit Jewellery Product';
$error = '';

// Load categories & suppliers
$categoriesStmt = $pdo->query("SELECT id, name, metal_type FROM categories WHERE is_active = 1 ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll();

$suppliersStmt = $pdo->query("SELECT id, name, company FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
$suppliers = $suppliersStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session token.';
    } else {
        $productCode = trim($_POST['product_code'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $metalType = trim($_POST['metal_type'] ?? 'gold');
        $purity = trim($_POST['purity'] ?? '22K');
        $grossWeight = (float)($_POST['gross_weight'] ?? 0);
        $stoneWeight = (float)($_POST['stone_weight'] ?? 0);
        $netWeight = max(0, $grossWeight - $stoneWeight);
        $stoneCharges = (float)($_POST['stone_charges'] ?? 0);
        $makingType = in_array($_POST['making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['making_charges_type'] : 'per_gram';
        $makingCharges = (float)($_POST['making_charges'] ?? 0);
        $wastagePct = (float)($_POST['wastage_percentage'] ?? 0);
        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $sellingPrice = (float)($_POST['selling_price'] ?? 0);
        $stockQuantity = max(0, (int)($_POST['stock_quantity'] ?? 0));
        $minStockLevel = max(1, (int)($_POST['min_stock_level'] ?? 1));
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $description = trim($_POST['description'] ?? '');

        if (empty($productCode) || empty($name) || empty($categoryId)) {
            $error = 'Please fill in all required fields (Product Code, Name, Category).';
        } else {
            // Check unique product code
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE product_code = ? AND id != ?");
            $checkStmt->execute([$productCode, $productId]);
            if ($checkStmt->fetch()) {
                $error = 'Product code "' . sanitize($productCode) . '" is already in use by another item.';
            } else {
                $imagePath = $product['image'];
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($ext, $allowed)) {
                        $filename = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $target = UPLOAD_DIR . $filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                            $imagePath = 'uploads/' . $filename;
                        }
                    }
                }

                // If stock was directly changed in edit, record transaction
                $oldStock = (int)$product['stock_quantity'];
                if ($oldStock !== $stockQuantity) {
                    $diff = $stockQuantity - $oldStock;
                    $type = $diff > 0 ? 'stock_in' : 'adjustment';
                    $txStmt = $pdo->prepare("INSERT INTO inventory_transactions (
                        product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, 'manual_edit', 'Stock adjusted in product edit form', ?)");
                    $txStmt->execute([$productId, $type, abs($diff), $oldStock, $stockQuantity, getCurrentUserId()]);
                }

                $updateStmt = $pdo->prepare("UPDATE products SET 
                    product_code = ?, barcode = ?, name = ?, category_id = ?, metal_type = ?, purity = ?,
                    gross_weight = ?, net_weight = ?, stone_weight = ?, stone_charges = ?,
                    making_charges_type = ?, making_charges = ?, wastage_percentage = ?,
                    purchase_price = ?, selling_price = ?, stock_quantity = ?, min_stock_level = ?,
                    supplier_id = ?, description = ?, image = ? 
                    WHERE id = ?");

                $updateStmt->execute([
                    $productCode, $barcode, $name, $categoryId, $metalType, $purity,
                    $grossWeight, $netWeight, $stoneWeight, $stoneCharges,
                    $makingType, $makingCharges, $wastagePct,
                    $purchasePrice, $sellingPrice, $stockQuantity, $minStockLevel,
                    $supplierId, $description, $imagePath, $productId
                ]);

                logAudit(getCurrentUserId(), 'update', 'products', $productId, ['name' => $product['name']], ['name' => $name]);

                redirect(url('modules/inventory/index.php'), "Product {$name} updated successfully!");
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Edit Jewellery Item</h4>
                <p class="text-muted fs-sm mb-0">Update item specifications, prices, or inventory levels</p>
            </div>
            <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-tag me-2 text-primary"></i>Product Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_code" required value="<?php echo sanitize($_POST['product_code'] ?? $product['product_code']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode" value="<?php echo sanitize($_POST['barcode'] ?? $product['barcode']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($_POST['category_id'] ?? $product['category_id']) == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required value="<?php echo sanitize($_POST['name'] ?? $product['name']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Metal Type</label>
                            <select class="form-select" name="metal_type">
                                <option value="gold" <?php echo ($_POST['metal_type'] ?? $product['metal_type']) === 'gold' ? 'selected' : ''; ?>>Gold</option>
                                <option value="silver" <?php echo ($_POST['metal_type'] ?? $product['metal_type']) === 'silver' ? 'selected' : ''; ?>>Silver</option>
                                <option value="diamond" <?php echo ($_POST['metal_type'] ?? $product['metal_type']) === 'diamond' ? 'selected' : ''; ?>>Diamond</option>
                                <option value="platinum" <?php echo ($_POST['metal_type'] ?? $product['metal_type']) === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                                <option value="other" <?php echo ($_POST['metal_type'] ?? $product['metal_type']) === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Purity</label>
                            <select class="form-select" name="purity">
                                <option value="24K" <?php echo ($_POST['purity'] ?? $product['purity']) === '24K' ? 'selected' : ''; ?>>24K</option>
                                <option value="22K" <?php echo ($_POST['purity'] ?? $product['purity']) === '22K' ? 'selected' : ''; ?>>22K (916)</option>
                                <option value="18K" <?php echo ($_POST['purity'] ?? $product['purity']) === '18K' ? 'selected' : ''; ?>>18K (750)</option>
                                <option value="14K" <?php echo ($_POST['purity'] ?? $product['purity']) === '14K' ? 'selected' : ''; ?>>14K</option>
                                <option value="999 Silver" <?php echo ($_POST['purity'] ?? $product['purity']) === '999 Silver' ? 'selected' : ''; ?>>999 Silver</option>
                                <option value="925 Silver" <?php echo ($_POST['purity'] ?? $product['purity']) === '925 Silver' ? 'selected' : ''; ?>>925 Sterling</option>
                                <option value="950 Platinum" <?php echo ($_POST['purity'] ?? $product['purity']) === '950 Platinum' ? 'selected' : ''; ?>>950 Platinum</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Weights & Charges</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Gross Weight (grams)</label>
                            <input type="number" step="0.001" class="form-control" name="gross_weight" id="grossWeight" value="<?php echo sanitize($_POST['gross_weight'] ?? $product['gross_weight']); ?>" oninput="calculateNetWeight()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stone Weight (grams)</label>
                            <input type="number" step="0.001" class="form-control" name="stone_weight" id="stoneWeight" value="<?php echo sanitize($_POST['stone_weight'] ?? $product['stone_weight']); ?>" oninput="calculateNetWeight()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Net Weight (grams)</label>
                            <input type="number" step="0.001" class="form-control bg-light fw-bold" name="net_weight" id="netWeight" readonly value="<?php echo sanitize($_POST['net_weight'] ?? $product['net_weight']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Making Charges Type</label>
                            <select class="form-select" name="making_charges_type">
                                <option value="per_gram" <?php echo ($_POST['making_charges_type'] ?? $product['making_charges_type']) === 'per_gram' ? 'selected' : ''; ?>>Per Gram (₹ / g)</option>
                                <option value="percentage" <?php echo ($_POST['making_charges_type'] ?? $product['making_charges_type']) === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo ($_POST['making_charges_type'] ?? $product['making_charges_type']) === 'fixed' ? 'selected' : ''; ?>>Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Making Charges</label>
                            <input type="number" step="0.01" class="form-control" name="making_charges" value="<?php echo sanitize($_POST['making_charges'] ?? $product['making_charges']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Wastage %</label>
                            <input type="number" step="0.01" class="form-control" name="wastage_percentage" value="<?php echo sanitize($_POST['wastage_percentage'] ?? $product['wastage_percentage']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stone Charges (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="stone_charges" value="<?php echo sanitize($_POST['stone_charges'] ?? $product['stone_charges']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price" value="<?php echo sanitize($_POST['purchase_price'] ?? $product['purchase_price']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" value="<?php echo sanitize($_POST['selling_price'] ?? $product['selling_price']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Stock & Media</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" min="0" step="1" class="form-control fw-bold text-primary" name="stock_quantity" value="<?php echo sanitize($_POST['stock_quantity'] ?? $product['stock_quantity']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Low Stock Alert Level</label>
                            <input type="number" min="1" step="1" class="form-control" name="min_stock_level" value="<?php echo sanitize($_POST['min_stock_level'] ?? $product['min_stock_level']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($_POST['supplier_id'] ?? $product['supplier_id']) == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['company'] ?: $s['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Replace Product Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <?php if ($product['image']): ?>
                            <small class="text-muted d-block mt-1">Current image: <?php echo basename($product['image']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo sanitize($_POST['description'] ?? $product['description']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateNetWeight() {
    const gross = parseFloat(document.getElementById('grossWeight').value) || 0;
    const stone = parseFloat(document.getElementById('stoneWeight').value) || 0;
    const net = Math.max(0, gross - stone);
    document.getElementById('netWeight').value = net.toFixed(3);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

