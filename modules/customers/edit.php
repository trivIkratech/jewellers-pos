<?php
/**
 * Edit Customer
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$customerId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect(url('modules/customers/index.php'), 'Customer not found.', 'danger');
}

$pageTitle = 'Edit Customer';
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
            // Check if phone already exists for another customer
            $chk = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
            $chk->execute([$phone, $customerId]);
            if ($chk->fetch()) {
                $error = 'Another customer with phone number ' . sanitize($phone) . ' already exists.';
            } else {
                $updateStmt = $pdo->prepare("UPDATE customers SET 
                    name = ?, phone = ?, email = ?, address = ?, city = ?, state = ?, pincode = ?, gstin = ?, pan = ?, notes = ? 
                    WHERE id = ?");
                $updateStmt->execute([$name, $phone, $email, $address, $city, $state, $pincode, $gstin, $pan, $notes, $customerId]);

                logAudit(getCurrentUserId(), 'update', 'customers', $customerId, ['name' => $customer['name']], ['name' => $name]);

                redirect(url('modules/customers/view.php?id=' . $customerId), "Customer {$name} updated successfully!");
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
                <h4 class="mb-1 fw-bold text-dark">Edit Customer Details</h4>
                <p class="text-muted fs-sm mb-0">Update contact, address, or tax profile</p>
            </div>
            <a href="<?php echo url('modules/customers/view.php?id=' . $customerId); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Profile
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
                            <input type="text" class="form-control" name="name" required value="<?php echo sanitize($_POST['name'] ?? $customer['name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required value="<?php echo sanitize($_POST['phone'] ?? $customer['phone']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?php echo sanitize($_POST['email'] ?? $customer['email']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">GSTIN</label>
                            <input type="text" class="form-control" name="gstin" value="<?php echo sanitize($_POST['gstin'] ?? $customer['gstin']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">PAN Number</label>
                            <input type="text" class="form-control" name="pan" value="<?php echo sanitize($_POST['pan'] ?? $customer['pan']); ?>">
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
                            <input type="text" class="form-control" name="address" value="<?php echo sanitize($_POST['address'] ?? $customer['address']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" value="<?php echo sanitize($_POST['city'] ?? $customer['city']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" value="<?php echo sanitize($_POST['state'] ?? $customer['state']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" class="form-control" name="pincode" value="<?php echo sanitize($_POST['pincode'] ?? $customer['pincode']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"><?php echo sanitize($_POST['notes'] ?? $customer['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <a href="<?php echo url('modules/customers/view.php?id=' . $customerId); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Update Customer
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

