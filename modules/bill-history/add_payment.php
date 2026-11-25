<?php
/**
 * Record Due Payment against Invoice
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$invoiceId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    redirect(url('modules/bill-history/index.php'), 'Invoice not found.', 'danger');
}

if ($invoice['status'] === 'cancelled') {
    redirect(url('modules/bill-history/view.php?id=' . $invoiceId), 'Cannot accept payment for a cancelled invoice.', 'danger');
}

if ($invoice['due_amount'] <= 0) {
    redirect(url('modules/bill-history/view.php?id=' . $invoiceId), 'This invoice has already been fully paid!', 'info');
}

$pageTitle = 'Record Due Payment - ' . $invoice['invoice_number'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token.';
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $payDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $payMethod = $_POST['payment_method'] ?? 'cash';
        $refNo = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) {
            $error = 'Please enter a valid payment amount greater than zero.';
        } elseif ($amount > (float)$invoice['due_amount']) {
            $error = 'Payment amount cannot exceed the pending due balance of ₹' . number_format($invoice['due_amount'], 2);
        } else {
            $pdo->beginTransaction();
            try {
                // 1. Insert into payments
                $pStmt = $pdo->prepare("INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $pStmt->execute([$invoiceId, $payDate, $amount, $payMethod, $refNo, $notes, getCurrentUserId()]);

                // 2. Update invoice paid & due
                $newPaid = (float)$invoice['paid_amount'] + $amount;
                $newDue = max(0, (float)$invoice['due_amount'] - $amount);
                $newStatus = $newDue <= 0 ? 'paid' : 'partial';

                $uInv = $pdo->prepare("UPDATE invoices SET paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?");
                $uInv->execute([$newPaid, $newDue, $newStatus, $invoiceId]);

                // 3. Update customer outstanding balance
                if ($invoice['customer_id']) {
                    $uCust = $pdo->prepare("UPDATE customers SET outstanding_balance = GREATEST(0, outstanding_balance - ?) WHERE id = ?");
                    $uCust->execute([$amount, $invoice['customer_id']]);
                }

                // 4. Audit Log
                logAudit(getCurrentUserId(), 'payment', 'invoices', $invoiceId, ['due' => $invoice['due_amount']], ['paid' => $amount, 'new_due' => $newDue]);

                $pdo->commit();
                redirect(url('modules/bill-history/view.php?id=' . $invoiceId), "Payment of ₹" . number_format($amount, 2) . " recorded successfully!");

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to record payment: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Collect Due Payment</h4>
                <p class="text-muted fs-sm mb-0">Record payment against bill <strong><?php echo sanitize($invoice['invoice_number']); ?></strong></p>
            </div>
            <a href="<?php echo url('modules/bill-history/view.php?id=' . $invoice['id']); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- Invoice Balance Summary Card -->
        <div class="card mb-4 bg-light border">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Customer Name:</span>
                    <strong><?php echo sanitize($invoice['customer_name'] ?: 'Cash Customer'); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Bill Grand Total:</span>
                    <strong class="text-dark"><?php echo formatCurrency($invoice['final_amount']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Previously Paid:</span>
                    <strong class="text-success"><?php echo formatCurrency($invoice['paid_amount']); ?></strong>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center fs-5">
                    <span class="text-danger fw-bold">Current Pending Due:</span>
                    <span class="text-danger fw-bold fs-4"><?php echo formatCurrency($invoice['due_amount']); ?></span>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" max="<?php echo $invoice['due_amount']; ?>" class="form-control form-control-lg fw-bold text-success" name="amount" required value="<?php echo $invoice['due_amount']; ?>">
                        </div>
                        <small class="text-muted fs-xs">Defaults to total remaining balance. You can enter a partial payment amount.</small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                                <option value="card">Debit / Credit Card</option>
                                <option value="bank_transfer">Bank Transfer / NEFT</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Transaction / Cheque / UTR #</label>
                        <input type="text" class="form-control" name="reference_number" placeholder="Optional transaction ID">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Remarks</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Cleared remaining balance via UPI"></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-1"></i> Confirm & Record Payment
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

