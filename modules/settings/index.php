<?php
/**
 * System Settings
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Shop & System Settings';
$pdo = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        // 1. Business & Store Settings
        $settingsToUpdate = [
            'shop_name' => trim($_POST['shop_name'] ?? 'Sachin jewellers'),
            'shop_address' => trim($_POST['shop_address'] ?? ''),
            'shop_phone' => trim($_POST['shop_phone'] ?? ''),
            'shop_email' => trim($_POST['shop_email'] ?? ''),
            'shop_gstin' => trim($_POST['shop_gstin'] ?? ''),
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '₹'),
            'gst_rate' => trim($_POST['gst_rate'] ?? '3.00'),
            'invoice_prefix' => trim($_POST['invoice_prefix'] ?? 'INV'),
            'low_stock_threshold' => trim($_POST['low_stock_threshold'] ?? '3'),

            // Working Charges / Making Charges (Amount ₹ or Percentage %)
            'default_making_charges_type' => in_array($_POST['default_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['default_making_charges_type'] : 'per_gram',
            'default_making_charges_rate' => (float)($_POST['default_making_charges_rate'] ?? 400.00),

            'gold_making_charges_type' => in_array($_POST['gold_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['gold_making_charges_type'] : 'per_gram',
            'gold_making_charges_rate' => (float)($_POST['gold_making_charges_rate'] ?? 400.00),

            'silver_making_charges_type' => in_array($_POST['silver_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['silver_making_charges_type'] : 'per_gram',
            'silver_making_charges_rate' => (float)($_POST['silver_making_charges_rate'] ?? 25.00),

            'platinum_making_charges_type' => in_array($_POST['platinum_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['platinum_making_charges_type'] : 'per_gram',
            'platinum_making_charges_rate' => (float)($_POST['platinum_making_charges_rate'] ?? 750.00),
        ];

        foreach ($settingsToUpdate as $key => $val) {
            updateSetting($key, $val);
        }

        // 2. Daily Metal Rates (Gold, Silver, Platinum)
        $rates = [
            'gold_24k' => (float)($_POST['gold_24k'] ?? 0),
            'gold_22k' => (float)($_POST['gold_22k'] ?? 0),
            'gold_18k' => (float)($_POST['gold_18k'] ?? 0),
            'silver_999' => (float)($_POST['silver_999'] ?? 0),
            'silver_925' => (float)($_POST['silver_925'] ?? 0),
            'platinum' => (float)($_POST['platinum'] ?? 0)
        ];
        $effectiveDate = !empty($_POST['effective_date']) ? $_POST['effective_date'] : date('Y-m-d');

        $stmt = $pdo->prepare("INSERT INTO metal_rates (metal_type, rate_per_gram, effective_date, created_by) VALUES (?, ?, ?, ?)");
        $rateUpdated = false;
        foreach ($rates as $metal => $rate) {
            if ($rate > 0) {
                $stmt->execute([$metal, $rate, $effectiveDate, getCurrentUserId()]);
                $rateUpdated = true;
            }
        }

        logAudit(getCurrentUserId(), 'update_settings', 'settings');
        if ($rateUpdated) {
            logAudit(getCurrentUserId(), 'update_rates', 'metal_rates', null, null, $rates);
        }

        $success = 'Settings, Daily Metal Rates, and Working Charges updated successfully!';
    }
}

// Current rates & metadata
$currentRates = getCurrentMetalRates();
$lastRateUpdateStmt = $pdo->query("SELECT created_at, effective_date FROM metal_rates ORDER BY id DESC LIMIT 1");
$lastRateUpdate = $lastRateUpdateStmt->fetch();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">System & Shop Settings</h4>
        <p class="text-muted fs-sm mb-0">Configure your business credentials, daily bullion market rates, working charges (Amount / %), and billing</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/settings/metal_rates.php'); ?>" class="btn btn-outline-warning text-dark">
            <i class="bi bi-clock-history me-1"></i> Rate Change History
        </a>
        <a href="<?php echo url('modules/settings/audit_logs.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-shield-check me-1"></i> Audit Trail
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
        <div><strong>Success!</strong> <?php echo sanitize($success); ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
        <div><strong>Error:</strong> <?php echo sanitize($error); ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <form method="POST" action="" id="settingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- 1. Daily Bullion Market Rates -->
            <div class="card mb-4 border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-lightning-charge-fill text-warning me-2 fs-5"></i>
                            Daily Bullion Market Rates (दैनिक धातु भाव - ₹ / Gram)
                        </h6>
                        <small class="text-muted">Live benchmark rates automatically loaded into POS Billing and Inventory valuation</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1 fs-xs">
                            <i class="bi bi-broadcast me-1"></i> Active in POS
                        </span>
                        <?php if ($lastRateUpdate): ?>
                        <span class="text-muted fs-xs">
                            Last set: <strong><?php echo date('d M Y', strtotime($lastRateUpdate['effective_date'])); ?></strong>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4 bg-light bg-opacity-25">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-sm text-secondary">Effective Rate Date</label>
                            <input type="date" class="form-control" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <p class="text-muted fs-xs mb-2">
                                <i class="bi bi-info-circle me-1 text-primary"></i> Changing these rates saves a timestamped record in the Bullion Rate Log and immediately updates all new POS billing items.
                            </p>
                        </div>
                    </div>

                    <!-- Gold Rates Group -->
                    <div class="p-3 bg-white border rounded-3 mb-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge rounded-pill bg-warning text-dark px-3 py-1 me-2 fw-bold">GOLD (सोना)</span>
                            <span class="text-muted fs-xs">Pure Bullion 24K, Hallmark 22K (916), & Diamond 18K (750)</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fs-sm fw-bold text-dark">Gold 24K (Pure 999)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">₹</span>
                                    <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold text-dark" name="gold_24k" id="rate_gold_24k" value="<?php echo $currentRates['gold_24k'] ?? 7500; ?>" required oninput="updateWorkingChargesPreview()">
                                    <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                                </div>
                                <small class="text-muted fs-xs">Benchmark pure bullion rate</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-sm fw-bold text-warning-emphasis">Gold 22K (Hallmark 916)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-warning-subtle fw-bold text-dark">₹</span>
                                    <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold text-dark border-warning" name="gold_22k" id="rate_gold_22k" value="<?php echo $currentRates['gold_22k'] ?? 6900; ?>" required oninput="updateWorkingChargesPreview()">
                                    <span class="input-group-text bg-warning-subtle fs-xs text-dark">/ g</span>
                                </div>
                                <small class="text-muted fs-xs">Standard jewellery making rate</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-sm fw-bold text-dark">Gold 18K (Jewellery 750)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold">₹</span>
                                    <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold text-dark" name="gold_18k" id="rate_gold_18k" value="<?php echo $currentRates['gold_18k'] ?? 5625; ?>" required oninput="updateWorkingChargesPreview()">
                                    <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                                </div>
                                <small class="text-muted fs-xs">Diamond studded jewellery base</small>
                            </div>
                        </div>
                    </div>

                    <!-- Silver & Platinum Rates Group -->
                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge rounded-pill bg-secondary text-white px-3 py-1 me-2 fw-bold">SILVER (चांदी)</span>
                                    <span class="text-muted fs-xs">Pure 999 & Sterling 925</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fs-sm fw-semibold">Silver 999 (Pure)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light fw-bold">₹</span>
                                            <input type="number" step="0.01" min="1" class="form-control fw-bold" name="silver_999" id="rate_silver_999" value="<?php echo $currentRates['silver_999'] ?? 95; ?>" required oninput="updateWorkingChargesPreview()">
                                            <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fs-sm fw-semibold">Silver 925 (Sterling)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light fw-bold">₹</span>
                                            <input type="number" step="0.01" min="1" class="form-control fw-bold" name="silver_925" id="rate_silver_925" value="<?php echo $currentRates['silver_925'] ?? 88; ?>" required oninput="updateWorkingChargesPreview()">
                                            <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="p-3 bg-white border rounded-3 h-100">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge rounded-pill bg-info text-dark px-3 py-1 me-2 fw-bold">PLATINUM (प्लैटिनम)</span>
                                    <span class="text-muted fs-xs">950 Purity</span>
                                </div>
                                <div>
                                    <label class="form-label fs-sm fw-semibold">Platinum 950 Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light fw-bold">₹</span>
                                        <input type="number" step="0.01" min="1" class="form-control fw-bold" name="platinum" id="rate_platinum" value="<?php echo $currentRates['platinum'] ?? 3200; ?>" required oninput="updateWorkingChargesPreview()">
                                        <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                                    </div>
                                    <small class="text-muted fs-xs">Platinum 950 jewellery rate</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Default Working Charges / Making Charges (मजदूरी दर) -->
            <div class="card mb-4 border-0 shadow-sm" style="border-left: 4px solid #3b82f6 !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-hammer text-primary me-2 fs-5"></i>
                            Default Working Charges (कारीगरी / मजदूरी दर)
                        </h6>
                        <small class="text-muted">Set default making charges in <strong>Amount (₹)</strong> or <strong>Percentage (%)</strong> applied during inventory registration & POS billing</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-1 fs-xs">
                        <i class="bi bi-percent me-1"></i> Amount (₹) or % Supported
                    </span>
                </div>
                <div class="card-body p-4">
                    <!-- Master Default Store Configuration -->
                    <div class="p-3 rounded-3 bg-light border mb-4">
                        <div class="row align-items-center g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold mb-1">Standard Default Mode</label>
                                <p class="text-muted fs-xs mb-0">Base fallback when metal-specific rule is not applied</p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-sm fw-semibold">Calculation Mode</label>
                                <select class="form-select fw-semibold" name="default_making_charges_type" id="default_making_charges_type" onchange="onMakingModeChange('default')">
                                    <option value="per_gram" <?php echo getSetting('default_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>
                                        Amount per Gram (₹ / g)
                                    </option>
                                    <option value="percentage" <?php echo getSetting('default_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>
                                        Percentage (%) of Metal Value
                                    </option>
                                    <option value="fixed" <?php echo getSetting('default_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>
                                        Fixed Amount (₹ / Piece)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-sm fw-semibold">Default Rate / Value</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white fw-bold" id="default_prefix">₹</span>
                                    <input type="number" step="0.01" min="0" class="form-control fw-bold" name="default_making_charges_rate" id="default_making_charges_rate" value="<?php echo sanitize(getSetting('default_making_charges_rate', '400.00')); ?>" oninput="updateWorkingChargesPreview()">
                                    <span class="input-group-text bg-white text-muted fs-xs" id="default_suffix">/ g</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Per-Metal Working Charges Configuration -->
                    <h6 class="fw-bold text-secondary fs-sm mb-3 text-uppercase">
                        <i class="bi bi-grid-fill me-1 text-primary"></i> Metal-Specific Working Charges (सोना, चांदी, प्लैटिनम अलग-अलग मजदूरी)
                    </h6>
                    <div class="row g-3 mb-4">
                        <!-- Gold Working Charge Card -->
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-white h-100 shadow-xs" style="border-top: 3px solid #f59e0b !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark fs-sm"><i class="bi bi-gem text-warning me-1"></i> Gold (सोना)</span>
                                    <span class="badge bg-warning-subtle text-dark border border-warning fs-xs">Gold Making</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fs-xs text-muted mb-1">Charge Type</label>
                                    <select class="form-select form-select-sm fw-semibold" name="gold_making_charges_type" id="gold_making_charges_type" onchange="onMakingModeChange('gold')">
                                        <option value="per_gram" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>
                                            Amount (₹ / gram)
                                        </option>
                                        <option value="percentage" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>
                                            Percentage (%)
                                        </option>
                                        <option value="fixed" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>
                                            Fixed (₹ / piece)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label fs-xs text-muted mb-1">Gold Charge Value</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light fw-bold" id="gold_prefix">₹</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" name="gold_making_charges_rate" id="gold_making_charges_rate" value="<?php echo sanitize(getSetting('gold_making_charges_rate', '400.00')); ?>" oninput="updateWorkingChargesPreview()">
                                        <span class="input-group-text bg-light fs-xs text-muted" id="gold_suffix">/ g</span>
                                    </div>
                                    <small class="text-muted fs-xs d-block mt-1" id="gold_hint">E.g. ₹400/g or 10% on gold value</small>
                                </div>
                            </div>
                        </div>

                        <!-- Silver Working Charge Card -->
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-white h-100 shadow-xs" style="border-top: 3px solid #64748b !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark fs-sm"><i class="bi bi-disc text-secondary me-1"></i> Silver (चांदी)</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary fs-xs">Silver Making</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fs-xs text-muted mb-1">Charge Type</label>
                                    <select class="form-select form-select-sm fw-semibold" name="silver_making_charges_type" id="silver_making_charges_type" onchange="onMakingModeChange('silver')">
                                        <option value="per_gram" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>
                                            Amount (₹ / gram)
                                        </option>
                                        <option value="percentage" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>
                                            Percentage (%)
                                        </option>
                                        <option value="fixed" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>
                                            Fixed (₹ / piece)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label fs-xs text-muted mb-1">Silver Charge Value</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light fw-bold" id="silver_prefix">₹</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" name="silver_making_charges_rate" id="silver_making_charges_rate" value="<?php echo sanitize(getSetting('silver_making_charges_rate', '25.00')); ?>" oninput="updateWorkingChargesPreview()">
                                        <span class="input-group-text bg-light fs-xs text-muted" id="silver_suffix">/ g</span>
                                    </div>
                                    <small class="text-muted fs-xs d-block mt-1" id="silver_hint">E.g. ₹25/g or 12% on silver value</small>
                                </div>
                            </div>
                        </div>

                        <!-- Platinum Working Charge Card -->
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-white h-100 shadow-xs" style="border-top: 3px solid #0284c7 !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark fs-sm"><i class="bi bi-shield-shaded text-info me-1"></i> Platinum (प्लैटिनम)</span>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info fs-xs">Platinum Making</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fs-xs text-muted mb-1">Charge Type</label>
                                    <select class="form-select form-select-sm fw-semibold" name="platinum_making_charges_type" id="platinum_making_charges_type" onchange="onMakingModeChange('platinum')">
                                        <option value="per_gram" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>
                                            Amount (₹ / gram)
                                        </option>
                                        <option value="percentage" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>
                                            Percentage (%)
                                        </option>
                                        <option value="fixed" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>
                                            Fixed (₹ / piece)
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label fs-xs text-muted mb-1">Platinum Charge Value</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light fw-bold" id="platinum_prefix">₹</span>
                                        <input type="number" step="0.01" min="0" class="form-control fw-bold" name="platinum_making_charges_rate" id="platinum_making_charges_rate" value="<?php echo sanitize(getSetting('platinum_making_charges_rate', '750.00')); ?>" oninput="updateWorkingChargesPreview()">
                                        <span class="input-group-text bg-light fs-xs text-muted" id="platinum_suffix">/ g</span>
                                    </div>
                                    <small class="text-muted fs-xs d-block mt-1" id="platinum_hint">E.g. ₹750/g or 15% on platinum value</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculation Simulation Banner -->
                    <div class="p-3 rounded-3 bg-white border border-primary-subtle shadow-xs">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                    <i class="bi bi-calculator fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-sm">Live Calculation Preview (मजदूरी गणना उदाहरण):</div>
                                    <div class="text-muted fs-xs" id="calcPreviewText">
                                        Calculating based on your current rates...
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fs-xs text-muted d-block">Estimated Making on 10g Gold (22K)</span>
                                <span class="fs-5 fw-bold text-primary" id="calcPreviewMaking">₹0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Showroom / Business Credentials -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shop me-2 text-primary"></i>Showroom / Business Details</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-sm">Shop / Business Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="shop_name" required value="<?php echo sanitize(getSetting('shop_name', 'Sachin jewellers')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-sm">GSTIN / Tax ID</label>
                            <input type="text" class="form-control" name="shop_gstin" value="<?php echo sanitize(getSetting('shop_gstin', '')); ?>" placeholder="e.g. 27AABCS1429B1ZB">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-sm">Official Phone / Helpline</label>
                            <input type="text" class="form-control" name="shop_phone" value="<?php echo sanitize(getSetting('shop_phone', '')); ?>" placeholder="+91 98765 43210">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-sm">Official Email</label>
                            <input type="email" class="form-control" name="shop_email" value="<?php echo sanitize(getSetting('shop_email', '')); ?>" placeholder="info@jewelleryshop.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-sm">Store Address (Printed on Invoices)</label>
                            <textarea class="form-control" name="shop_address" rows="2"><?php echo sanitize(getSetting('shop_address', '')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Invoicing & Billing Configuration -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Billing & Tax Configuration</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-sm">Invoice Number Prefix</label>
                            <input type="text" class="form-control" name="invoice_prefix" value="<?php echo sanitize(getSetting('invoice_prefix', 'INV')); ?>" placeholder="INV">
                            <small class="text-muted fs-xs">e.g. INV produces INV-001001</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-sm">Standard Jewellery GST (%)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" name="gst_rate" value="<?php echo sanitize(getSetting('gst_rate', '3.00')); ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted fs-xs">Standard rate (split 1.5% CGST + 1.5% SGST)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-sm">Currency Symbol</label>
                            <input type="text" class="form-control" name="currency_symbol" value="<?php echo sanitize(getSetting('currency_symbol', '₹')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-sm">Low Stock Alert Threshold (pcs)</label>
                            <input type="number" min="1" step="1" class="form-control" name="low_stock_threshold" value="<?php echo sanitize(getSetting('low_stock_threshold', '3')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg px-4 shadow">
                    <i class="bi bi-check2-circle me-2"></i> Save All Settings & Daily Rates
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function onMakingModeChange(prefix) {
    const typeSelect = document.getElementById(prefix + '_making_charges_type');
    const prefixEl = document.getElementById(prefix + '_prefix');
    const suffixEl = document.getElementById(prefix + '_suffix');
    const hintEl = document.getElementById(prefix + '_hint');

    const val = typeSelect.value;
    if (val === 'percentage') {
        prefixEl.innerText = '%';
        suffixEl.innerText = 'of val';
        if (hintEl) hintEl.innerText = 'Percentage calculated on metal value';
    } else if (val === 'fixed') {
        prefixEl.innerText = '₹';
        suffixEl.innerText = 'flat';
        if (hintEl) hintEl.innerText = 'Fixed lump sum charge per piece';
    } else {
        prefixEl.innerText = '₹';
        suffixEl.innerText = '/ g';
        if (hintEl) hintEl.innerText = 'Per gram amount multiplied by net weight';
    }
    updateWorkingChargesPreview();
}

function updateWorkingChargesPreview() {
    const goldRate = parseFloat(document.getElementById('rate_gold_22k')?.value) || 6900;
    const goldMakingType = document.getElementById('gold_making_charges_type')?.value || 'per_gram';
    const goldMakingRate = parseFloat(document.getElementById('gold_making_charges_rate')?.value) || 0;

    const sampleWeight = 10.0; // 10 grams
    const metalVal = sampleWeight * goldRate;
    let makingTotal = 0;

    let desc = '';
    if (goldMakingType === 'percentage') {
        makingTotal = (metalVal * goldMakingRate) / 100;
        desc = `10g Gold (22K) @ ₹${goldRate.toLocaleString('en-IN')}/g = ₹${metalVal.toLocaleString('en-IN')} metal value + <strong>${goldMakingRate}% making</strong>`;
    } else if (goldMakingType === 'fixed') {
        makingTotal = goldMakingRate;
        desc = `10g Gold (22K) @ ₹${goldRate.toLocaleString('en-IN')}/g = ₹${metalVal.toLocaleString('en-IN')} metal value + <strong>₹${goldMakingRate.toLocaleString('en-IN')} flat making</strong>`;
    } else {
        makingTotal = sampleWeight * goldMakingRate;
        desc = `10g Gold (22K) @ ₹${goldRate.toLocaleString('en-IN')}/g = ₹${metalVal.toLocaleString('en-IN')} metal value + <strong>₹${goldMakingRate.toLocaleString('en-IN')}/g making</strong>`;
    }

    const previewEl = document.getElementById('calcPreviewText');
    const makingEl = document.getElementById('calcPreviewMaking');
    if (previewEl) previewEl.innerHTML = desc;
    if (makingEl) makingEl.innerText = '₹' + makingTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    onMakingModeChange('default');
    onMakingModeChange('gold');
    onMakingModeChange('silver');
    onMakingModeChange('platinum');
    updateWorkingChargesPreview();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
