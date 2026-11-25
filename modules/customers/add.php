<?php
/**
 * Add Customer
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'Add Customer';
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $pan = trim($_POST['pan'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name) || empty($phone)) {
            $error = 'Customer name and phone number are required.';
        } else {
            // Check if phone already exists
            $chk = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
            $chk->execute([$phone]);
            if ($chk->fetch()) {
                $error = 'A customer with phone number ' . sanitize($phone) . ' already exists.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address, city, state, pincode, gstin, pan, notes) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $address, $city, $state, $pincode, $gstin, $pan, $notes]);
                $custId = $pdo->lastInsertId();

                logAudit(getCurrentUserId(), 'create', 'customers', $custId, null, ['name' => $name, 'phone' => $phone]);

                redirect(url('modules/customers/view.php?id=' . $custId), "Customer {$name} added successfully!");
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Add New Customer</h4>
                <p class="text-muted fs-sm mb-0">Record customer contact, tax credentials, and billing profile</p>
            </div>
            <a href="<?php echo url('modules/customers/index.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Personal & Contact Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g. Priya Patel" value="<?php echo sanitize($_POST['name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required placeholder="10-digit mobile number" value="<?php echo sanitize($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" placeholder="customer@example.com" value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">GSTIN (Optional)</label>
                            <input type="text" class="form-control" name="gstin" placeholder="GST number" value="<?php echo sanitize($_POST['gstin'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PAN Number</label>
                            <input type="text" class="form-control" name="pan" placeholder="PAN for high value sales" value="<?php echo sanitize($_POST['pan'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Address & Notes</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Street / Apartment Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Flat No., Building Name, Street" value="<?php echo sanitize($_POST['address'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" placeholder="City" value="<?php echo sanitize($_POST['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" placeholder="State" value="<?php echo sanitize($_POST['state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" class="form-control" name="pincode" placeholder="Postal Code" value="<?php echo sanitize($_POST['pincode'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Customer Notes / Preferences</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="e.g. VIP client, prefers 22K temple jewellery, anniversary in December."><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <a href="<?php echo url('modules/customers/index.php'); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

