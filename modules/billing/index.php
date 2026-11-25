<?php
/**
 * Billing / POS Module
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Billing / POS';
$pdo = getDBConnection();

// Fetch live metal rates
$metalRates = getCurrentMetalRates();

// Fetch categories for manual items
$categoriesStmt = $pdo->query("SELECT id, name, metal_type FROM categories WHERE is_active = 1 ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll();

// Fetch all available products in inventory to display directly on the billing counter!
$productsStmt = $pdo->query("SELECT p.*, c.name as category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.is_active = 1 
                             ORDER BY p.stock_quantity > 0 DESC, p.metal_type ASC, p.name ASC");
$inventoryProducts = $productsStmt->fetchAll();

// Fetch customers for quick selection
$customersStmt = $pdo->query("SELECT id, name, phone, gstin, address, outstanding_balance FROM customers WHERE is_active = 1 ORDER BY name ASC LIMIT 50");
$recentCustomers = $customersStmt->fetchAll();

// Generate next invoice number for display
$nextInvoiceNo = generateInvoiceNumber();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<form action="<?php echo url('modules/billing/save_bill.php'); ?>" method="POST" id="billingForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

    <!-- Top Action & Meta Bar -->
    <div class="row align-items-center mb-3 mb-md-4">
        <!-- Desktop Meta Bar (> 992px) -->
        <div class="col-md-6 d-none d-lg-block">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-primary fs-6 px-3 py-2 shadow-xs">
                    <i class="bi bi-receipt me-1"></i> Invoice: <?php echo $nextInvoiceNo; ?>
                </span>
                <span class="badge bg-success fs-6 px-3 py-2 shadow-xs" id="topBillTypeBadge">
                    <i class="bi bi-shield-check me-1"></i> Tax Invoice (With GST)
                </span>
                <span class="text-muted fs-sm ms-2">
                    <i class="bi bi-calendar3 me-1"></i> <?php echo date('d M Y'); ?>
                </span>
            </div>
        </div>
        <div class="col-md-6 mt-2 mt-md-0 d-none d-lg-block">
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">
                <button type="button" class="btn btn-outline-dark shadow-sm" data-bs-toggle="modal"
                    data-bs-target="#qrScannerModal" onclick="openQrScannerModal()">
                    <i class="bi bi-qr-code-scan text-warning me-1"></i> Scan QR Tag
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetBillingForm()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary shadow-sm" id="btnSubmitBill">
                    <i class="bi bi-check-circle-fill me-1"></i> Finalize Bill
                </button>
            </div>
        </div>

        <!-- Sleek Modern Mobile Bar (<= 992px) - Clean Text Perfectly Fitted on Phone -->
        <div class="col-12 d-lg-none">
            <div class="d-flex align-items-center justify-content-between px-0 py-1 mb-2 text-nowrap"
                style="font-size: 11px;">
                <span class="fw-bold text-dark font-monospace d-flex align-items-center">
                    <i class="bi bi-receipt text-primary me-1"></i><?php echo $nextInvoiceNo; ?>
                </span>
                <span class="text-muted opacity-50 px-1">•</span>
                <span class="fw-semibold text-success d-flex align-items-center" id="topBillTypeBadgeMobile">
                    <i class="bi bi-shield-check me-1"></i>GST (3%)
                </span>
                <span class="text-muted opacity-50 px-1">•</span>
                <span class="text-muted d-flex align-items-center">
                    <i class="bi bi-calendar3 text-secondary me-1"></i><?php echo date('d M Y'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Mobile Billing Step Wizard Indicator (d-lg-none) -->
    <div class="mobile-billing-step-indicator d-lg-none mb-3" id="mobileStepIndicator">
        <div class="mobile-step-item active" id="stepIndicator1" onclick="goToMobileBillingStep(1)">
            <div class="mobile-step-circle"><i class="bi bi-qr-code"></i></div>
            <div class="mobile-step-label">1. Scan Product</div>
        </div>
        <div class="mobile-step-divider"></div>
        <div class="mobile-step-item" id="stepIndicator2" onclick="goToMobileBillingStep(2)">
            <div class="mobile-step-circle"><i class="bi bi-person"></i></div>
            <div class="mobile-step-label">2. Customer Info</div>
        </div>
        <div class="mobile-step-divider"></div>
        <div class="mobile-step-item" id="stepIndicator3" onclick="goToMobileBillingStep(3)">
            <div class="mobile-step-circle"><i class="bi bi-receipt"></i></div>
            <div class="mobile-step-label">3. Bill & Pay</div>
        </div>
    </div>

    <!-- Metal Rates Quick Reference Bar -->
    <div class="card mb-4" style="background: #F9FAFB; border: 1px dashed var(--border);">
        <div class="card-body py-2">
            <div
                class="d-flex align-items-center justify-content-between flex-wrap gap-2 text-nowrap fs-sm metal-rates-bar">
                <div class="fw-bold text-primary"><i class="bi bi-lightning-charge-fill text-warning"></i> Current Metal
                    Rates (per g):</div>
                <div>Gold 24K: <strong
                        id="rate_display_gold_24k">₹<?php echo number_format($metalRates['gold_24k'] ?? 0, 2); ?></strong>
                </div>
                <div>Gold 22K: <strong
                        id="rate_display_gold_22k">₹<?php echo number_format($metalRates['gold_22k'] ?? 0, 2); ?></strong>
                </div>
                <div>Gold 18K: <strong
                        id="rate_display_gold_18k">₹<?php echo number_format($metalRates['gold_18k'] ?? 0, 2); ?></strong>
                </div>
                <div>Silver 999: <strong
                        id="rate_display_silver_999">₹<?php echo number_format($metalRates['silver_999'] ?? 0, 2); ?></strong>
                </div>
                <div>Platinum: <strong
                        id="rate_display_platinum">₹<?php echo number_format($metalRates['platinum'] ?? 0, 2); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="gold_rate_24k" id="rate_input_gold_24k"
        value="<?php echo $metalRates['gold_24k'] ?? 0; ?>">
    <input type="hidden" name="gold_rate_22k" id="rate_input_gold_22k"
        value="<?php echo $metalRates['gold_22k'] ?? 0; ?>">
    <input type="hidden" name="gold_rate_18k" id="rate_input_gold_18k"
        value="<?php echo $metalRates['gold_18k'] ?? 0; ?>">
    <input type="hidden" name="silver_rate" id="rate_input_silver"
        value="<?php echo $metalRates['silver_999'] ?? 0; ?>">

    <div class="row g-4">
        <!-- Left: Customer Information, Available Products Catalogue & Billing Items -->
        <div class="col-lg-8">

            <!-- =============================================================== -->
            <!-- STEP 2 (MOBILE): CUSTOMER DETAILS                               -->
            <!-- =============================================================== -->
            <div id="billingStepCustomer" class="billing-step-content">
                <!-- 1. Customer Selection Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Customer Details</h6>
                            <span
                                class="badge bg-light text-muted border fw-normal d-none d-sm-inline-flex align-items-center">
                                <i class="bi bi-magic text-primary me-1"></i>Auto-saves customer to system
                            </span>
                        </div>
                        <button type="button"
                            class="btn btn-sm btn-outline-primary fw-medium px-3 py-1 d-flex align-items-center gap-1 shadow-xs"
                            id="btnWalkInCustomer" onclick="selectWalkInCustomer()">
                            <i class="bi bi-person-walking"></i> Walk-in Customer
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search / Select Customer</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="customerSearchInput"
                                        placeholder="Type customer name or mobile..." autocomplete="off">
                                    <input type="hidden" name="customer_id" id="selectedCustomerId">
                                    <div id="customerSearchResults" class="list-group position-absolute w-100 shadow-lg"
                                        style="z-index: 1050; max-height: 220px; overflow-y: auto; display: none;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_name" id="cust_name" required
                                    placeholder="Enter customer full name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="customer_phone" id="cust_phone" required
                                    placeholder="Mobile number">
                                <div id="cust_lookup_status" class="mt-1 fs-xs" style="display: none;"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Customer GSTIN (Optional)</label>
                                <input type="text" class="form-control" name="customer_gstin" id="cust_gstin"
                                    placeholder="GSTIN if registered">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Outstanding Balance</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="text" class="form-control bg-light text-danger fw-bold"
                                        id="cust_outstanding" readonly value="0.00">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="customer_address" id="cust_address"
                                    placeholder="Billing address">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- STEP 1 (MOBILE): DIRECT CAMERA QR SCANNER & SCANNED PRODUCTS   -->
            <!-- =============================================================== -->
            <div id="billingStepScan" class="billing-step-content active">

                <!-- Mobile Direct Camera Viewfinder Card -->
                <div class="card mb-3 mobile-direct-scanner-card d-lg-none shadow-sm border overflow-hidden">
                    <div
                        class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-danger"
                                style="width: 8px; height: 8px;"></span>
                            <span class="fw-bold fs-sm"><i class="bi bi-qr-code-scan text-warning me-1"></i> Live Camera
                                Viewfinder</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 fs-xs"
                            onclick="restartMobileCameraManual()" title="Restart Camera">
                            <i class="bi bi-arrow-clockwise"></i> Retry
                        </button>
                    </div>
                    <div class="card-body p-2 bg-black text-center position-relative" style="min-height: 220px;">
                        <div id="mobileDirectQrReader"
                            style="width: 100%; max-width: 320px; margin: 0 auto; overflow: hidden; border-radius: 8px;">
                        </div>
                        <div id="mobileDirectLoading"
                            class="position-absolute top-50 start-50 translate-middle text-white text-center w-100 px-3">
                            <div class="spinner-border text-warning spinner-border-sm mb-2" role="status"></div>
                            <div class="fs-xs text-white-50">Starting camera viewfinder...</div>
                        </div>
                    </div>
                    <div class="p-2 bg-light border-top">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-upc-scan text-muted"></i></span>
                            <input type="text" id="mobileDirectCodeInput" class="form-control"
                                placeholder="Scan Gun / Enter Code (e.g. GLD-001)"
                                onkeydown="if(event.key==='Enter'){event.preventDefault();submitMobileDirectCode();}">
                            <button type="button" class="btn btn-sm btn-primary px-3 fw-bold"
                                onclick="submitMobileDirectCode()">+ Add</button>
                        </div>
                        <div id="mobileDirectScanFeedback" class="mt-2" style="display: none;"></div>
                    </div>
                </div>

                <!-- Scanned Products List Right Below Camera -->
                <div class="card mb-3 shadow-sm border d-lg-none" id="mobileScannedProductsArea">
                    <div
                        class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                        <h6 class="mb-0 fw-bold fs-sm text-dark"><i class="bi bi-bag-check-fill text-success me-1"></i>
                            Scanned Products</h6>
                        <span class="badge bg-primary" id="mobileScannedCountBadge">0 Items</span>
                    </div>
                    <div class="card-body p-2" id="mobileScannedProductsContainer">
                        <div class="text-center py-4 text-muted border rounded bg-light" id="mobileScannedEmptyState">
                            <i class="bi bi-qr-code fs-2 text-secondary d-block mb-1"></i>
                            <div class="fw-semibold fs-sm">No jewellery scanned yet</div>
                            <div class="fs-xs text-muted">Point camera at QR tag or enter code above.</div>
                        </div>
                    </div>
                </div>

                <!-- 2. QUICK JEWELLERY INVENTORY PICKER (MINIMAL COGNITIVE LOAD) - HIDDEN ON MOBILE AS REQUESTED -->
                <div class="card mb-4 shadow-sm border d-none d-lg-block" style="border-color: #E5E7EB !important;">

                    <!-- Single Unified Header: Search, Metal Filters & Custom Button -->
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

                            <!-- Left: Search Box & QR Scan Button -->
                            <div class="d-flex align-items-center gap-2 flex-grow-1"
                                style="max-width: 440px; min-width: 260px;">
                                <div class="position-relative flex-grow-1">
                                    <i class="bi bi-search position-absolute text-muted"
                                        style="left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px;"></i>
                                    <input type="text" class="form-control form-control-sm ps-5 py-2"
                                        id="catalogueSearchInput" placeholder="Search jewellery by name, code..."
                                        oninput="filterCatalogue()" autocomplete="off">
                                </div>
                                <button type="button"
                                    class="btn btn-sm btn-dark py-2 px-3 fw-bold text-nowrap d-flex align-items-center gap-1 shadow-sm"
                                    data-bs-toggle="modal" data-bs-target="#qrScannerModal"
                                    onclick="openQrScannerModal()">
                                    <i class="bi bi-qr-code-scan text-warning"></i> Scan QR
                                </button>
                            </div>

                            <!-- Center: Clean Minimal Metal Filter Pills -->
                            <div class="d-flex align-items-center gap-1 flex-wrap mobile-scroll-pills"
                                id="metalFilterTabs">
                                <button type="button" class="btn btn-sm btn-primary py-1 px-3 rounded-pill fw-semibold"
                                    onclick="filterByMetal('all', this)">All</button>
                                <button type="button"
                                    class="btn btn-sm btn-light border py-1 px-3 rounded-pill fw-medium text-dark"
                                    onclick="filterByMetal('gold', this)"><span class="text-warning">●</span>
                                    Gold</button>
                                <button type="button"
                                    class="btn btn-sm btn-light border py-1 px-3 rounded-pill fw-medium text-dark"
                                    onclick="filterByMetal('diamond', this)"><span class="text-info">●</span>
                                    Diamond</button>
                                <button type="button"
                                    class="btn btn-sm btn-light border py-1 px-3 rounded-pill fw-medium text-dark"
                                    onclick="filterByMetal('silver', this)"><span class="text-secondary">●</span>
                                    Silver</button>
                                <button type="button"
                                    class="btn btn-sm btn-light border py-1 px-3 rounded-pill fw-medium text-dark"
                                    onclick="filterByMetal('platinum', this)"><span class="text-primary">●</span>
                                    Platinum</button>
                            </div>

                            <!-- Right: Actions & View Switcher -->
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary active" id="btnViewList"
                                        onclick="switchCatalogView('list')" title="List View">
                                        <i class="bi bi-list-ul"></i> List
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btnViewGrid"
                                        onclick="switchCatalogView('grid')" title="Grid View">
                                        <i class="bi bi-grid-fill"></i> Grid
                                    </button>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success py-1 px-3"
                                    onclick="addCustomItemRow()">
                                    <i class="bi bi-plus-lg me-1"></i> Custom Item
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 1. COMPACT TABLE VIEW (DEFAULT - Zero Cognitive Load, Clean Vertical Alignment) -->
                    <div id="catalogListView" class="table-responsive" style="max-height: 330px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0 fs-sm">
                            <thead class="table-light sticky-top" style="z-index: 5;">
                                <tr>
                                    <th style="width: 45px;" class="text-center">#</th>
                                    <th style="min-width: 220px;">Jewellery Item & Code</th>
                                    <th style="width: 140px;">Metal & Purity</th>
                                    <th style="width: 120px;" class="text-end">Net Weight</th>
                                    <th style="width: 120px;" class="text-center">Stock</th>
                                    <th style="width: 110px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="catalogTableBody">
                                <?php if (empty($inventoryProducts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 text-secondary d-block mb-1"></i>
                                            <div class="fw-semibold">No stock products registered yet</div>
                                            <small class="text-muted">You can add products in Inventory or click <strong>"+
                                                    Custom Item"</strong> to bill custom jewellery.</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $pIdx = 1;
                                    foreach ($inventoryProducts as $prod):
                                        $prodJson = htmlspecialchars(json_encode([
                                            'id' => $prod['id'],
                                            'product_code' => $prod['product_code'],
                                            'barcode' => $prod['barcode'],
                                            'name' => $prod['name'],
                                            'metal_type' => $prod['metal_type'],
                                            'purity' => $prod['purity'],
                                            'gross_weight' => (float) $prod['gross_weight'],
                                            'net_weight' => (float) $prod['net_weight'],
                                            'stone_weight' => (float) $prod['stone_weight'],
                                            'making_charges' => (float) $prod['making_charges'],
                                            'making_charges_type' => $prod['making_charges_type'],
                                            'wastage_percentage' => 0,
                                            'stone_charges' => 0,
                                            'selling_price' => (float) $prod['selling_price'],
                                            'stock_quantity' => (int) $prod['stock_quantity']
                                        ]), ENT_QUOTES, 'UTF-8');

                                        $metalPillClass = $prod['metal_type'] === 'gold' ? 'bg-warning-subtle text-dark border-warning' : ($prod['metal_type'] === 'diamond' ? 'bg-info-subtle text-dark border-info' : ($prod['metal_type'] === 'platinum' ? 'bg-primary-subtle text-primary border-primary' : 'bg-secondary-subtle text-dark border-secondary'));
                                        ?>
                                        <tr class="catalogue-item catalogue-row" data-metal="<?php echo $prod['metal_type']; ?>"
                                            data-name="<?php echo strtolower($prod['name'] . ' ' . $prod['product_code']); ?>"
                                            style="cursor: pointer;" onclick='addProductFromCatalog(<?php echo $prodJson; ?>)'>
                                            <td class="text-center text-muted fs-xs"><?php echo $pIdx++; ?></td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?php echo sanitize($prod['name']); ?></div>
                                                <span
                                                    class="text-muted fs-xs font-monospace"><?php echo sanitize($prod['product_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge border <?php echo $metalPillClass; ?> fw-medium px-2 py-1">
                                                    <?php echo ucfirst($prod['metal_type']); ?>
                                                    <?php echo sanitize($prod['purity']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span
                                                    class="fw-bold text-dark"><?php echo formatWeight($prod['net_weight']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($prod['stock_quantity'] > 0): ?>
                                                    <span
                                                        class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                        <?php echo $prod['stock_quantity']; ?> in stock
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger px-2 py-1">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-nowrap py-2" onclick="event.stopPropagation();">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary px-3 py-1 rounded-pill fw-bold text-nowrap d-inline-flex align-items-center justify-content-center gap-1 shadow-xs"
                                                    style="white-space: nowrap !important; height: 32px; min-width: 84px;"
                                                    onclick='addProductFromCatalog(<?php echo $prodJson; ?>)'>
                                                    <i class="bi bi-plus-lg fs-6"></i> Add
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 2. STREAMLINED GRID VIEW (Alternative - Full Titles, Clean Alignment) -->
                    <div id="catalogGridView" class="card-body p-3"
                        style="max-height: 350px; overflow-y: auto; display: none;">
                        <div class="row g-3">
                            <?php foreach ($inventoryProducts as $prod):
                                $prodJson = htmlspecialchars(json_encode([
                                    'id' => $prod['id'],
                                    'product_code' => $prod['product_code'],
                                    'barcode' => $prod['barcode'],
                                    'name' => $prod['name'],
                                    'metal_type' => $prod['metal_type'],
                                    'purity' => $prod['purity'],
                                    'gross_weight' => (float) $prod['gross_weight'],
                                    'net_weight' => (float) $prod['net_weight'],
                                    'stone_weight' => (float) $prod['stone_weight'],
                                    'making_charges' => (float) $prod['making_charges'],
                                    'making_charges_type' => $prod['making_charges_type'],
                                    'wastage_percentage' => 0,
                                    'stone_charges' => 0,
                                    'selling_price' => (float) $prod['selling_price'],
                                    'stock_quantity' => (int) $prod['stock_quantity']
                                ]), ENT_QUOTES, 'UTF-8');

                                $metalPillClass = $prod['metal_type'] === 'gold' ? 'bg-warning-subtle text-dark border-warning' : ($prod['metal_type'] === 'diamond' ? 'bg-info-subtle text-dark border-info' : ($prod['metal_type'] === 'platinum' ? 'bg-primary-subtle text-primary border-primary' : 'bg-secondary-subtle text-dark border-secondary'));
                                ?>
                                <div class="col-md-6 catalogue-item catalogue-card"
                                    data-metal="<?php echo $prod['metal_type']; ?>"
                                    data-name="<?php echo strtolower($prod['name'] . ' ' . $prod['product_code']); ?>">
                                    <div
                                        class="p-3 border rounded-3 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <div class="fw-bold text-dark fs-6" style="line-height: 1.3;">
                                                    <?php echo sanitize($prod['name']); ?>
                                                </div>
                                                <span
                                                    class="badge <?php echo $prod['stock_quantity'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger'; ?> text-nowrap fs-xs">
                                                    <?php echo $prod['stock_quantity']; ?> left
                                                </span>
                                            </div>
                                            <div class="d-flex gap-2 align-items-center mb-3">
                                                <span class="badge border <?php echo $metalPillClass; ?> fs-xs">
                                                    <?php echo ucfirst($prod['metal_type']); ?>
                                                    <?php echo sanitize($prod['purity']); ?>
                                                </span>
                                                <span
                                                    class="text-muted fs-xs font-monospace"><?php echo sanitize($prod['product_code']); ?></span>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                            <div>
                                                <small class="text-muted d-block fs-xs">Weight</small>
                                                <span
                                                    class="fw-bold text-dark"><?php echo formatWeight($prod['net_weight']); ?></span>
                                            </div>
                                            <button type="button"
                                                class="btn btn-sm btn-primary px-3 py-1 rounded-pill fw-bold text-nowrap d-inline-flex align-items-center gap-1"
                                                style="white-space: nowrap !important;"
                                                onclick='addProductFromCatalog(<?php echo $prodJson; ?>)'>
                                                <i class="bi bi-plus-lg"></i> Add to Bill
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /#billingStepScan -->

                <!-- =============================================================== -->
                <!-- STEP 3 (MOBILE): ITEMS IN BILL & NOTES                          -->
                <!-- =============================================================== -->
                <div id="billingStepCheckoutItems" class="billing-step-content">
                    <!-- 3. ITEMS IN BILL TABLE / SELECTED PRODUCTS                      -->
                    <div class="card mb-4" id="billItemsCard">
                        <div
                            class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-cart-check-fill me-2 text-success"></i>Items
                                Included in
                                Customer Bill</h6>
                            <span class="badge bg-primary" id="selectedItemsCountBadge">0 Items</span>
                        </div>
                        <div class="card-body p-0">
                            <div id="itemsContainer" class="p-3">
                                <!-- Dynamic Item Rows Injected Here -->
                            </div>

                            <div id="noItemsAlert" class="text-center py-5 text-muted">
                                <i class="bi bi-basket3 fs-1 text-light d-block mb-2"></i>
                                <h6>No items added to customer bill yet</h6>
                                <p class="fs-sm mb-3">Click <strong>"+ Add to Bill"</strong> on any product above, or
                                    add a
                                    custom item.</p>
                                <button type="button" class="btn btn-sm btn-outline-success"
                                    onclick="addCustomItemRow()">
                                    <i class="bi bi-plus-lg me-1"></i> Add Custom Jewellery Item
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Notes & Terms -->
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label"><i class="bi bi-card-text me-1"></i> Bill Remarks / Guarantee
                                Notes</label>
                            <textarea class="form-control" name="notes" rows="2"
                                placeholder="e.g. Certified 100% BIS Hallmarked jewellery. 1-year free polish & cleaning guarantee included."></textarea>
                        </div>
                    </div>
                </div><!-- /#billingStepCheckoutItems -->
            </div>

            <!-- Right: Billing Calculation & Payment Summary -->
            <div class="col-lg-4">
                <div id="billingStepCheckoutSummary" class="billing-step-content">
                    <div class="card sticky-top" style="top: 80px;" id="billingSummaryCard">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0 fw-bold text-white"><i class="bi bi-calculator me-2"></i>Bill Summary</h6>
                        </div>
                        <div class="card-body">

                            <!-- Sub-calculations: Metal Value + Working Charges = Gross Amount -->
                            <div class="bill-summary p-3 mb-3 bg-light rounded">
                                <div class="summary-row">
                                    <span class="text-muted">Metal Value:</span>
                                    <span class="fw-bold" id="disp_metal_value">₹0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span class="text-muted">Working Charges:</span>
                                    <span class="fw-bold text-primary" id="disp_making_charges">₹0.00</span>
                                </div>
                                <div class="summary-row border-top pt-2 mt-2">
                                    <span class="fw-semibold">Gross Amount:</span>
                                    <span class="fw-bold text-dark fs-6" id="disp_gross_subtotal">₹0.00</span>
                                </div>
                            </div>

                            <!-- Bill Type Selector: GST vs Non-GST (Estimate) -->
                            <?php
                            $presetBillType = $_GET['type'] ?? '';
                            $isDefaultNonGst = in_array($presetBillType, ['non_gst', 'without_gst', 'estimate']);
                            ?>
                            <div class="card mb-3 border-2 <?php echo $isDefaultNonGst ? 'border-secondary-subtle' : 'border-success-subtle'; ?> shadow-xs"
                                id="billTypeCard">
                                <div class="card-body p-2 bg-light rounded">
                                    <label
                                        class="form-label fs-xs fw-bold text-uppercase text-muted mb-2 d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-receipt-cutoff me-1 text-primary"></i>Bill Option:</span>
                                        <span
                                            class="badge <?php echo $isDefaultNonGst ? 'bg-secondary' : 'bg-success'; ?>"
                                            id="billTypeBadge">
                                            <?php echo $isDefaultNonGst ? 'Non-GST (0% Tax)' : 'GST Bill (3%)'; ?>
                                        </span>
                                    </label>
                                    <div class="btn-group w-100" role="group" aria-label="Bill Type Selection">
                                        <input type="radio" class="btn-check" name="bill_type" id="billTypeWithGst"
                                            value="with_gst" <?php echo !$isDefaultNonGst ? 'checked' : ''; ?>
                                            onchange="onBillTypeChange()">
                                        <label class="btn btn-outline-success btn-sm py-2 fw-semibold"
                                            for="billTypeWithGst">
                                            <i class="bi bi-shield-check me-1"></i> GST Bill (Tax Invoice)
                                        </label>

                                        <input type="radio" class="btn-check" name="bill_type" id="billTypeWithoutGst"
                                            value="without_gst" <?php echo $isDefaultNonGst ? 'checked' : ''; ?>
                                            onchange="onBillTypeChange()">
                                        <label class="btn btn-outline-secondary btn-sm py-2 fw-semibold"
                                            for="billTypeWithoutGst">
                                            <i class="bi bi-file-earmark-text me-1"></i> Non-GST Bill (Estimate)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="mb-3">
                                <label class="form-label fs-sm d-flex justify-content-between">
                                    <span>Discount:</span>
                                    <span class="text-danger fw-bold" id="disp_discount_amount">-₹0.00</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <select class="form-select" name="discount_type" id="discountType"
                                        style="max-width: 100px;" onchange="calculateBillTotals()">
                                        <option value="fixed">Fixed (₹)</option>
                                        <option value="percentage">Percent (%)</option>
                                    </select>
                                    <input type="number" step="0.01" class="form-control" name="discount_value"
                                        id="discountValue" value="0" min="0" oninput="calculateBillTotals()">
                                </div>
                                <div class="mt-2" id="discountApprovalWrapper">
                                    <div id="discountApprovalStatusAlert" style="display: none;"></div>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-warning w-100 d-flex align-items-center justify-content-center gap-1"
                                        id="btnRequestDiscountApproval" onclick="requestDiscountApproval()">
                                        <i class="bi bi-shield-lock-fill"></i> Request Admin Approval for Discount
                                    </button>
                                </div>
                            </div>

                            <!-- Tax Section (With GST) -->
                            <div class="mb-3 p-2 border rounded" id="taxSectionContainer" style="background: #F9FAFB;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fs-sm mb-0 fw-semibold text-primary">GST (3%
                                        Jewellery)</label>
                                    <div class="form-check form-switch fs-sm" id="igstSwitchContainer">
                                        <input class="form-check-input" type="checkbox" name="is_igst" id="isIgst"
                                            onchange="calculateBillTotals()">
                                        <label class="form-check-label fs-xs" for="isIgst">IGST (Inter-state)</label>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between text-muted fs-sm">
                                    <span id="tax_breakdown_label">CGST (1.5%) + SGST (1.5%):</span>
                                    <span class="fw-bold text-dark" id="disp_tax_amount">₹0.00</span>
                                </div>
                            </div>

                            <!-- Without GST Notice Badge (Shown when Without GST is active) -->
                            <div class="alert alert-secondary py-2 px-3 mb-3 fs-xs" id="nonGstNotice"
                                style="display: none;">
                                <i class="bi bi-info-circle-fill me-1 text-primary"></i> <strong>Retail Estimate /
                                    Memo:</strong> Standard pricing applied.
                            </div>

                            <!-- Total Payable -->
                            <div class="p-3 bg-primary-light rounded mb-3 text-center border border-primary">
                                <div class="fs-xs text-uppercase text-muted fw-bold">Total Payable Amount</div>
                                <div class="fs-2 fw-bold text-primary" id="disp_final_amount">₹0.00</div>
                                <div class="fs-xs text-muted" id="disp_round_off">(Round off: ₹0.00)</div>
                            </div>

                            <div class="mb-3 p-2 bg-light rounded fs-xs text-muted" id="disp_amount_in_words">
                                Zero Rupees Only
                            </div>

                            <hr>

                            <!-- Payment Section -->
                            <h6 class="fw-bold mb-3 fs-sm"><i class="bi bi-wallet2 me-1 text-primary"></i>Payment
                                Details</h6>

                            <div class="mb-3">
                                <label class="form-label fs-sm">Payment Method</label>
                                <select class="form-select form-select-sm" name="payment_method" id="paymentMethod">
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                                    <option value="card">Debit / Credit Card</option>
                                    <option value="bank_transfer">Bank Transfer (NEFT / RTGS)</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-sm">Transaction / Reference # (Optional)</label>
                                <input type="text" class="form-control form-control-sm" name="payment_reference"
                                    placeholder="e.g. UTR / Txn ID">
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fs-sm">Amount Paid (₹)</label>
                                    <input type="number" step="1"
                                        class="form-control form-control-sm fw-bold text-success" name="paid_amount"
                                        id="paidAmount" value="0" min="0" oninput="updateDueAmount()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-sm">Balance Due (₹)</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-danger bg-light"
                                        id="disp_due_amount" readonly value="0.00">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-printer-fill me-1"></i> Save & Generate Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div><!-- /#billingStepCheckoutSummary -->
            </div>
        </div>

        <!-- Mobile Step 1 Action Bar: Direct Next to Customer -->
        <div class="mobile-step-action-bar d-lg-none" id="mobileStepBar1">
            <div class="pe-2" style="min-width: 0;">
                <div class="fs-xs text-muted fw-semibold">Scanned: <span id="mobileStep1Count"
                        class="text-primary fw-bold">0</span> items</div>
                <div class="fs-6 fw-bold text-dark text-truncate" id="mobileStep1Total">₹0.00</div>
            </div>
            <button type="button"
                class="btn btn-primary fw-bold px-3 py-2 d-flex align-items-center gap-1 shadow-sm text-nowrap"
                onclick="goToMobileBillingStep(2)">
                Next: Customer Info <i class="bi bi-arrow-right"></i>
            </button>
        </div>

        <!-- Mobile Step 2 Action Bar: Back to Scan & Next to Bill -->
        <div class="mobile-step-action-bar d-lg-none" id="mobileStepBar2" style="display: none;">
            <button type="button"
                class="btn btn-outline-secondary fw-semibold px-3 py-2 d-flex align-items-center gap-1 text-nowrap"
                onclick="goToMobileBillingStep(1)">
                <i class="bi bi-arrow-left"></i> Back to Scan
            </button>
            <button type="button"
                class="btn btn-primary fw-bold px-3 py-2 d-flex align-items-center gap-1 shadow-sm text-nowrap"
                onclick="goToMobileBillingStep(3)">
                Next: Bill & Pay <i class="bi bi-arrow-right"></i>
            </button>
        </div>

        <!-- Mobile Step 3 Action Bar: Back to Customer & Finalize -->
        <div class="mobile-step-action-bar d-lg-none" id="mobileStepBar3" style="display: none;">
            <button type="button"
                class="btn btn-outline-secondary fw-semibold px-3 py-2 d-flex align-items-center gap-1 text-nowrap"
                onclick="goToMobileBillingStep(2)">
                <i class="bi bi-arrow-left"></i> Customer
            </button>
            <button type="button"
                class="btn btn-success fw-bold px-3 py-2 d-flex align-items-center gap-1 shadow-sm text-nowrap"
                onclick="submitBillMobile()">
                <i class="bi bi-check-circle-fill"></i> Finalize Bill
            </button>
        </div>
</form>

<!-- ========================================================================= -->
<!-- Modal: QR Code & Barcode Scanner for Instant Product Billing             -->
<!-- ========================================================================= -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <div
                        style="width: 36px; height: 36px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-qr-code-scan text-warning fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold text-white" id="qrScannerModalLabel">Scan Product QR Code
                        </h6>
                        <small class="text-white-50 fs-xs">Point camera at jewellery tag or scan with barcode
                            gun</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">

                <!-- Camera Viewfinder Window -->
                <div class="position-relative bg-black rounded-3 overflow-hidden mb-3 text-center"
                    style="min-height: 240px; border: 2px dashed #4B5563;">
                    <div id="qrReader" style="width: 100%;"></div>
                    <div id="qrCameraLoading" class="py-5 text-white">
                        <div class="spinner-border text-warning spinner-border-sm mb-2" role="status"></div>
                        <div class="fs-xs text-white-50">Initializing camera scanner...</div>
                    </div>
                </div>

                <!-- Live Status & Feedback -->
                <div id="qrScanFeedback" class="alert alert-secondary py-2 px-3 fs-xs mb-3 text-center fw-medium">
                    <i class="bi bi-camera me-1 text-primary"></i> Align jewellery tag QR code within camera frame
                </div>

                <!-- Hardware Barcode Gun / Manual Code Input -->
                <div class="card bg-light border mb-3">
                    <div class="card-body p-2">
                        <label
                            class="form-label fs-xs fw-bold text-muted mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-upc-scan me-1 text-primary"></i>Barcode Gun / Type Product
                                Code:</span>
                            <span class="badge bg-secondary font-monospace" style="font-size: 10px;">USB Gun
                                Ready</span>
                        </label>
                        <form id="manualScanForm" onsubmit="handleManualScan(event)">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i
                                        class="bi bi-barcode text-primary"></i></span>
                                <input type="text" class="form-control" id="manualScanInput"
                                    placeholder="Scan barcode gun or type code (e.g. GN-001)..." autocomplete="off">
                                <button type="submit" class="btn btn-primary px-3 fw-bold">
                                    <i class="bi bi-plus-lg me-1"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 1-Click Demo QR Simulator & Continuous Mode -->
                <div class="pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted fs-xs fw-bold text-uppercase"><i
                                class="bi bi-lightning-charge text-warning"></i> 1-Click Demo Test Tags:</small>
                        <div class="form-check form-switch fs-xs mb-0">
                            <input class="form-check-input" type="checkbox" id="continuousScanToggle" checked>
                            <label class="form-check-label fs-xs" for="continuousScanToggle">Multi-item Scan</label>
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-xs"
                            onclick="simulateScanCode('GN-001')">🏷️ GN-001 (Gold Necklace)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-xs"
                            onclick="simulateScanCode('GR-002')">🏷️ GR-002 (Gold Ring)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-xs"
                            onclick="simulateScanCode('GB-003')">🏷️ GB-003 (Gold Bangle)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-xs"
                            onclick="simulateScanCode('DR-006')">🏷️ DR-006 (Diamond Ring)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 fs-xs"
                            onclick="simulateScanCode('SR-008')">🏷️ SR-008 (Silver Ring)</button>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-light py-2">
                <span class="fs-xs text-muted me-auto"><i class="bi bi-shield-check text-success me-1"></i>Supports
                    Phone Cam, Webcam & Barcode Guns</span>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Customer -->
<div class="modal fade" id="newCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCustomerForm">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="qc_name" required placeholder="e.g. Priya Patel">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="qc_phone" required
                            placeholder="10-digit mobile number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="qc_email" placeholder="customer@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="qc_address" rows="2"
                            placeholder="Street, locality, city"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" id="qc_city" placeholder="City">
                        </div>
                        <div class="col-6">
                            <label class="form-label">GSTIN (Optional)</label>
                            <input type="text" class="form-control" id="qc_gstin" placeholder="GST number">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitQuickCustomer()">
                    <i class="bi bi-check-lg me-1"></i> Save Customer
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = '<script src="' . url('assets/js/html5-qrcode.min.js') . '"></script>
<script>
// Pre-loaded inventory products for instantaneous QR matching
const INVENTORY_PRODUCTS = ' . json_encode(array_map(function ($p) {
    return [
        'id' => (int) $p['id'],
        'product_code' => $p['product_code'],
        'barcode' => $p['barcode'],
        'name' => $p['name'],
        'metal_type' => $p['metal_type'],
        'purity' => $p['purity'],
        'gross_weight' => (float) $p['gross_weight'],
        'net_weight' => (float) $p['net_weight'],
        'stone_weight' => (float) $p['stone_weight'],
        'making_charges' => (float) $p['making_charges'],
        'making_charges_type' => $p['making_charges_type'],
        'wastage_percentage' => 0,
        'stone_charges' => 0,
        'selling_price' => (float) $p['selling_price'],
        'stock_quantity' => (int) $p['stock_quantity']
    ];
}, $inventoryProducts)) . ';

// Metal rates data object
const METAL_RATES = {
    gold_24k: ' . (float) ($metalRates['gold_24k'] ?? 0) . ',
    gold_22k: ' . (float) ($metalRates['gold_22k'] ?? 0) . ',
    gold_18k: ' . (float) ($metalRates['gold_18k'] ?? 0) . ',
    silver_999: ' . (float) ($metalRates['silver_999'] ?? 0) . ',
    silver_925: ' . (float) ($metalRates['silver_925'] ?? 0) . ',
    platinum: ' . (float) ($metalRates['platinum'] ?? 0) . '
};

// Default working charges from admin settings
const DEFAULT_MAKING_CONFIG = {
    gold: { type: "' . getSetting('gold_making_charges_type', 'per_gram') . '", rate: ' . (float) getSetting('gold_making_charges_rate', 400.00) . ' },
    silver: { type: "' . getSetting('silver_making_charges_type', 'per_gram') . '", rate: ' . (float) getSetting('silver_making_charges_rate', 25.00) . ' },
    platinum: { type: "' . getSetting('platinum_making_charges_type', 'per_gram') . '", rate: ' . (float) getSetting('platinum_making_charges_rate', 750.00) . ' },
    default: { type: "' . getSetting('default_making_charges_type', 'per_gram') . '", rate: ' . (float) getSetting('default_making_charges_rate', 400.00) . ' }
};

let itemIndex = 0;
let html5QrCodeScanner = null;
let lastScannedCode = "";
let lastScanTime = 0;

// POS Scanner Beep sound using Web Audio API
function playScanBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = "sine";
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // 880Hz crisp beep
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.12);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch(e) {}
}

// ---------------- MOBILE BILLING 3-STEP WIZARD ENGINE ----------------
let currentMobileStep = 1;
let mobileQrInstance = null;
let isMobileScannerRunning = false;

function goToMobileBillingStep(step) {
    if (window.innerWidth > 992) return;
    currentMobileStep = step;

    // 1. Update Indicator Pills
    for (let i = 1; i <= 3; i++) {
        const ind = document.getElementById("stepIndicator" + i);
        if (!ind) continue;
        ind.classList.remove("active", "completed");
        if (i < step) {
            ind.classList.add("completed");
        } else if (i === step) {
            ind.classList.add("active");
        }
    }

    // 2. Manage step content visibility
    const stepScan = document.getElementById("billingStepScan");
    const stepCust = document.getElementById("billingStepCustomer");
    const stepItems = document.getElementById("billingStepCheckoutItems");
    const stepSum = document.getElementById("billingStepCheckoutSummary");

    if (stepScan) stepScan.classList.remove("active");
    if (stepCust) stepCust.classList.remove("active");
    if (stepItems) stepItems.classList.remove("active");
    if (stepSum) stepSum.classList.remove("active");

    // 3. Toggle bottom action bars
    const bar1 = document.getElementById("mobileStepBar1");
    const bar2 = document.getElementById("mobileStepBar2");
    const bar3 = document.getElementById("mobileStepBar3");
    if (bar1) bar1.style.display = "none";
    if (bar2) bar2.style.display = "none";
    if (bar3) bar3.style.display = "none";

    if (step === 1) {
        if (stepScan) stepScan.classList.add("active");
        if (bar1) bar1.style.display = "flex";
        startMobileDirectQRScanner();
        updateMobileScannedList();
    } else {
        stopMobileDirectQRScanner();
    }

    if (step === 2) {
        if (stepCust) stepCust.classList.add("active");
        if (bar2) bar2.style.display = "flex";
    }

    if (step === 3) {
        if (stepItems) stepItems.classList.add("active");
        if (stepSum) stepSum.classList.add("active");
        if (bar3) bar3.style.display = "flex";
    }

    window.scrollTo({ top: 0, behavior: "smooth" });
}

async function startMobileDirectQRScanner() {
    if (window.innerWidth > 992) return;
    const readerEl = document.getElementById("mobileDirectQrReader");
    const loadingEl = document.getElementById("mobileDirectLoading");
    if (!readerEl) return;
    if (isMobileScannerRunning) return;

    if (typeof Html5Qrcode === "undefined") {
        if (loadingEl) {
            loadingEl.innerHTML = \'<div class="text-warning fs-xs">Loading QR scanner engine...</div>\';
        }
        setTimeout(startMobileDirectQRScanner, 400);
        return;
    }

    try {
        if (!mobileQrInstance) {
            mobileQrInstance = new Html5Qrcode("mobileDirectQrReader");
        }

        isMobileScannerRunning = true;
        const config = {
            fps: 15,
            qrbox: { width: 200, height: 200 },
            aspectRatio: 1.0
        };

        let cameraParam = { facingMode: "environment" };
        try {
            const cameras = await Html5Qrcode.getCameras();
            if (cameras && cameras.length > 0) {
                const backCam = cameras.find(c => /back|rear|environment/i.test(c.label));
                cameraParam = backCam ? backCam.id : cameras[0].id;
            }
        } catch(cErr) {
            cameraParam = { facingMode: "environment" };
        }

        await mobileQrInstance.start(
            cameraParam,
            config,
            (decodedText) => {
                const now = Date.now();
                if (decodedText === lastScannedCode && (now - lastScanTime < 2000)) return;
                lastScannedCode = decodedText;
                lastScanTime = now;
                handleScannedQR(decodedText);
            },
            () => {}
        );
        if (loadingEl) loadingEl.style.display = "none";
    } catch(err) {
        isMobileScannerRunning = false;
        if (loadingEl) {
            loadingEl.style.display = "block";
            loadingEl.innerHTML = `
                <div class="p-2 text-warning fs-xs">
                    <i class="bi bi-camera-video-off fs-4 d-block mb-1"></i>
                    Camera stream not active in this session.<br>
                    <span class="text-white-50">Use Tag input or Quick Demo below!</span>
                    <div class="mt-2 d-flex justify-content-center gap-1 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2" onclick="handleMobileDirectTag(\'GLD-001\')">Tag: GLD-001</button>
                        <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2" onclick="handleMobileDirectTag(\'GLD-002\')">Tag: GLD-002</button>
                        <button type="button" class="btn btn-xs btn-outline-warning py-1 px-2" onclick="handleMobileDirectTag(\'DIA-001\')">Tag: DIA-001</button>
                    </div>
                </div>
            `;
        }
    }
}

async function stopMobileDirectQRScanner() {
    if (mobileQrInstance && isMobileScannerRunning) {
        try {
            await mobileQrInstance.stop();
        } catch(e) {}
        isMobileScannerRunning = false;
    }
}

async function restartMobileCameraManual() {
    await stopMobileDirectQRScanner();
    const loadingEl = document.getElementById("mobileDirectLoading");
    if (loadingEl) {
        loadingEl.style.display = "block";
        loadingEl.innerHTML = \'<div class="spinner-border text-warning spinner-border-sm mb-2" role="status"></div><div class="fs-xs text-white-50">Re-connecting camera...</div>\';
    }
    await new Promise(r => setTimeout(r, 200));
    startMobileDirectQRScanner();
}

function submitMobileDirectCode() {
    const input = document.getElementById("mobileDirectCodeInput");
    if (!input || !input.value.trim()) return;
    const code = input.value.trim();
    input.value = "";
    handleScannedQR(code);
}

function handleMobileDirectTag(code) {
    handleScannedQR(code);
}

function updateMobileScannedList() {
    const listContainer = document.getElementById("mobileScannedProductsContainer");
    const countBadge = document.getElementById("mobileScannedCountBadge");
    const step1Count = document.getElementById("mobileStep1Count");
    const step1Total = document.getElementById("mobileStep1Total");
    const items = document.querySelectorAll(".bill-item-row");

    if (countBadge) countBadge.innerText = `${items.length} ${items.length === 1 ? "Item" : "Items"}`;
    if (step1Count) step1Count.innerText = items.length;

    const totalEl = document.getElementById("disp_final_amount");
    const currentTotal = totalEl ? totalEl.innerText : "₹0.00";
    if (step1Total) step1Total.innerText = currentTotal;

    if (!listContainer) return;

    if (items.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center py-4 text-muted border rounded bg-light" id="mobileScannedEmptyState">
                <i class="bi bi-qr-code fs-2 text-secondary d-block mb-1"></i>
                <div class="fw-semibold fs-sm">No jewellery scanned yet</div>
                <div class="fs-xs text-muted">Point camera at QR code tag or enter code above.</div>
            </div>
        `;
        return;
    }

    let html = "";
    items.forEach((row) => {
        const idMatch = row.id.match(/\\d+$/);
        if (!idMatch) return;
        const idx = idMatch[0];

        const nameEl = row.querySelector("strong.text-dark");
        const name = nameEl ? nameEl.innerText : "Jewellery Item";

        const codeInput = row.querySelector(`input[name="items[${idx}][product_code]"]`);
        const code = codeInput ? codeInput.value : "";

        const purityEl = document.getElementById(`item_purity_${idx}`);
        const purity = purityEl ? purityEl.value : "";

        const netEl = document.getElementById(`item_net_${idx}`);
        const netWt = netEl ? parseFloat(netEl.value || 0).toFixed(3) : "0.000";

        const metalEl = document.getElementById(`item_metal_${idx}`);
        const metal = metalEl ? metalEl.value : "gold";

        const totalDisplay = document.getElementById(`item_total_display_${idx}`);
        const itemTotal = totalDisplay ? totalDisplay.value : "0.00";

        html += `
            <div class="mobile-scanned-item-card">
                <div class="item-info">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-xs">${code || "ITEM"}</span>
                        <strong class="text-dark fs-sm text-truncate d-inline-block" style="max-width: 180px;">${name}</strong>
                    </div>
                    <div class="text-muted fs-xs">
                        <span>${purity || metal}</span> • <span>${netWt}g</span> • <strong class="text-success">₹${itemTotal}</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeItemRow(${idx})" title="Remove item">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
    });

    listContainer.innerHTML = html;
}

// ---------------- BULLETPROOF QR SCANNER ENGINE ----------------
let qrScannerInstance = null;
let isScannerStarting = false;
let isScannerStopping = false;

// Force kill and release all active media stream tracks
function forceReleaseAllMediaTracks() {
    try {
        const videos = document.querySelectorAll("#qrReader video, video");
        videos.forEach(v => {
            if (v.srcObject && typeof v.srcObject.getTracks === "function") {
                v.srcObject.getTracks().forEach(track => {
                    try { track.stop(); } catch(e) {}
                });
                v.srcObject = null;
            }
        });
    } catch(e) {}
}

function openQrScannerModal() {
    const feedback = document.getElementById("qrScanFeedback");
    if (feedback) {
        feedback.className = "alert alert-secondary py-2 px-3 fs-xs mb-3 text-center fw-medium";
        feedback.innerHTML = `<i class="bi bi-camera me-1 text-primary"></i> Align jewellery tag QR code within camera frame`;
    }
    const input = document.getElementById("manualScanInput");
    if (input) {
        input.value = "";
    }
    // Camera start is triggered reliably once modal transition finishes (shown.bs.modal)
}

async function stopQRScanner() {
    if (isScannerStopping) return;
    isScannerStopping = true;

    const loading = document.getElementById("qrCameraLoading");
    if (loading) loading.style.display = "none";

    if (qrScannerInstance) {
        const scanner = qrScannerInstance;
        qrScannerInstance = null;
        try {
            await scanner.stop();
        } catch(e) {
            console.warn("Scanner stop issue (safely caught):", e);
        }
    }

    // Always release any video tracks to prevent hardware camera locking
    forceReleaseAllMediaTracks();

    const reader = document.getElementById("qrReader");
    if (reader) reader.innerHTML = "";

    isScannerStopping = false;
    isScannerStarting = false;
}

async function startQRScanner() {
    if (isScannerStarting) return;
    isScannerStarting = true;

    const loading = document.getElementById("qrCameraLoading");
    const reader = document.getElementById("qrReader");

    // Clean up any existing scanner instance or lingering camera tracks
    if (qrScannerInstance || isScannerStopping) {
        await stopQRScanner();
        await new Promise(r => setTimeout(r, 150));
    }

    if (reader) reader.innerHTML = "";
    if (loading) {
        loading.style.display = "block";
        loading.innerHTML = `
            <div class="spinner-border text-warning spinner-border-sm mb-2" role="status"></div>
            <div class="fs-xs text-white-50">Connecting to camera...</div>
        `;
    }

    if (typeof Html5Qrcode === "undefined") {
        if (loading) {
            loading.innerHTML = `<div class="p-3 text-warning fs-xs">Scanner library not ready. Please reload page.</div>`;
        }
        isScannerStarting = false;
        return;
    }

    try {
        qrScannerInstance = new Html5Qrcode("qrReader");

        // Scan configuration
        const config = {
            fps: 15,
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                const edge = Math.floor(minEdge * 0.7);
                return { width: Math.max(edge, 180), height: Math.max(edge, 180) };
            },
            aspectRatio: 1.333
        };

        const onScanSuccess = (decodedText) => {
            const now = Date.now();
            if (decodedText === lastScannedCode && (now - lastScanTime < 2000)) {
                return;
            }
            lastScannedCode = decodedText;
            lastScanTime = now;
            handleScannedQR(decodedText);
        };

        const onScanFailure = (errorMessage) => {
            // Ignore frame-by-frame non-match
        };

        // Determine best camera device (support mobile rear cam, external webcam, or default)
        let cameraParam = { facingMode: "environment" };
        try {
            const cameras = await Html5Qrcode.getCameras();
            if (cameras && cameras.length > 0) {
                const backCam = cameras.find(c => /back|rear|environment/i.test(c.label));
                cameraParam = backCam ? backCam.id : cameras[0].id;
            }
        } catch(camErr) {
            cameraParam = { facingMode: "environment" };
        }

        await qrScannerInstance.start(cameraParam, config, onScanSuccess, onScanFailure);

        if (loading) loading.style.display = "none";
    } catch(err) {
        console.error("QR Camera start error:", err);
        forceReleaseAllMediaTracks();
        if (loading) {
            loading.style.display = "block";
            loading.innerHTML = `
                <div class="p-3 text-warning fs-xs">
                    <i class="bi bi-camera-video-off fs-4 d-block mb-1"></i>
                    Camera busy or access permission needed.<br>
                    <button type="button" class="btn btn-sm btn-outline-warning mt-2" onclick="restartCameraManual()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Re-connect Camera
                    </button>
                    <div class="mt-2 text-white-50">Or use <strong>Barcode Gun / 1-Click Demo Tags</strong> below!</div>
                </div>
            `;
        }
    } finally {
        isScannerStarting = false;
    }
}

async function restartCameraManual() {
    await stopQRScanner();
    await new Promise(r => setTimeout(r, 200));
    startQRScanner();
}

document.addEventListener("DOMContentLoaded", () => {
    const modalEl = document.getElementById("qrScannerModal");
    if (modalEl) {
        // Start camera only after modal transition completes and container has valid dimensions
        modalEl.addEventListener("shown.bs.modal", () => {
            const input = document.getElementById("manualScanInput");
            if (input) {
                input.value = "";
                setTimeout(() => input.focus(), 200);
            }
            startQRScanner();
        });

        // Cleanly stop camera on hide & hidden
        modalEl.addEventListener("hide.bs.modal", () => {
            stopQRScanner();
        });

        modalEl.addEventListener("hidden.bs.modal", () => {
            stopQRScanner();
        });
    }
});

function handleManualScan(e) {
    if (e) e.preventDefault();
    const input = document.getElementById("manualScanInput");
    if (!input || !input.value.trim()) return;
    const code = input.value.trim();
    input.value = "";
    handleScannedQR(code);
}

function simulateScanCode(code) {
    handleScannedQR(code);
}

function handleScannedQR(rawText) {
    if (!rawText) return;
    rawText = rawText.trim();
    
    let queryCode = rawText;
    try {
        const obj = JSON.parse(rawText);
        queryCode = obj.code || obj.product_code || obj.barcode || obj.id || rawText;
    } catch(e) {}
    
    if (queryCode.startsWith("http")) {
        try {
            const u = new URL(queryCode);
            queryCode = u.searchParams.get("code") || u.searchParams.get("id") || queryCode.split("/").pop();
        } catch(e) {}
    }
    queryCode = queryCode.trim().toLowerCase();
    
    // Find matching product
    let product = INVENTORY_PRODUCTS.find(p => 
        (p.product_code && p.product_code.toLowerCase() === queryCode) ||
        (p.barcode && p.barcode.toLowerCase() === queryCode) ||
        p.id.toString() === queryCode ||
        (p.name && p.name.toLowerCase().includes(queryCode))
    );
    
    const feedback = document.getElementById("qrScanFeedback");
    
    if (product) {
        playScanBeep();
        addProductRow(product);
        
        if (feedback) {
            feedback.className = "alert alert-success py-2 px-3 fs-xs mb-3 text-center fw-bold";
            feedback.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ADDED: ${product.name} (${product.product_code}) - ${product.purity} | ${product.net_weight}g`;
        }

        const mobFeedback = document.getElementById("mobileDirectScanFeedback");
        if (mobFeedback) {
            mobFeedback.style.display = "block";
            mobFeedback.className = "alert alert-success py-1 px-2 fs-xs mt-2 mb-0 text-center fw-bold";
            mobFeedback.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ADDED: ${product.name} (${product.product_code || ""})`;
            setTimeout(() => { if (mobFeedback) mobFeedback.style.display = "none"; }, 3500);
        }
        
        showToast(`QR Scanned: Added ${product.name} to bill!`, "success");
        
        const continuous = document.getElementById("continuousScanToggle");
        if (continuous && !continuous.checked) {
            setTimeout(() => {
                const modalEl = document.getElementById("qrScannerModal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                stopQRScanner();
            }, 800);
        }
    } else {
        // Fallback AJAX lookup
        fetch(`' . url('modules/billing/ajax_search_products.php?q=') . '` + encodeURIComponent(queryCode))
            .then(res => res.json())
            .then(data => {
                if (data.products && data.products.length > 0) {
                    const p = data.products[0];
                    playScanBeep();
                    addProductRow({
                        id: p.id,
                        product_code: p.product_code,
                        barcode: p.barcode,
                        name: p.name,
                        metal_type: p.metal_type,
                        purity: p.purity,
                        gross_weight: parseFloat(p.gross_weight),
                        net_weight: parseFloat(p.net_weight),
                        stone_weight: parseFloat(p.stone_weight),
                        making_charges: parseFloat(p.making_charges),
                        making_charges_type: p.making_charges_type,
                        wastage_percentage: 0,
                        stone_charges: 0,
                        selling_price: parseFloat(p.selling_price),
                        stock_quantity: parseInt(p.stock_quantity)
                    });
                    if (feedback) {
                        feedback.className = "alert alert-success py-2 px-3 fs-xs mb-3 text-center fw-bold";
                        feedback.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ADDED: ${p.name} (${p.product_code})`;
                    }
                    const mobFeedback = document.getElementById("mobileDirectScanFeedback");
                    if (mobFeedback) {
                        mobFeedback.style.display = "block";
                        mobFeedback.className = "alert alert-success py-1 px-2 fs-xs mt-2 mb-0 text-center fw-bold";
                        mobFeedback.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ADDED: ${p.name} (${p.product_code || ""})`;
                        setTimeout(() => { if (mobFeedback) mobFeedback.style.display = "none"; }, 3500);
                    }
                    showToast(`Scanned: Added ${p.name} to bill!`, "success");
                } else {
                    if (feedback) {
                        feedback.className = "alert alert-danger py-2 px-3 fs-xs mb-3 text-center fw-bold";
                        feedback.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-1"></i> No jewellery product found for: "${rawText}"`;
                    }
                    const mobFeedback = document.getElementById("mobileDirectScanFeedback");
                    if (mobFeedback) {
                        mobFeedback.style.display = "block";
                        mobFeedback.className = "alert alert-danger py-1 px-2 fs-xs mt-2 mb-0 text-center fw-bold";
                        mobFeedback.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-1"></i> No jewellery product found for: "${rawText}"`;
                    }
                }
            })
            .catch(() => {
                if (feedback) {
                    feedback.className = "alert alert-danger py-2 px-3 fs-xs mb-3 text-center fw-bold";
                    feedback.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-1"></i> Product code not found: "${rawText}"`;
                }
                const mobFeedback = document.getElementById("mobileDirectScanFeedback");
                if (mobFeedback) {
                    mobFeedback.style.display = "block";
                    mobFeedback.className = "alert alert-danger py-1 px-2 fs-xs mt-2 mb-0 text-center fw-bold";
                    mobFeedback.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-1"></i> Product code not found: "${rawText}"`;
                }
            });
    }
}

// Global Barcode Scanner Gun Key Listener
let barcodeBuffer = "";
let barcodeTimer = null;

document.addEventListener("keydown", (e) => {
    const active = document.activeElement;
    const isTextInput = active && (active.tagName === "INPUT" || active.tagName === "TEXTAREA");
    const isScanInput = active && (active.id === "manualScanInput" || active.id === "catalogueSearchInput");
    
    if (isTextInput && !isScanInput) {
        return;
    }
    
    if (e.key === "Enter") {
        if (barcodeBuffer.length >= 3) {
            handleScannedQR(barcodeBuffer);
            barcodeBuffer = "";
            e.preventDefault();
        }
    } else if (e.key.length === 1) {
        barcodeBuffer += e.key;
        clearTimeout(barcodeTimer);
        barcodeTimer = setTimeout(() => {
            barcodeBuffer = "";
        }, 200);
    }
});

// Helper to get default metal rate by metal & purity
function getDefaultRate(metal, purity) {
    metal = (metal || "gold").toLowerCase();
    purity = (purity || "").toUpperCase();
    
    if (metal === "gold") {
        if (purity.includes("24")) return METAL_RATES.gold_24k;
        if (purity.includes("18")) return METAL_RATES.gold_18k;
        return METAL_RATES.gold_22k;
    } else if (metal === "silver") {
        if (purity.includes("925")) return METAL_RATES.silver_925;
        return METAL_RATES.silver_999;
    } else if (metal === "platinum") {
        return METAL_RATES.platinum;
    }
    return 0;
}

// ---------------- Bill Type (With GST vs Without GST) ----------------
function onBillTypeChange() {
    const isWithGst = document.getElementById("billTypeWithGst").checked;
    const badge = document.getElementById("billTypeBadge");
    const topBadge = document.getElementById("topBillTypeBadge");
    const topBadgeMobile = document.getElementById("topBillTypeBadgeMobile");
    const taxSection = document.getElementById("taxSectionContainer");
    const igstContainer = document.getElementById("igstSwitchContainer");
    const nonGstNotice = document.getElementById("nonGstNotice");
    const card = document.getElementById("billTypeCard");

    if (card) {
        card.className = isWithGst ? "card mb-3 border-2 border-success-subtle shadow-xs" : "card mb-3 border-2 border-secondary-subtle shadow-xs";
    }

    if (isWithGst) {
        if (badge) {
            badge.className = "badge bg-success";
            badge.innerText = "GST Bill (3%)";
        }
        if (topBadge) {
            topBadge.className = "badge bg-success fs-6 px-3 py-2";
            topBadge.innerHTML = `<i class="bi bi-shield-check me-1"></i> Tax Invoice (With GST)`;
        }
        if (topBadgeMobile) {
            topBadgeMobile.className = "fw-semibold text-success d-flex align-items-center";
            topBadgeMobile.innerHTML = `<i class="bi bi-shield-check me-1"></i>GST (3%)`;
        }
        if (taxSection) taxSection.style.display = "block";
        if (igstContainer) igstContainer.style.display = "block";
        if (nonGstNotice) nonGstNotice.style.display = "none";
    } else {
        if (badge) {
            badge.className = "badge bg-secondary";
            badge.innerText = "Non-GST (0% Tax)";
        }
        if (topBadge) {
            topBadge.className = "badge bg-secondary fs-6 px-3 py-2";
            topBadge.innerHTML = `<i class="bi bi-file-earmark-text me-1"></i> Retail Estimate (Non-GST)`;
        }
        if (topBadgeMobile) {
            topBadgeMobile.className = "fw-semibold text-secondary d-flex align-items-center";
            topBadgeMobile.innerHTML = `<i class="bi bi-file-earmark-text me-1"></i>Non-GST`;
        }
        if (taxSection) taxSection.style.display = "none";
        if (igstContainer) igstContainer.style.display = "none";
        if (nonGstNotice) nonGstNotice.style.display = "block";
    }
    calculateBillTotals();
}

// ---------------- Catalogue Filtering & View Switching ----------------
function filterCatalogue() {
    const q = document.getElementById("catalogueSearchInput").value.toLowerCase().trim();
    const items = document.querySelectorAll(".catalogue-item");
    items.forEach(el => {
        const name = el.getAttribute("data-name");
        if (!q || name.includes(q)) {
            el.style.display = "";
        } else {
            el.style.display = "none";
        }
    });
}

function filterByMetal(metal, btn) {
    // Update active pill styling
    document.querySelectorAll("#metalFilterTabs button").forEach(b => {
        b.className = "btn btn-sm btn-light border py-1 px-3 rounded-pill fw-medium text-dark";
    });
    btn.className = "btn btn-sm btn-primary py-1 px-3 rounded-pill fw-semibold";

    const items = document.querySelectorAll(".catalogue-item");
    items.forEach(el => {
        const itemMetal = el.getAttribute("data-metal");
        if (metal === "all" || itemMetal === metal) {
            el.style.display = "";
        } else {
            el.style.display = "none";
        }
    });
}

function switchCatalogView(view) {
    const listView = document.getElementById("catalogListView");
    const gridView = document.getElementById("catalogGridView");
    const btnList = document.getElementById("btnViewList");
    const btnGrid = document.getElementById("btnViewGrid");
    if (view === "grid") {
        listView.style.display = "none";
        gridView.style.display = "block";
        btnList.classList.remove("active");
        btnGrid.classList.add("active");
    } else {
        listView.style.display = "block";
        gridView.style.display = "none";
        btnGrid.classList.remove("active");
        btnList.classList.add("active");
    }
}

// Add Product Directly from Catalogue Card
function addProductFromCatalog(p) {
    addProductRow(p);
    showToast(`Added ${p.name} to bill!`, "success");
    
    // Smooth scroll to bill items
    const target = document.getElementById("billItemsCard");
    if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}

// ---------------- Add Product Row ----------------
function addProductRow(p) {
    const container = document.getElementById("itemsContainer");
    document.getElementById("noItemsAlert").style.display = "none";
    
    const idx = itemIndex++;
    const defaultRate = getDefaultRate(p.metal_type, p.purity);
    const makingRate = parseFloat(p.making_charges || 0);
    const makingType = p.making_charges_type || "per_gram";
    const wastagePct = parseFloat(p.wastage_percentage || 0);
    const stoneCharges = parseFloat(p.stone_charges || 0);
    const grossWt = parseFloat(p.gross_weight || 0);
    const stoneWt = parseFloat(p.stone_weight || 0);
    const netWt = parseFloat(p.net_weight || (grossWt - stoneWt));

    const row = document.createElement("div");
    row.className = "bill-item-row shadow-sm mb-3 p-3 bg-white border rounded";
    row.id = `item_row_${idx}`;
    row.innerHTML = `
        <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary">Item #${idx + 1}</span>
                <strong class="text-dark fs-6">${p.name}</strong>
                <span class="badge bg-light text-dark border">${p.product_code || "CUSTOM"}</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(${idx})">
                <i class="bi bi-trash me-1"></i> Remove
            </button>
        </div>
        
        <input type="hidden" name="items[${idx}][product_id]" value="${p.id || ""}">
        <input type="hidden" name="items[${idx}][product_code]" value="${p.product_code || ""}">
        <input type="hidden" name="items[${idx}][product_name]" value="${p.name || ""}">
        <input type="hidden" name="items[${idx}][hsn_code]" value="7113">

        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label fs-xs mb-1">Metal & Purity</label>
                <div class="input-group input-group-sm">
                    <select class="form-select" name="items[${idx}][metal_type]" id="item_metal_${idx}" onchange="onItemMetalChange(${idx})">
                        <option value="gold" ${p.metal_type === "gold" ? "selected" : ""}>Gold</option>
                        <option value="silver" ${p.metal_type === "silver" ? "selected" : ""}>Silver</option>
                        <option value="diamond" ${p.metal_type === "diamond" ? "selected" : ""}>Diamond</option>
                        <option value="platinum" ${p.metal_type === "platinum" ? "selected" : ""}>Platinum</option>
                        <option value="other" ${p.metal_type === "other" ? "selected" : ""}>Other</option>
                    </select>
                    <select class="form-select" name="items[${idx}][purity]" id="item_purity_${idx}" onchange="onItemMetalChange(${idx})">
                        <option value="24K" ${p.purity === "24K" ? "selected" : ""}>24K</option>
                        <option value="22K" ${p.purity === "22K" || !p.purity ? "selected" : ""}>22K</option>
                        <option value="18K" ${p.purity === "18K" ? "selected" : ""}>18K</option>
                        <option value="14K" ${p.purity === "14K" ? "selected" : ""}>14K</option>
                        <option value="999 Silver" ${p.purity === "999 Silver" ? "selected" : ""}>999 Sil</option>
                        <option value="925 Silver" ${p.purity === "925 Silver" ? "selected" : ""}>925 Sil</option>
                        <option value="950 Platinum" ${p.purity === "950 Platinum" ? "selected" : ""}>950 Plat</option>
                    </select>
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label fs-xs mb-1">Gross Wt (g)</label>
                <input type="number" step="0.001" class="form-control form-control-sm" name="items[${idx}][gross_weight]" id="item_gross_${idx}" value="${grossWt.toFixed(3)}" oninput="onWeightChange(${idx})">
            </div>

            <div class="col-md-2">
                <label class="form-label fs-xs mb-1">Stone Wt (g)</label>
                <input type="number" step="0.001" class="form-control form-control-sm" name="items[${idx}][stone_weight]" id="item_stone_wt_${idx}" value="${stoneWt.toFixed(3)}" oninput="onWeightChange(${idx})">
            </div>

            <div class="col-md-2">
                <label class="form-label fs-xs mb-1">Net Wt (g)</label>
                <input type="number" step="0.001" class="form-control form-control-sm bg-light fw-bold" name="items[${idx}][net_weight]" id="item_net_${idx}" value="${netWt.toFixed(3)}" readonly>
            </div>

            <div class="col-md-3">
                <label class="form-label fs-xs mb-1">Rate / Gram (₹)</label>
                <input type="number" step="0.01" class="form-control form-control-sm fw-semibold" name="items[${idx}][rate_per_gram]" id="item_rate_${idx}" value="${defaultRate.toFixed(2)}" oninput="calculateItemRow(${idx})">
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-5">
                <label class="form-label fs-xs mb-1">Working Charges (मजदूरी)</label>
                <div class="input-group input-group-sm">
                    <select class="form-select" name="items[${idx}][making_charges_type]" id="item_making_type_${idx}" style="max-width: 95px;" onchange="calculateItemRow(${idx})">
                        <option value="per_gram" ${makingType === "per_gram" ? "selected" : ""}>/g</option>
                        <option value="percentage" ${makingType === "percentage" ? "selected" : ""}>%</option>
                        <option value="fixed" ${makingType === "fixed" ? "selected" : ""}>₹ Fix</option>
                    </select>
                    <input type="number" step="0.01" class="form-control fw-bold" name="items[${idx}][making_charges_rate]" id="item_making_rate_${idx}" value="${makingRate}" oninput="calculateItemRow(${idx})">
                </div>
            </div>

            <input type="hidden" name="items[${idx}][wastage_percentage]" id="item_wastage_pct_${idx}" value="0">
            <input type="hidden" name="items[${idx}][stone_charges]" id="item_stone_chg_${idx}" value="0">

            <div class="col-md-2">
                <label class="form-label fs-xs mb-1">Quantity</label>
                <input type="number" step="1" min="1" class="form-control form-control-sm fw-bold" name="items[${idx}][quantity]" id="item_qty_${idx}" value="1" oninput="calculateItemRow(${idx})">
            </div>

            <div class="col-md-5">
                <label class="form-label fs-xs mb-1">Item Total Amount (Metal + Working)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">₹</span>
                    <input type="text" class="form-control fw-bold text-primary bg-light" id="item_total_display_${idx}" readonly value="0.00">
                </div>
            </div>
        </div>
    `;

    container.appendChild(row);
    calculateItemRow(idx);
    updateSelectedCount();
    updateMobileScannedList();
}

// Add Custom Item Row
function addCustomItemRow() {
    const goldMaking = DEFAULT_MAKING_CONFIG.gold || DEFAULT_MAKING_CONFIG.default;
    addProductRow({
        id: "",
        product_code: "CUST-" + (Math.floor(Math.random() * 900) + 100),
        name: "Custom Jewellery Piece",
        metal_type: "gold",
        purity: "22K",
        gross_weight: 10.000,
        stone_weight: 0.000,
        net_weight: 10.000,
        making_charges_type: goldMaking.type || "per_gram",
        making_charges: parseFloat(goldMaking.rate) || 400.00,
        wastage_percentage: 2.00,
        stone_charges: 0.00
    });
}

function removeItemRow(idx) {
    const el = document.getElementById(`item_row_${idx}`);
    if (el) el.remove();
    
    const container = document.getElementById("itemsContainer");
    if (container.children.length === 0) {
        document.getElementById("noItemsAlert").style.display = "block";
    }
    calculateBillTotals();
    updateSelectedCount();
    updateMobileScannedList();
}

function updateSelectedCount() {
    const count = document.querySelectorAll(".bill-item-row").length;
    document.getElementById("selectedItemsCountBadge").innerText = `${count} ${count === 1 ? "Item" : "Items"}`;
}

function onWeightChange(idx) {
    const gross = parseFloat(document.getElementById(`item_gross_${idx}`).value) || 0;
    const stone = parseFloat(document.getElementById(`item_stone_wt_${idx}`).value) || 0;
    const net = Math.max(0, gross - stone);
    document.getElementById(`item_net_${idx}`).value = net.toFixed(3);
    calculateItemRow(idx);
}

function onItemMetalChange(idx) {
    const metal = document.getElementById(`item_metal_${idx}`).value;
    const purity = document.getElementById(`item_purity_${idx}`).value;
    const newRate = getDefaultRate(metal, purity);
    document.getElementById(`item_rate_${idx}`).value = newRate.toFixed(2);
    
    // Also update making charges if this is a custom item
    const cfg = DEFAULT_MAKING_CONFIG[metal] || DEFAULT_MAKING_CONFIG.default;
    if (cfg) {
        const typeEl = document.getElementById(`item_making_type_${idx}`);
        const rateEl = document.getElementById(`item_making_rate_${idx}`);
        if (typeEl && rateEl) {
            typeEl.value = cfg.type;
            rateEl.value = cfg.rate;
        }
    }

    calculateItemRow(idx);
}

// Calculate individual item row: Metal Value + Working Charges ONLY
function calculateItemRow(idx) {
    const netWt = parseFloat(document.getElementById(`item_net_${idx}`).value) || 0;
    const rate = parseFloat(document.getElementById(`item_rate_${idx}`).value) || 0;
    const metalVal = netWt * rate;

    const makingType = document.getElementById(`item_making_type_${idx}`).value;
    const makingRate = parseFloat(document.getElementById(`item_making_rate_${idx}`).value) || 0;
    let makingChg = 0;
    if (makingType === "per_gram") {
        makingChg = netWt * makingRate;
    } else if (makingType === "percentage") {
        makingChg = (metalVal * makingRate) / 100;
    } else {
        makingChg = makingRate;
    }

    const qty = parseInt(document.getElementById(`item_qty_${idx}`).value) || 1;

    // Only Metal Value + Working Charges!
    const total = (metalVal + makingChg) * qty;
    document.getElementById(`item_total_display_${idx}`).value = total.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    calculateBillTotals();
}

// ---------------- Overall Bill Totals Calculation ----------------
function calculateBillTotals() {
    let sumMetalVal = 0;
    let sumMaking = 0;

    const rows = document.querySelectorAll(".bill-item-row");
    rows.forEach(r => {
        const idParts = r.id.split("_");
        const idx = idParts[idParts.length - 1];

        const netWt = parseFloat(document.getElementById(`item_net_${idx}`).value) || 0;
        const rate = parseFloat(document.getElementById(`item_rate_${idx}`).value) || 0;
        const metalVal = netWt * rate;

        const makingType = document.getElementById(`item_making_type_${idx}`).value;
        const makingRate = parseFloat(document.getElementById(`item_making_rate_${idx}`).value) || 0;
        let makingChg = 0;
        if (makingType === "per_gram") {
            makingChg = netWt * makingRate;
        } else if (makingType === "percentage") {
            makingChg = (metalVal * makingRate) / 100;
        } else {
            makingChg = makingRate;
        }

        const qty = parseInt(document.getElementById(`item_qty_${idx}`).value) || 1;

        sumMetalVal += (metalVal * qty);
        sumMaking += (makingChg * qty);
    });

    // Gross Amount = Metal Value + Working Charges ONLY!
    const grossSubtotal = sumMetalVal + sumMaking;

    // Discount
    const discType = document.getElementById("discountType").value;
    const discVal = parseFloat(document.getElementById("discountValue").value) || 0;
    let discountAmount = 0;
    if (discType === "percentage") {
        discountAmount = (grossSubtotal * discVal) / 100;
    } else {
        discountAmount = Math.min(grossSubtotal, discVal);
    }

    const taxableAmount = Math.max(0, grossSubtotal - discountAmount);

    // Bill With GST vs Without GST Option
    const isWithGst = document.getElementById("billTypeWithGst") ? document.getElementById("billTypeWithGst").checked : true;
    let taxAmount = 0;

    if (isWithGst) {
        const isIgst = document.getElementById("isIgst").checked;
        const gstRate = 3.0; // 3%
        taxAmount = (taxableAmount * gstRate) / 100;
        document.getElementById("tax_breakdown_label").innerText = isIgst ? "IGST (3%):" : "CGST (1.5%) + SGST (1.5%):";
        document.getElementById("disp_tax_amount").innerText = "₹" + taxAmount.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    } else {
        document.getElementById("tax_breakdown_label").innerText = "GST (0% - Without GST):";
        document.getElementById("disp_tax_amount").innerText = "₹0.00";
    }

    const grandTotal = taxableAmount + taxAmount;
    const finalAmount = Math.round(grandTotal);
    const roundOff = (finalAmount - grandTotal);

    // Update UI displays
    document.getElementById("disp_metal_value").innerText = "₹" + sumMetalVal.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    document.getElementById("disp_making_charges").innerText = "₹" + sumMaking.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    document.getElementById("disp_gross_subtotal").innerText = "₹" + grossSubtotal.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    document.getElementById("disp_discount_amount").innerText = "-₹" + discountAmount.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    document.getElementById("disp_tax_amount").innerText = "₹" + taxAmount.toLocaleString("en-IN", { minimumFractionDigits: 2 });
    document.getElementById("disp_final_amount").innerText = "₹" + finalAmount.toLocaleString("en-IN");
    document.getElementById("disp_round_off").innerText = "(Round off: ₹" + (roundOff >= 0 ? "+" : "") + roundOff.toFixed(2) + ")";

    // Number to words
    document.getElementById("disp_amount_in_words").innerText = numberToWords(finalAmount);

    // Update Mobile Sticky Checkout Bar
    const mobileFinalAmount = document.getElementById("mobileFinalAmount");
    if (mobileFinalAmount) mobileFinalAmount.innerText = "₹" + finalAmount.toLocaleString("en-IN");
    const mobileItemCount = document.getElementById("mobileItemCount");
    if (mobileItemCount) {
        let totalItems = 0;
        document.querySelectorAll("input[name*=\'quantity\']").forEach(i => totalItems += parseInt(i.value) || 0);
        mobileItemCount.innerText = totalItems;
    }
    const mobileStep1Total = document.getElementById("mobileStep1Total");
    if (mobileStep1Total) mobileStep1Total.innerText = "₹" + finalAmount.toLocaleString("en-IN");
    const mobileStep1Count = document.getElementById("mobileStep1Count");
    if (mobileStep1Count) {
        mobileStep1Count.innerText = document.querySelectorAll(".bill-item-row").length;
    }
    updateMobileScannedList();

    // Default paid amount to full final amount if currently 0 or equal to previous total
    const paidInput = document.getElementById("paidAmount");
    if (paidInput.dataset.manual !== "true") {
        paidInput.value = finalAmount;
    }
    updateDueAmount();
}

function updateDueAmount() {
    const finalAmtText = document.getElementById("disp_final_amount").innerText.replace(/[^0-9.-]+/g,"");
    const finalAmt = parseFloat(finalAmtText) || 0;
    const paidInput = document.getElementById("paidAmount");
    const paid = parseFloat(paidInput.value) || 0;
    
    const due = Math.max(0, finalAmt - paid);
    document.getElementById("disp_due_amount").value = due.toLocaleString("en-IN", { minimumFractionDigits: 2 });
}

document.getElementById("paidAmount").addEventListener("input", function() {
    this.dataset.manual = "true";
    updateDueAmount();
});

// ---------------- Customer Search & Selection ----------------
const customerSearchInput = document.getElementById("customerSearchInput");
const customerSearchResults = document.getElementById("customerSearchResults");

customerSearchInput.addEventListener("input", debounce(function() {
    const q = customerSearchInput.value.trim();
    if (q.length < 1) {
        customerSearchResults.style.display = "none";
        return;
    }
    
    fetch("' . url('modules/billing/ajax_customer.php') . '?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            customerSearchResults.innerHTML = "";
            if (!data.customers || data.customers.length === 0) {
                customerSearchResults.innerHTML = "<div class=\"p-3 text-muted text-center fs-sm\">No matching customer. Click + New Customer above.</div>";
                customerSearchResults.style.display = "block";
                return;
            }
            
            data.customers.forEach(c => {
                const a = document.createElement("a");
                a.href = "#";
                a.className = "list-group-item list-group-item-action py-2";
                a.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${c.name}</strong> <span class="text-muted">(${c.phone})</span>
                            <div class="fs-xs text-muted">${c.address || "No address"}</div>
                        </div>
                        <div class="text-end">
                            <span class="fs-xs ${parseFloat(c.outstanding_balance) > 0 ? "text-danger fw-bold" : "text-muted"}">
                                Balance: ₹${parseFloat(c.outstanding_balance).toLocaleString("en-IN")}
                            </span>
                        </div>
                    </div>
                `;
                a.addEventListener("click", function(e) {
                    e.preventDefault();
                    selectCustomer(c);
                    customerSearchResults.style.display = "none";
                });
                customerSearchResults.appendChild(a);
            });
            customerSearchResults.style.display = "block";
        });
}, 250));

function selectWalkInCustomer() {
    const custIdEl = document.getElementById("selectedCustomerId");
    const nameEl = document.getElementById("cust_name");
    const phoneEl = document.getElementById("cust_phone");
    const addrEl = document.getElementById("cust_address");
    const gstinEl = document.getElementById("cust_gstin");
    const outstandingEl = document.getElementById("cust_outstanding");
    const searchInput = document.getElementById("customerSearchInput");
    const statusEl = document.getElementById("cust_lookup_status");
    const btn = document.getElementById("btnWalkInCustomer");

    custIdEl.value = "walk_in";
    nameEl.value = "Walk-in Customer";
    phoneEl.value = "Walk-in";
    phoneEl.removeAttribute("required");
    if (addrEl) addrEl.value = "";
    if (gstinEl) gstinEl.value = "";
    if (outstandingEl) outstandingEl.value = "0.00";
    if (searchInput) searchInput.value = "Walk-in Customer";

    if (statusEl) {
        statusEl.innerHTML = `<span class="badge bg-secondary-subtle text-dark border border-secondary-subtle"><i class="bi bi-person-walking text-primary me-1"></i>Walk-in Customer (No customer details on bill • Recorded in Customers directory)</span>`;
        statusEl.style.display = "block";
    }

    if (btn) {
        btn.classList.remove("btn-outline-primary");
        btn.classList.add("btn-primary");
        btn.innerHTML = `<i class="bi bi-check2-circle me-1"></i> Walk-in Active`;
    }
}

function resetWalkInBtn() {
    const btn = document.getElementById("btnWalkInCustomer");
    if (btn && btn.classList.contains("btn-primary")) {
        btn.classList.remove("btn-primary");
        btn.classList.add("btn-outline-primary");
        btn.innerHTML = `<i class="bi bi-person-walking"></i> Walk-in Customer`;
    }
    const phoneEl = document.getElementById("cust_phone");
    if (phoneEl && phoneEl.value !== "Walk-in") {
        phoneEl.setAttribute("required", "required");
    }
}

function selectCustomer(c) {
    resetWalkInBtn();
    document.getElementById("selectedCustomerId").value = c.id;
    document.getElementById("customerSearchInput").value = `${c.name} (${c.phone})`;
    document.getElementById("cust_name").value = c.name;
    document.getElementById("cust_phone").value = c.phone;
    document.getElementById("cust_phone").setAttribute("required", "required");
    document.getElementById("cust_address").value = c.address || "";
    document.getElementById("cust_gstin").value = c.gstin || "";
    document.getElementById("cust_outstanding").value = parseFloat(c.outstanding_balance || 0).toLocaleString("en-IN", { minimumFractionDigits: 2 });
    
    const statusEl = document.getElementById("cust_lookup_status");
    if (statusEl) {
        statusEl.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Linked: ${c.name}</span>`;
        statusEl.style.display = "block";
    }
}

// Automatic Phone Lookup & New Customer Status Detection
const custPhoneInput = document.getElementById("cust_phone");
if (custPhoneInput) {
    custPhoneInput.addEventListener("input", debounce(function() {
        resetWalkInBtn();
        const phone = custPhoneInput.value.trim();
        const digits = phone.replace(/[^0-9]/g, "");
        const statusEl = document.getElementById("cust_lookup_status");

        if (phone === "Walk-in") {
            return;
        }

        if (digits.length < 5) {
            if (statusEl) statusEl.style.display = "none";
            return;
        }

        fetch("' . url('modules/billing/ajax_customer.php?action=lookup_phone&phone=') . '" + encodeURIComponent(phone))
            .then(r => r.json())
            .then(data => {
                if (data.found && data.customer) {
                    selectCustomer(data.customer);
                } else {
                    document.getElementById("selectedCustomerId").value = "";
                    document.getElementById("cust_outstanding").value = "0.00";
                    if (statusEl) {
                        statusEl.innerHTML = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-stars me-1"></i>New customer — will be automatically added to system</span>`;
                        statusEl.style.display = "block";
                    }
                }
            })
            .catch(() => {});
    }, 300));
}

document.getElementById("cust_name").addEventListener("input", function() {
    if (this.value !== "Walk-in Customer") {
        resetWalkInBtn();
    }
});

// ---------------- Quick Customer Modal Submit ----------------
function submitQuickCustomer() {
    const name = document.getElementById("qc_name").value.trim();
    const phone = document.getElementById("qc_phone").value.trim();
    if (!name || !phone) {
        alert("Please enter customer name and phone number.");
        return;
    }
    
    const formData = new FormData();
    formData.append("name", name);
    formData.append("phone", phone);
    formData.append("email", document.getElementById("qc_email").value.trim());
    formData.append("address", document.getElementById("qc_address").value.trim());
    formData.append("city", document.getElementById("qc_city").value.trim());
    formData.append("gstin", document.getElementById("qc_gstin").value.trim());
    
    fetch("' . url('modules/billing/ajax_customer.php?action=quick_add') . '", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.customer) {
            selectCustomer(res.customer);
            const modalEl = document.getElementById("newCustomerModal");
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
            showToast("Customer added and selected!", "success");
        } else {
            alert(res.message || "Failed to add customer.");
        }
    })
    .catch(err => {
        alert("Error saving customer.");
    });
}

function resetBillingForm() {
    if (confirm("Reset the entire billing form?")) {
        window.location.reload();
    }
}

function scrollToBillingSummary() {
    const el = document.getElementById("billingSummaryCard");
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}

function submitBillMobile() {
    const custName = document.getElementById("cust_name");
    const custPhone = document.getElementById("cust_phone");
    if (!custName || !custName.value.trim() || !custPhone || !custPhone.value.trim()) {
        showToast("Please enter Customer Name & Phone Number first", "warning");
        goToMobileBillingStep(2);
        if (custName && !custName.value.trim()) custName.focus();
        return;
    }
    const items = document.querySelectorAll(".bill-item-row");
    if (items.length === 0) {
        showToast("Please scan or add at least 1 jewellery item", "warning");
        goToMobileBillingStep(1);
        return;
    }
    const form = document.querySelector("form[action*=\'save_bill\']");
    if (form) {
        if (form.reportValidity()) {
            form.submit();
        }
    }
}

let activeDiscountRequestId = null;
let discountCheckInterval = null;

function requestDiscountApproval() {
    const subtotalText = document.getElementById("disp_gross_subtotal").innerText.replace(/[^0-9.-]+/g,"");
    const subtotal = parseFloat(subtotalText) || 0;
    const type = document.getElementById("discountType").value;
    const val = parseFloat(document.getElementById("discountValue").value) || 0;
    const custName = document.getElementById("cust_name").value.trim() || "Walk-in Customer";

    if (subtotal <= 0) {
        alert("Please add at least one item to the bill before requesting discount approval.");
        return;
    }
    if (val <= 0) {
        alert("Please enter a discount value (> 0) to request approval.");
        return;
    }

    const typeLabel = type === "percentage" ? val + "%" : "₹" + val.toLocaleString("en-IN");
    const reason = prompt("Request Admin Approval for " + typeLabel + " discount for " + custName + ":\nEnter optional note/reason for Admin (or leave empty):", "Customer concession");
    if (reason === null) return;

    const btn = document.getElementById("btnRequestDiscountApproval");
    btn.disabled = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1"></span> Sending request to Admin...\';

    const formData = new FormData();
    formData.append("customer_name", custName);
    formData.append("bill_subtotal", subtotal);
    formData.append("discount_type", type);
    formData.append("discount_value", val);
    formData.append("reason", reason);

    fetch(\'' . url('modules/billing/ajax_discount_approval.php?action=request') . '\', {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            activeDiscountRequestId = res.request_id;
            const alertBox = document.getElementById("discountApprovalStatusAlert");
            alertBox.className = "alert alert-warning py-1 px-2 fs-xs d-flex align-items-center justify-content-between mb-1";
            alertBox.innerHTML = \'<span><i class="bi bi-hourglass-split me-1 text-warning"></i>Approval Sent! Waiting for Admin...</span><button type="button" class="btn btn-xs btn-link p-0 text-dark fw-bold" onclick="pollDiscountStatus()">Check Status</button>\';
            alertBox.style.display = "flex";
            btn.innerHTML = \'<i class="bi bi-check2 me-1"></i> Requested (ID #\' + res.request_id + \')\';
            
            if (discountCheckInterval) clearInterval(discountCheckInterval);
            discountCheckInterval = setInterval(pollDiscountStatus, 3500);
        } else {
            btn.disabled = false;
            btn.innerHTML = \'<i class="bi bi-shield-lock-fill"></i> Request Admin Approval for Discount\';
            alert(res.message || "Failed to submit request.");
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = \'<i class="bi bi-shield-lock-fill"></i> Request Admin Approval for Discount\';
        alert("Network error sending approval request.");
    });
}

function pollDiscountStatus() {
    if (!activeDiscountRequestId) return;
    fetch(\'' . url('modules/billing/ajax_discount_approval.php?action=check_status') . '&request_id=\' + activeDiscountRequestId)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const alertBox = document.getElementById("discountApprovalStatusAlert");
            const btn = document.getElementById("btnRequestDiscountApproval");

            if (res.status === "approved") {
                if (discountCheckInterval) clearInterval(discountCheckInterval);
                document.getElementById("discountType").value = res.approved_type;
                document.getElementById("discountValue").value = res.approved_value;
                calculateBillTotals();

                const approvedLabel = res.approved_type === "percentage" ? res.approved_value + "%" : "₹" + res.approved_value.toLocaleString("en-IN");
                alertBox.className = "alert alert-success py-1 px-2 fs-xs mb-1";
                alertBox.innerHTML = \'<i class="bi bi-check-circle-fill me-1 text-success"></i><strong>Discount Approved by Admin:</strong> \' + approvedLabel + \' (Applied to Bill)\';
                alertBox.style.display = "block";
                if (btn) btn.style.display = "none";
            } else if (res.status === "rejected") {
                if (discountCheckInterval) clearInterval(discountCheckInterval);
                document.getElementById("discountValue").value = 0;
                calculateBillTotals();

                alertBox.className = "alert alert-danger py-1 px-2 fs-xs mb-1";
                alertBox.innerHTML = \'<i class="bi bi-x-circle-fill me-1 text-danger"></i><strong>Discount Request Rejected by Admin.</strong>\' + (res.admin_notes ? " Reason: " + res.admin_notes : "");
                alertBox.style.display = "block";
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = \'<i class="bi bi-shield-lock-fill"></i> Request Admin Approval for Discount\';
                }
            }
        }
    })
    .catch(err => {});
}

// Initialise bill type state based on preset selection
document.addEventListener("DOMContentLoaded", function() {
    onBillTypeChange();
    if (window.innerWidth <= 992) {
        goToMobileBillingStep(1);
    }
});
onBillTypeChange();
if (window.innerWidth <= 992) {
    goToMobileBillingStep(1);
}
</script>
';
include __DIR__ . '/../../includes/footer.php';
?>