<?php
/**
 * Daily Metal Rates & Working Charges Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Daily Metal Rates & Working Charges';
$pdo = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        // Bullion Rates
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

        // Working charges settings (Amount / Percentage)
        $workingChargesSettings = [
            'default_making_charges_type' => in_array($_POST['default_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['default_making_charges_type'] : 'per_gram',
            'default_making_charges_rate' => (float)($_POST['default_making_charges_rate'] ?? 400.00),

            'gold_making_charges_type' => in_array($_POST['gold_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['gold_making_charges_type'] : 'per_gram',
            'gold_making_charges_rate' => (float)($_POST['gold_making_charges_rate'] ?? 400.00),

            'silver_making_charges_type' => in_array($_POST['silver_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['silver_making_charges_type'] : 'per_gram',
            'silver_making_charges_rate' => (float)($_POST['silver_making_charges_rate'] ?? 25.00),

            'platinum_making_charges_type' => in_array($_POST['platinum_making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $_POST['platinum_making_charges_type'] : 'per_gram',
            'platinum_making_charges_rate' => (float)($_POST['platinum_making_charges_rate'] ?? 750.00),
        ];

        foreach ($workingChargesSettings as $key => $val) {
            updateSetting($key, $val);
        }

        logAudit(getCurrentUserId(), 'update_rates', 'metal_rates', null, null, $rates);
        logAudit(getCurrentUserId(), 'update_working_charges', 'settings', null, null, $workingChargesSettings);

        $success = 'Bullion market rates & working charges updated successfully for ' . date('d M Y', strtotime($effectiveDate)) . '!';
    }
}

// Current rates
$currentRates = getCurrentMetalRates();

// Historical rate log
$historyStmt = $pdo->query("SELECT mr.*, u.username as updater_name 
                            FROM metal_rates mr 
                            LEFT JOIN users u ON mr.created_by = u.id 
                            ORDER BY mr.effective_date DESC, mr.id DESC LIMIT 30");
$rateHistory = $historyStmt->fetchAll();

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Daily Metal Rates & Working Charges Board</h4>
        <p class="text-muted fs-sm mb-0">Set live gold, silver, and platinum bullion market benchmarks & default making charges (Amount or %) for POS billing and inventory</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/settings/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> Shop Settings
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> <?php echo sanitize($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo sanitize($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Rate & Working Charge Update Form -->
    <div class="col-lg-5">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- Metal Rates Card -->
            <div class="card mb-4 border-0 shadow-sm" style="border-top: 4px solid #f59e0b !important;">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Live Bullion Rates (₹ / Gram)</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fs-sm fw-semibold">Effective Date</label>
                        <input type="date" class="form-control" name="effective_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-sm fw-bold text-warning-emphasis">Gold 24K (Pure Bullion 999)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold" name="gold_24k" value="<?php echo $currentRates['gold_24k'] ?? 7500; ?>" required>
                            <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-sm fw-bold text-warning-emphasis">Gold 22K (916 Standard Jewellery)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-warning-subtle fw-bold text-dark">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold border-warning" name="gold_22k" value="<?php echo $currentRates['gold_22k'] ?? 6900; ?>" required>
                            <span class="input-group-text bg-warning-subtle fs-xs text-dark">/ g</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-sm fw-bold text-warning-emphasis">Gold 18K (750 Diamond Mountings)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control form-control-lg fw-bold" name="gold_18k" value="<?php echo $currentRates['gold_18k'] ?? 5625; ?>" required>
                            <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fs-sm fw-semibold">Silver 999 (Pure)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">₹</span>
                                <input type="number" step="0.01" min="1" class="form-control fw-bold" name="silver_999" value="<?php echo $currentRates['silver_999'] ?? 95; ?>" required>
                                <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-sm fw-semibold">Silver 925 (Sterling)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">₹</span>
                                <input type="number" step="0.01" min="1" class="form-control fw-bold" name="silver_925" value="<?php echo $currentRates['silver_925'] ?? 88; ?>" required>
                                <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fs-sm fw-semibold">Platinum 950</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">₹</span>
                            <input type="number" step="0.01" min="1" class="form-control fw-bold" name="platinum" value="<?php echo $currentRates['platinum'] ?? 3200; ?>" required>
                            <span class="input-group-text bg-light fs-xs text-muted">/ g</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Working Charges Card -->
            <div class="card mb-4 border-0 shadow-sm" style="border-top: 4px solid #3b82f6 !important;">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-hammer me-2 text-primary"></i>Working Charges / मजदूरी (Amount or %)</h6>
                </div>
                <div class="card-body">
                    <!-- Gold Making -->
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fs-sm fw-bold mb-0 text-dark"><i class="bi bi-gem text-warning me-1"></i> Gold Making Charge</label>
                            <span class="text-muted fs-xs">Amount (₹) or %</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <select class="form-select fw-semibold" style="max-width: 130px;" name="gold_making_charges_type" id="mr_gold_type" onchange="syncMrMaking('mr_gold')">
                                <option value="per_gram" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>Per Gram (₹/g)</option>
                                <option value="percentage" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo getSetting('gold_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>Fixed (₹)</option>
                            </select>
                            <input type="number" step="0.01" min="0" class="form-control fw-bold" name="gold_making_charges_rate" id="mr_gold_rate" value="<?php echo sanitize(getSetting('gold_making_charges_rate', '400.00')); ?>">
                            <span class="input-group-text bg-light" id="mr_gold_suf">/ g</span>
                        </div>
                    </div>

                    <!-- Silver Making -->
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fs-sm fw-bold mb-0 text-dark"><i class="bi bi-disc text-secondary me-1"></i> Silver Making Charge</label>
                            <span class="text-muted fs-xs">Amount (₹) or %</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <select class="form-select fw-semibold" style="max-width: 130px;" name="silver_making_charges_type" id="mr_silver_type" onchange="syncMrMaking('mr_silver')">
                                <option value="per_gram" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>Per Gram (₹/g)</option>
                                <option value="percentage" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo getSetting('silver_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>Fixed (₹)</option>
                            </select>
                            <input type="number" step="0.01" min="0" class="form-control fw-bold" name="silver_making_charges_rate" id="mr_silver_rate" value="<?php echo sanitize(getSetting('silver_making_charges_rate', '25.00')); ?>">
                            <span class="input-group-text bg-light" id="mr_silver_suf">/ g</span>
                        </div>
                    </div>

                    <!-- Platinum Making -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fs-sm fw-bold mb-0 text-dark"><i class="bi bi-shield-shaded text-info me-1"></i> Platinum Making Charge</label>
                            <span class="text-muted fs-xs">Amount (₹) or %</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <select class="form-select fw-semibold" style="max-width: 130px;" name="platinum_making_charges_type" id="mr_platinum_type" onchange="syncMrMaking('mr_platinum')">
                                <option value="per_gram" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'per_gram' ? 'selected' : ''; ?>>Per Gram (₹/g)</option>
                                <option value="percentage" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo getSetting('platinum_making_charges_type', 'per_gram') === 'fixed' ? 'selected' : ''; ?>>Fixed (₹)</option>
                            </select>
                            <input type="number" step="0.01" min="0" class="form-control fw-bold" name="platinum_making_charges_rate" id="mr_platinum_rate" value="<?php echo sanitize(getSetting('platinum_making_charges_rate', '750.00')); ?>">
                            <span class="input-group-text bg-light" id="mr_platinum_suf">/ g</span>
                        </div>
                    </div>

                    <input type="hidden" name="default_making_charges_type" value="<?php echo sanitize(getSetting('default_making_charges_type', 'per_gram')); ?>">
                    <input type="hidden" name="default_making_charges_rate" value="<?php echo sanitize(getSetting('default_making_charges_rate', '400.00')); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 shadow mb-4">
                <i class="bi bi-check-circle me-1"></i> Publish Live Rates & Working Charges
            </button>
        </form>
    </div>

    <!-- Historical Rates Log -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Bullion Rate Modification History</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Effective Date</th>
                                <th>Metal / Purity</th>
                                <th>Rate / Gram</th>
                                <th>Updated By</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rateHistory)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No rate change records logged.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($rateHistory as $rh): ?>
                            <tr>
                                <td class="fw-semibold text-dark">
                                    <?php echo date('d M Y', strtotime($rh['effective_date'])); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo strtoupper(str_replace('_', ' ', $rh['metal_type'])); ?></span>
                                </td>
                                <td>
                                    <strong class="text-primary fs-6">₹<?php echo number_format($rh['rate_per_gram'], 2); ?></strong>
                                </td>
                                <td class="text-muted fs-sm">
                                    <?php echo sanitize($rh['updater_name'] ?: 'Admin'); ?>
                                </td>
                                <td class="text-muted fs-xs">
                                    <?php echo date('d M, h:i A', strtotime($rh['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncMrMaking(idPrefix) {
    const type = document.getElementById(idPrefix + '_type')?.value;
    const suf = document.getElementById(idPrefix + '_suf');
    if (!suf) return;
    if (type === 'percentage') {
        suf.innerText = '%';
    } else if (type === 'fixed') {
        suf.innerText = 'flat';
    } else {
        suf.innerText = '/ g';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    syncMrMaking('mr_gold');
    syncMrMaking('mr_silver');
    syncMrMaking('mr_platinum');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
