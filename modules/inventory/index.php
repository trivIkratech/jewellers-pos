<?php
/**
 * Inventory Management - List Products
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Inventory Management';
$pdo = getDBConnection();

// Filters
$search = trim($_GET['search'] ?? '');
$categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : '';
$metalType = trim($_GET['metal_type'] ?? '');
$stockFilter = trim($_GET['filter'] ?? '');

// Base query
$sql = "SELECT p.*, c.name as category_name, s.company as supplier_company 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.is_active = 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categoryId) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if ($metalType !== '') {
    $sql .= " AND p.metal_type = ?";
    $params[] = $metalType;
}

if ($stockFilter === 'low_stock') {
    $sql .= " AND p.stock_quantity <= p.min_stock_level";
} elseif ($stockFilter === 'out_of_stock') {
    $sql .= " AND p.stock_quantity <= 0";
} elseif ($stockFilter === 'in_stock') {
    $sql .= " AND p.stock_quantity > p.min_stock_level";
}

$sql .= " ORDER BY p.stock_quantity <= p.min_stock_level DESC, p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Statistics
$statTotalStmt = $pdo->query("SELECT 
    COUNT(*) as total_items, 
    COALESCE(SUM(stock_quantity), 0) as total_qty, 
    COALESCE(SUM(stock_quantity * selling_price), 0) as total_val,
    SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock_count
    FROM products WHERE is_active = 1");
$stats = $statTotalStmt->fetch();

// Categories for filter dropdown
$catStmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
$allCategories = $catStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Stock Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['total_items'] ?? 0); ?></h3>
                <p>Total Products Registered</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-stack"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo number_format($stats['total_qty'] ?? 0); ?> pcs</h3>
                <p>Physical Stock Count</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon <?php echo ($stats['low_stock_count'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo ($stats['low_stock_count'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo number_format($stats['low_stock_count'] ?? 0); ?> Items
                </h3>
                <p>Low Stock Notifications</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($stats['total_val'] ?? 0); ?></h3>
                <p>Estimated Inventory Value</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_GET['new_id'])): ?>
<div class="alert alert-success d-flex align-items-center justify-content-between p-3 mb-4 shadow-sm border-0 bg-success-subtle rounded-3">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-check-circle-fill text-success fs-2"></i>
        <div>
            <div class="fw-bold text-dark fs-6">Product successfully added to inventory!</div>
            <div class="text-muted fs-sm">You can now print this jewellery item's tag or generate full A4 sheets for all products.</div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo url('modules/inventory/print_bulk_qr.php?ids=' . (int)$_GET['new_id']); ?>" target="_blank" class="btn btn-outline-success btn-sm fw-bold">
            <i class="bi bi-tag-fill me-1"></i> Print This Tag
        </a>
        <a href="<?php echo url('modules/inventory/print_bulk_qr.php'); ?>" target="_blank" class="btn btn-success btn-sm fw-bold shadow-xs">
            <i class="bi bi-printer-fill me-1"></i> Print All QR Tags (A4 Sheet)
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Controls & Actions Bar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-2 align-items-center">
            <!-- Search & Filters Form -->
            <div class="col-12 col-xl">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-12 col-md">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search code, name, barcode..." value="<?php echo sanitize($search); ?>">
                        </div>
                    </div>

                    <div class="col-6 col-sm-4 col-md-auto">
                        <select class="form-select form-select-sm" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-sm-4 col-md-auto">
                        <select class="form-select form-select-sm" name="metal_type" onchange="this.form.submit()">
                            <option value="">All Metals</option>
                            <option value="gold" <?php echo $metalType === 'gold' ? 'selected' : ''; ?>>Gold</option>
                            <option value="silver" <?php echo $metalType === 'silver' ? 'selected' : ''; ?>>Silver</option>
                            <option value="diamond" <?php echo $metalType === 'diamond' ? 'selected' : ''; ?>>Diamond</option>
                            <option value="platinum" <?php echo $metalType === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                            <option value="other" <?php echo $metalType === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-<?php echo ($search !== '' || $categoryId || $metalType !== '' || $stockFilter !== '') ? '8 col-sm-4' : '12 col-sm-4'; ?> col-md-auto">
                        <select class="form-select form-select-sm" name="filter" onchange="this.form.submit()">
                            <option value="">All Stock Levels</option>
                            <option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="in_stock" <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="out_of_stock" <?php echo $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>

                    <?php if ($search !== '' || $categoryId || $metalType !== '' || $stockFilter !== ''): ?>
                    <div class="col-4 col-sm-auto">
                        <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-sm btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Buttons (Desktop only - hidden on mobile) -->
            <div class="col-12 col-xl-auto d-none d-md-flex gap-2 flex-wrap justify-content-xl-end">
                <a href="<?php echo url('modules/inventory/print_bulk_qr.php'); ?>" target="_blank" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1 shadow-xs fw-semibold">
                    <i class="bi bi-qr-code text-primary"></i>
                    <span>Print Bulk QR (A4 Sheet)</span>
                </a>
                <a href="<?php echo url('modules/inventory/transactions.php'); ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i> Stock Movement
                </a>
                <a href="<?php echo url('modules/inventory/categories.php'); ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-tags me-1"></i> Categories
                </a>
                <a href="<?php echo url('modules/inventory/add.php'); ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Selection Floating Toolbar -->
<div id="bulkActionsBar" class="alert alert-primary py-2 px-3 mb-3 d-none align-items-center justify-content-between shadow-sm border-primary">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-check2-circle fs-5 text-primary"></i>
        <span class="fw-bold"><span id="selectedItemsCount">0</span> Jewellery items selected</span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearSelection()">Clear Selection</button>
        <a href="#" id="bulkPrintBtn" target="_blank" class="btn btn-sm btn-primary fw-bold d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer-fill"></i> Print Selected QR (A4 Sheet)
        </a>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">
                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input" onchange="toggleSelectAll(this)" title="Select all for Bulk QR print">
                        </th>
                        <th>Product / Code</th>
                        <th>Category</th>
                        <th>Metal & Purity</th>
                        <th>Weights (Gross / Net)</th>
                        <th>Making Charges</th>
                        <th>Selling Price</th>
                        <th>Available Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state py-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3 shadow-xs" style="width: 64px; height: 64px;">
                                    <i class="bi bi-gem fs-2"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-1">No jewellery items found</h5>
                                <p class="text-muted fs-sm mb-3">Try adjusting your search filters or register a new jewellery product to stock.</p>
                                <a href="<?php echo url('modules/inventory/add.php'); ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-plus-lg fs-6"></i>
                                    <span>Add Product</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): 
                        $isLowStock = (int)$p['stock_quantity'] <= (int)$p['min_stock_level'];
                        $isOut = (int)$p['stock_quantity'] <= 0;
                    ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input product-select-checkbox" value="<?php echo $p['id']; ?>" onchange="updateSelectedCount()">
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div style="width: 44px; height: 44px; background: #F4F6F9; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid #E5E7EB;">
                                    <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../../' . $p['image'])): ?>
                                    <img src="<?php echo url($p['image']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                    <i class="bi bi-gem text-primary fs-5"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong class="text-dark d-block"><?php echo sanitize($p['name']); ?></strong>
                                    <span class="badge bg-light text-dark border me-1"><?php echo sanitize($p['product_code']); ?></span>
                                    <?php if ($p['barcode']): ?>
                                    <small class="text-muted"><i class="bi bi-upc"></i> <?php echo sanitize($p['barcode']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-dark"><?php echo sanitize($p['category_name'] ?: 'General'); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary-light text-primary"><?php echo ucfirst($p['metal_type']); ?></span>
                            <span class="badge bg-light text-dark border"><?php echo sanitize($p['purity']); ?></span>
                        </td>
                        <td>
                            <div><strong class="text-dark"><?php echo formatWeight($p['net_weight']); ?></strong> <span class="text-muted fs-xs">(Net)</span></div>
                            <small class="text-muted"><?php echo formatWeight($p['gross_weight']); ?> Gross <?php if ($p['stone_weight'] > 0) echo ' | ' . formatWeight($p['stone_weight']) . ' St'; ?></small>
                        </td>
                        <td>
                            <?php if ($p['making_charges_type'] === 'per_gram'): ?>
                                ₹<?php echo number_format($p['making_charges'], 2); ?> /g
                            <?php elseif ($p['making_charges_type'] === 'percentage'): ?>
                                <?php echo $p['making_charges']; ?>%
                            <?php else: ?>
                                ₹<?php echo number_format($p['making_charges'], 2); ?> Fix
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-primary"><?php echo formatCurrency($p['selling_price']); ?></strong>
                        </td>
                        <td>
                            <?php if ($isOut): ?>
                                <span class="badge bg-danger">Out of Stock (0)</span>
                            <?php elseif ($isLowStock): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo $p['stock_quantity']; ?> left</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?php echo $p['stock_quantity']; ?> in stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end action-dropdown">
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0)" onclick='openProductQrTag(<?php echo htmlspecialchars(json_encode([
                                            "name" => $p["name"],
                                            "code" => $p["product_code"],
                                            "barcode" => $p["barcode"],
                                            "metal" => ucfirst($p["metal_type"]),
                                            "purity" => $p["purity"],
                                            "weight" => formatWeight($p["net_weight"]),
                                            "gross" => formatWeight($p["gross_weight"]),
                                            "price" => formatCurrency($p["selling_price"])
                                        ]), ENT_QUOTES, "UTF-8"); ?>)'>
                                            <i class="bi bi-qr-code text-warning"></i> Preview Single QR Tag
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/inventory/print_bulk_qr.php?ids=' . $p['id']); ?>" target="_blank">
                                            <i class="bi bi-printer text-primary"></i> Print A4 Tag Sheet
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/inventory/stock_adjust.php?id=' . $p['id']); ?>">
                                            <i class="bi bi-plus-slash-minus text-success"></i> Stock In / Out
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/inventory/edit.php?id=' . $p['id']); ?>">
                                            <i class="bi bi-pencil text-primary"></i> Edit Product
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/inventory/transactions.php?product_id=' . $p['id']); ?>">
                                            <i class="bi bi-clock-history text-info"></i> Movement Log
                                        </a>
                                    </li>
                                    <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="<?php echo url('modules/inventory/delete.php?id=' . $p['id']); ?>" onclick="return confirm('Are you sure you want to delete this product?');">
                                            <i class="bi bi-trash"></i> Delete Product
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

<!-- ========================================================================= -->
<!-- Modal: Printable Jewellery QR Tag                                        -->
<!-- ========================================================================= -->
<div class="modal fade" id="productQrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold fs-sm"><i class="bi bi-qr-code text-warning me-1"></i> Jewellery QR Tag</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 text-center">
                
                <!-- Tag Container (Printable) -->
                <div id="printableQrTag" class="p-3 border rounded bg-white shadow-sm d-inline-block text-center" style="max-width: 250px;">
                    <div class="fw-bold fs-xs text-uppercase tracking-wider text-primary mb-1">Sachin Jewellers</div>
                    <div class="fw-bold text-dark fs-sm text-truncate" id="tagProdName">Product Name</div>
                    <div class="fs-xxs text-muted mb-2 font-monospace" id="tagProdCode">CODE: GN-001</div>
                    
                    <!-- QR Code Canvas -->
                    <div id="qrCodeContainer" class="d-flex justify-content-center my-2 p-2 bg-light rounded border"></div>
                    
                    <div class="d-flex justify-content-between fs-xs border-top pt-1 mt-1">
                        <span class="fw-bold text-dark" id="tagPurity">22K</span>
                        <span class="fw-bold text-dark" id="tagWeight">Net: 27.20g</span>
                    </div>
                    <div class="fs-xxs text-muted text-center mt-1" id="tagBarcode">8901234001</div>
                </div>

            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="printQrTag()">
                    <i class="bi bi-printer me-1"></i> Print Tag
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
$extraJS = '
<script src="' . url('assets/js/qrcode.min.js') . '"></script>
<script>
let qrGenerator = null;

function openProductQrTag(p) {
    document.getElementById("tagProdName").innerText = p.name;
    document.getElementById("tagProdCode").innerText = "CODE: " + p.code;
    document.getElementById("tagPurity").innerText = p.metal + " " + p.purity;
    document.getElementById("tagWeight").innerText = "Net: " + p.weight;
    document.getElementById("tagBarcode").innerText = p.barcode ? "BAR: " + p.barcode : "";

    const container = document.getElementById("qrCodeContainer");
    container.innerHTML = "";
    
    // Generate QR code encoding the product code
    new QRCode(container, {
        text: p.code,
        width: 140,
        height: 140,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });

    const modal = new bootstrap.Modal(document.getElementById("productQrModal"));
    modal.show();
}

function printQrTag() {
    const printContent = document.getElementById("printableQrTag").innerHTML;
    const printWindow = window.open("", "", "width=400,height=500");
    printWindow.document.write(`
        <html>
        <head>
            <title>Jewellery Tag</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; text-align: center; padding: 15px; margin: 0; }
                .fw-bold { font-weight: bold; }
                .fs-xs { font-size: 11px; }
                .fs-sm { font-size: 13px; }
                .fs-xxs { font-size: 9px; }
                .text-muted { color: #6c757d; }
                .my-2 { margin: 8px 0; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
}

function toggleSelectAll(master) {
    document.querySelectorAll(".product-select-checkbox").forEach(chk => {
        chk.checked = master.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = Array.from(document.querySelectorAll(".product-select-checkbox:checked")).map(c => c.value);
    const bar = document.getElementById("bulkActionsBar");
    const countSpan = document.getElementById("selectedItemsCount");
    const selectAll = document.getElementById("selectAllCheckbox");
    const total = document.querySelectorAll(".product-select-checkbox").length;

    if (checked.length > 0) {
        bar.classList.remove("d-none");
        bar.classList.add("d-flex");
        countSpan.innerText = checked.length;
        document.getElementById("bulkPrintBtn").href = "' . url('modules/inventory/print_bulk_qr.php?ids=') . '" + checked.join(",");
        if (selectAll) selectAll.checked = (checked.length === total);
    } else {
        bar.classList.remove("d-flex");
        bar.classList.add("d-none");
        if (selectAll) selectAll.checked = false;
    }
}

function clearSelection() {
    document.querySelectorAll(".product-select-checkbox").forEach(chk => chk.checked = false);
    const selectAll = document.getElementById("selectAllCheckbox");
    if (selectAll) selectAll.checked = false;
    updateSelectedCount();
}
</script>
';
include __DIR__ . '/../../includes/footer.php'; 
?>

