<?php
/**
 * Add Product - Interactive Flow
 * Follows: Product Name -> Product Weight -> Product Categories (Gold, Silver, Diamond, Platinum, Other) -> Product Code Generated -> Added to Inventory
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Add Jewellery Product';
$pdo = getDBConnection();
$error = '';
$success = '';

// Load categories & suppliers
$categoriesStmt = $pdo->query("SELECT id, name, metal_type FROM categories WHERE is_active = 1 ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$suppliersStmt = $pdo->query("SELECT id, name, company FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $metalType = strtolower(trim($_POST['metal_type'] ?? 'gold'));
        if (!in_array($metalType, ['gold', 'silver', 'diamond', 'platinum', 'other'])) {
            $metalType = 'gold';
        }
        $categoryId = (int)($_POST['category_id'] ?? 0);
        
        // If no category selected, pick first category matching metal_type or create fallback
        if ($categoryId <= 0) {
            foreach ($categories as $cat) {
                if (strtolower($cat['metal_type']) === $metalType) {
                    $categoryId = (int)$cat['id'];
                    break;
                }
            }
            if ($categoryId <= 0 && !empty($categories)) {
                $categoryId = (int)$categories[0]['id'];
            }
        }

        $grossWeight = (float)($_POST['gross_weight'] ?? 0);
        $stoneWeight = (float)($_POST['stone_weight'] ?? 0);
        $netWeight = max(0, $grossWeight - $stoneWeight);

        $productCode = trim($_POST['product_code'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');

        // Auto-generate code if empty
        if (empty($productCode)) {
            $prefix = match($metalType) {
                'gold' => 'GLD',
                'silver' => 'SLV',
                'diamond' => 'DMD',
                'platinum' => 'PLT',
                default => 'OTH'
            };
            $stmtMax = $pdo->prepare("SELECT product_code FROM products WHERE product_code LIKE ? ORDER BY id DESC LIMIT 50");
            $stmtMax->execute([$prefix . '-%']);
            $existing = $stmtMax->fetchAll(PDO::FETCH_COLUMN);
            $maxSeq = 0;
            foreach ($existing as $c) {
                if (preg_match('/-(\d+)$/', $c, $m)) {
                    $maxSeq = max($maxSeq, (int)$m[1]);
                }
            }
            $productCode = sprintf('%s-%03d', $prefix, $maxSeq + 1);
        }

        if (empty($barcode)) {
            $metalNum = match($metalType) {
                'gold' => '10',
                'silver' => '20',
                'diamond' => '30',
                'platinum' => '40',
                default => '50'
            };
            $barcode = '890' . $metalNum . rand(10000, 99999);
        }

        $purity = trim($_POST['purity'] ?? '');
        if (empty($purity)) {
            $purity = match($metalType) {
                'gold' => '22K',
                'silver' => '925 Silver',
                'diamond' => '18K Diamond',
                'platinum' => '950 Platinum',
                default => 'Standard'
            };
        }

        $makingType = in_array($_POST['making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['making_charges_type'] : getSetting('default_making_charges_type', 'per_gram');
        $makingCharges = (float)($_POST['making_charges'] ?? getSetting('default_making_charges_rate', 400.00));
        $stoneCharges = (float)($_POST['stone_charges'] ?? 0);
        $wastagePct = (float)($_POST['wastage_percentage'] ?? 0);
        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $sellingPrice = (float)($_POST['selling_price'] ?? 0);
        $stockQuantity = max(1, (int)($_POST['stock_quantity'] ?? 1));
        $minStockLevel = max(1, (int)($_POST['min_stock_level'] ?? 1));
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = 'Please enter Product Name.';
        } elseif ($grossWeight <= 0) {
            $error = 'Please enter a valid Product Weight (greater than 0 grams).';
        } else {
            // Check unique product code
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE product_code = ?");
            $checkStmt->execute([$productCode]);
            if ($checkStmt->fetch()) {
                // If collision, append random sequence
                $productCode .= '-' . rand(10, 99);
            }

            // Optional image upload
            $imagePath = '';
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

            // Insert into products table
            $insertStmt = $pdo->prepare("INSERT INTO products (
                product_code, barcode, name, category_id, metal_type, purity,
                gross_weight, net_weight, stone_weight, stone_charges,
                making_charges_type, making_charges, wastage_percentage,
                purchase_price, selling_price, stock_quantity, min_stock_level,
                supplier_id, description, image
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )");

            $insertStmt->execute([
                $productCode, $barcode, $name, $categoryId, $metalType, $purity,
                $grossWeight, $netWeight, $stoneWeight, $stoneCharges,
                $makingType, $makingCharges, $wastagePct,
                $purchasePrice, $sellingPrice, $stockQuantity, $minStockLevel,
                $supplierId, $description, $imagePath
            ]);

            $newProductId = $pdo->lastInsertId();

            // Record initial stock transaction
            if ($stockQuantity > 0) {
                $txStmt = $pdo->prepare("INSERT INTO inventory_transactions (
                    product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, notes, created_by
                ) VALUES (?, 'stock_in', ?, 0, ?, 'initial_stock', 'Initial opening stock upon product creation', ?)");
                $txStmt->execute([$newProductId, $stockQuantity, $stockQuantity, getCurrentUserId()]);
            }

            logAudit(getCurrentUserId(), 'create', 'products', $newProductId, null, ['code' => $productCode, 'name' => $name]);

            redirect(url('modules/inventory/index.php?new_id=' . $newProductId), "Product '{$name}' [{$productCode}] successfully added to inventory!");
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2" style="font-size: 1rem;"><i class="bi bi-plus-lg"></i></span>
                    Add Product to Inventory
                </h4>
                <p class="text-muted fs-sm mb-0">Follow the quick 5-step workflow to register new jewellery items</p>
            </div>
            <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4 shadow-sm border-0 d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?php echo sanitize($error); ?></div>
        </div>
        <?php endif; ?>

        <!-- Visual Flow Tracker -->
        <div class="card mb-4 border-0 shadow-sm bg-light">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center justify-content-between text-center flex-wrap gap-2 fs-xs fw-bold">
                    <div class="d-flex align-items-center gap-2 text-primary">
                        <span class="badge rounded-circle bg-primary text-white" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">1</span>
                        <span>Product Name</span>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                    <div class="d-flex align-items-center gap-2 text-primary">
                        <span class="badge rounded-circle bg-primary text-white" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">2</span>
                        <span>Product Weight</span>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                    <div class="d-flex align-items-center gap-2 text-primary">
                        <span class="badge rounded-circle bg-primary text-white" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">3</span>
                        <span>Categories</span>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                    <div class="d-flex align-items-center gap-2 text-primary">
                        <span class="badge rounded-circle bg-primary text-white" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">4</span>
                        <span>Code Generated</span>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                    <div class="d-flex align-items-center gap-2 text-success">
                        <span class="badge rounded-circle bg-success text-white" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">5</span>
                        <span>Add to Inventory</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="addProductForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="metal_type" id="selectedMetalType" value="gold">

            <!-- STEP 1: PRODUCT NAME -->
            <div class="card mb-3 shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">1</span>
                    <h6 class="mb-0 fw-bold text-dark">Product Name</h6>
                </div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold text-secondary">Enter Jewellery Product Name / Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg fw-semibold" name="name" id="productNameInput" required placeholder="e.g. Floral 22K Gold Ring, Peacock Jhumka, Silver Payal" value="<?php echo sanitize($_POST['name'] ?? ''); ?>" autofocus>
                    
                    <!-- Quick Name Tags / Chips -->
                    <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                        <small class="text-muted fw-semibold me-1"><i class="bi bi-lightning-charge text-warning"></i> Quick Select:</small>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Ring')">+ Ring</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Necklace')">+ Necklace</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Bangle')">+ Bangle</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Earrings')">+ Earrings</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Chain')">+ Chain</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Pendant')">+ Pendant</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill quick-name-btn" onclick="quickAppendName('Bracelet')">+ Bracelet</button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: PRODUCT WEIGHT -->
            <div class="card mb-3 shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">2</span>
                    <h6 class="mb-0 fw-bold text-dark">Product Weight</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold text-secondary">Gross Weight (grams) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <input type="number" step="0.001" min="0.001" class="form-control fw-bold text-primary" name="gross_weight" id="grossWeightInput" required placeholder="0.000" value="<?php echo sanitize($_POST['gross_weight'] ?? ''); ?>" oninput="recalcWeights()">
                                <span class="input-group-text bg-light fw-semibold">grams</span>
                            </div>
                            <small class="text-muted">Total weight on scale</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-secondary">Stone / Bead Weight</label>
                            <div class="input-group input-group-lg">
                                <input type="number" step="0.001" min="0" class="form-control text-muted" name="stone_weight" id="stoneWeightInput" placeholder="0.000" value="<?php echo sanitize($_POST['stone_weight'] ?? '0.000'); ?>" oninput="recalcWeights()">
                                <span class="input-group-text bg-light text-muted">grams</span>
                            </div>
                            <small class="text-muted">Deducted from gross</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-secondary">Net Metal Weight</label>
                            <div class="p-2 border rounded-3 bg-light text-center h-auto d-flex flex-column justify-content-center" style="min-height: 48px;">
                                <div class="fs-4 fw-bold text-success" id="netWeightDisplay">0.000 g</div>
                                <small class="text-muted fs-xs">Pure billing weight</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: PRODUCT CATEGORIES (Gold, Silver, Diamond, Platinum, Other) -->
            <div class="card mb-3 shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">3</span>
                        <h6 class="mb-0 fw-bold text-dark">Product Categories</h6>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary px-3 py-1 rounded-pill fs-xs">Select 1 Category</span>
                </div>
                <div class="card-body p-4">
                    <!-- 5 Category Cards -->
                    <div class="row g-3 mb-4" id="metalCategoryCards">
                        
                        <!-- Gold -->
                        <div class="col">
                            <div class="category-card border rounded-3 p-3 text-center cursor-pointer transition active" data-metal="gold" onclick="selectMetalCategory('gold', this)" style="border-width: 2px !important; border-color: #f59e0b !important; background-color: #fffbeb;">
                                <div class="mb-2 fs-2 text-warning"><i class="bi bi-gem"></i></div>
                                <div class="fw-bold text-dark">Gold</div>
                                <small class="text-muted d-block fs-xs">22K / 24K / 18K</small>
                            </div>
                        </div>

                        <!-- Silver -->
                        <div class="col">
                            <div class="category-card border rounded-3 p-3 text-center cursor-pointer transition" data-metal="silver" onclick="selectMetalCategory('silver', this)" style="border-width: 2px !important; border-color: #e2e8f0;">
                                <div class="mb-2 fs-2 text-secondary"><i class="bi bi-stars"></i></div>
                                <div class="fw-bold text-dark">Silver</div>
                                <small class="text-muted d-block fs-xs">925 / 999</small>
                            </div>
                        </div>

                        <!-- Diamond -->
                        <div class="col">
                            <div class="category-card border rounded-3 p-3 text-center cursor-pointer transition" data-metal="diamond" onclick="selectMetalCategory('diamond', this)" style="border-width: 2px !important; border-color: #e2e8f0;">
                                <div class="mb-2 fs-2 text-info"><i class="bi bi-suit-diamond-fill"></i></div>
                                <div class="fw-bold text-dark">Diamond</div>
                                <small class="text-muted d-block fs-xs">Real & Solitaire</small>
                            </div>
                        </div>

                        <!-- Platinum -->
                        <div class="col">
                            <div class="category-card border rounded-3 p-3 text-center cursor-pointer transition" data-metal="platinum" onclick="selectMetalCategory('platinum', this)" style="border-width: 2px !important; border-color: #e2e8f0;">
                                <div class="mb-2 fs-2 text-primary"><i class="bi bi-shield-check"></i></div>
                                <div class="fw-bold text-dark">Platinum</div>
                                <small class="text-muted d-block fs-xs">950 Pure</small>
                            </div>
                        </div>

                        <!-- Other -->
                        <div class="col">
                            <div class="category-card border rounded-3 p-3 text-center cursor-pointer transition" data-metal="other" onclick="selectMetalCategory('other', this)" style="border-width: 2px !important; border-color: #e2e8f0;">
                                <div class="mb-2 fs-2 text-dark"><i class="bi bi-box-seam"></i></div>
                                <div class="fw-bold text-dark">Other</div>
                                <small class="text-muted d-block fs-xs">Mixed / Alloy</small>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-category & Purity selector row -->
                    <div class="row g-3 pt-3 border-top">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Subcategory / Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="subcategorySelect" required onchange="triggerCodeGeneration()">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" data-metal="<?php echo strtolower($cat['metal_type']); ?>" <?php echo strtolower($cat['metal_type']) === 'gold' ? '' : 'style="display:none;"'; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Purity Grade</label>
                            <select class="form-select" name="purity" id="puritySelect">
                                <option value="22K" selected>22K (916 Hallmarked)</option>
                                <option value="24K">24K (999 Pure)</option>
                                <option value="18K">18K (750)</option>
                                <option value="14K">14K (585)</option>
                                <option value="999 Silver">999 Fine Silver</option>
                                <option value="925 Silver">925 Sterling Silver</option>
                                <option value="950 Platinum">950 Platinum</option>
                                <option value="Standard">Standard / Imitation</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 4: PRODUCT CODE GENERATED -->
            <div class="card mb-4 shadow-sm border" style="background: linear-gradient(to right, #f8fafc, #eff6ff);">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-circle" style="width: 24px; height: 24px; line-height: 24px; padding: 0;">4</span>
                        <h6 class="mb-0 fw-bold text-dark">Product Code Generated</h6>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fs-xs">
                        <i class="bi bi-check-circle-fill me-1"></i> Auto-Generated Code
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Product Code (SKU)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white text-primary"><i class="bi bi-qr-code"></i></span>
                                <input type="text" class="form-control fw-bold font-monospace text-primary" name="product_code" id="productCodeInput" required readonly value="GLD-001">
                                <button type="button" class="btn btn-outline-secondary" onclick="triggerCodeGeneration()" title="Regenerate Code">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="enableCodeEdit()" title="Edit Manually">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                            <small class="text-muted fs-xs">Automatically assigned based on metal category & sequence.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary">Barcode / Scanner Gun Tag</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white text-secondary"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" class="form-control font-monospace" name="barcode" id="barcodeInput" value="8901000001">
                            </div>
                            <small class="text-muted fs-xs">Used for optical barcode / QR reader scanners.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- OPTIONAL DETAILS ACCORDION (Making Charges, Stock, etc.) -->
            <div class="accordion mb-4 shadow-sm" id="optionalDetailsAccordion">
                <div class="accordion-item border rounded-3 overflow-hidden">
                    <h2 class="accordion-header" id="headingOptional">
                        <button class="accordion-button collapsed py-3 bg-light text-dark fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptional" aria-expanded="false" aria-controls="collapseOptional">
                            <i class="bi bi-sliders me-2 text-primary"></i> Optional Details (Making Charges, Stock Qty, Karigar / Supplier)
                        </button>
                    </h2>
                    <div id="collapseOptional" class="accordion-collapse collapse" aria-labelledby="headingOptional" data-bs-parent="#optionalDetailsAccordion">
                        <div class="accordion-body p-4 bg-white">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Making Charges Type</label>
                                    <?php $initMakingType = getSetting('gold_making_charges_type', getSetting('default_making_charges_type', 'per_gram')); ?>
                                    <select class="form-select" name="making_charges_type" id="making_charges_type">
                                        <option value="per_gram" <?php echo $initMakingType === 'per_gram' ? 'selected' : ''; ?>>Per Gram (₹ / g)</option>
                                        <option value="percentage" <?php echo $initMakingType === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                        <option value="fixed" <?php echo $initMakingType === 'fixed' ? 'selected' : ''; ?>>Fixed Amount (₹)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Making Charges Value</label>
                                    <?php $initMakingRate = getSetting('gold_making_charges_rate', getSetting('default_making_charges_rate', '400.00')); ?>
                                    <input type="number" step="0.01" class="form-control fw-bold" name="making_charges" id="making_charges" value="<?php echo sanitize($initMakingRate); ?>" placeholder="400.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Initial Stock Quantity</label>
                                    <input type="number" min="1" step="1" class="form-control fw-bold" name="stock_quantity" value="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Supplier / Karigar</label>
                                    <select class="form-select" name="supplier_id">
                                        <option value="">Select Supplier (Optional)</option>
                                        <?php foreach ($suppliers as $s): ?>
                                        <option value="<?php echo $s['id']; ?>">
                                            <?php echo sanitize($s['company'] ?: $s['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Description / HUID / Hallmark Note</label>
                                    <input type="text" class="form-control" name="description" placeholder="e.g. BIS Hallmarked HUID 6-digit inscribed, crafted in Rajkot">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 5: ADD TO INVENTORY ACTION -->
            <div class="card border-0 shadow-sm p-4 bg-white text-center mb-5">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="text-start">
                        <h6 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="badge bg-success rounded-circle" style="width: 22px; height: 22px; line-height: 22px; padding: 0;">5</span>
                            Ready to Add Product
                        </h6>
                        <p class="text-muted fs-xs mb-0">Item will be registered in inventory with unique code and available immediately for POS billing.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg px-5 py-2 rounded-pill fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-in-down fs-5"></i>
                            <span>Add Product to Inventory</span>
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
// Recalculate Net Weight
function recalcWeights() {
    const gross = parseFloat(document.getElementById('grossWeightInput').value) || 0;
    const stone = parseFloat(document.getElementById('stoneWeightInput').value) || 0;
    const net = Math.max(0, gross - stone);
    document.getElementById('netWeightDisplay').innerText = net.toFixed(3) + ' g';
}

// Quick append to name
function quickAppendName(word) {
    const input = document.getElementById('productNameInput');
    const cur = input.value.trim();
    if (!cur) {
        const metal = document.getElementById('selectedMetalType').value;
        const metalName = metal.charAt(0).toUpperCase() + metal.slice(1);
        input.value = metalName + ' ' + word;
    } else if (!cur.toLowerCase().includes(word.toLowerCase())) {
        input.value = cur + ' ' + word;
    }
    input.focus();
}

// Metal category card click handler
function selectMetalCategory(metal, cardEl) {
    document.getElementById('selectedMetalType').value = metal;

    // Update active styles on cards
    document.querySelectorAll('.category-card').forEach(c => {
        c.classList.remove('active');
        c.style.backgroundColor = '#ffffff';
        c.style.borderColor = '#e2e8f0';
    });

    cardEl.classList.add('active');
    const colorMap = {
        'gold': { border: '#f59e0b', bg: '#fffbeb' },
        'silver': { border: '#64748b', bg: '#f8fafc' },
        'diamond': { border: '#06b6d4', bg: '#ecfeff' },
        'platinum': { border: '#3b82f6', bg: '#eff6ff' },
        'other': { border: '#8b5cf6', bg: '#f5f3ff' }
    };
    const c = colorMap[metal] || { border: '#3b82f6', bg: '#eff6ff' };
    cardEl.style.borderColor = c.border;
    cardEl.style.backgroundColor = c.bg;

    // Filter subcategories dropdown
    const select = document.getElementById('subcategorySelect');
    let firstValid = null;
    Array.from(select.options).forEach(opt => {
        const optMetal = opt.getAttribute('data-metal');
        if (optMetal === metal) {
            opt.style.display = '';
            opt.disabled = false;
            if (!firstValid) firstValid = opt.value;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    });

    if (firstValid) {
        select.value = firstValid;
    }

    // Adjust default purity
    const puritySelect = document.getElementById('puritySelect');
    if (metal === 'gold') puritySelect.value = '22K';
    else if (metal === 'silver') puritySelect.value = '925 Silver';
    else if (metal === 'diamond') puritySelect.value = '18K';
    else if (metal === 'platinum') puritySelect.value = '950 Platinum';
    else puritySelect.value = 'Standard';

    // Auto-update working charges from Admin Settings
    const metalDefaults = {
        'gold': { type: '<?php echo getSetting('gold_making_charges_type', 'per_gram'); ?>', rate: '<?php echo getSetting('gold_making_charges_rate', '400.00'); ?>' },
        'silver': { type: '<?php echo getSetting('silver_making_charges_type', 'per_gram'); ?>', rate: '<?php echo getSetting('silver_making_charges_rate', '25.00'); ?>' },
        'platinum': { type: '<?php echo getSetting('platinum_making_charges_type', 'per_gram'); ?>', rate: '<?php echo getSetting('platinum_making_charges_rate', '750.00'); ?>' },
        'diamond': { type: '<?php echo getSetting('gold_making_charges_type', 'per_gram'); ?>', rate: '<?php echo getSetting('gold_making_charges_rate', '400.00'); ?>' },
        'other': { type: '<?php echo getSetting('default_making_charges_type', 'per_gram'); ?>', rate: '<?php echo getSetting('default_making_charges_rate', '400.00'); ?>' }
    };
    if (metalDefaults[metal]) {
        const typeEl = document.getElementById('making_charges_type');
        const rateEl = document.getElementById('making_charges');
        if (typeEl && rateEl) {
            typeEl.value = metalDefaults[metal].type;
            rateEl.value = metalDefaults[metal].rate;
        }
    }

    // Trigger code generation
    triggerCodeGeneration();
}

// Trigger AJAX code generation
function triggerCodeGeneration() {
    const metal = document.getElementById('selectedMetalType').value;
    const subcatSelect = document.getElementById('subcategorySelect');
    const subcatText = subcatSelect.selectedOptions[0]?.text || '';

    fetch(`<?php echo url('modules/inventory/ajax_generate_code.php'); ?>?metal=${encodeURIComponent(metal)}&subcat=${encodeURIComponent(subcatText)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('productCodeInput').value = data.product_code;
                document.getElementById('barcodeInput').value = data.barcode;
            }
        })
        .catch(err => {
            console.warn('Code gen fallback:', err);
            // Fallback code
            const prefix = metal.toUpperCase().substring(0, 3);
            const rnd = Math.floor(Math.random() * 900) + 100;
            document.getElementById('productCodeInput').value = `${prefix}-${rnd}`;
        });
}

function enableCodeEdit() {
    const inp = document.getElementById('productCodeInput');
    inp.removeAttribute('readonly');
    inp.focus();
    inp.select();
}

// Run code generation and initial category selection on page load
document.addEventListener('DOMContentLoaded', () => {
    const goldCard = document.querySelector('.category-card[data-metal="gold"]');
    if (goldCard) {
        selectMetalCategory('gold', goldCard);
    } else {
        triggerCodeGeneration();
    }
});
</script>

<style>
.category-card {
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.cursor-pointer {
    cursor: pointer;
}
.btn-xs {
    padding: 0.25rem 0.6rem;
    font-size: 0.75rem;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
