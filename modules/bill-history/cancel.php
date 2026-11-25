<?php
/**
 * Cancel Invoice with Inventory Reversal
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$invoiceId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    redirect(url('modules/bill-history/index.php'), 'Invoice not found.', 'danger');
}

if ($invoice['status'] === 'cancelled') {
    redirect(url('modules/bill-history/view.php?id=' . $invoiceId), 'This invoice has already been cancelled.', 'warning');
}

$pageTitle = 'Cancel Invoice ' . $invoice['invoice_number'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $reason = trim($_POST['cancel_reason'] ?? '');
        if (empty($reason)) {
            $error = 'Please enter a valid cancellation reason.';
        } else {
            $pdo->beginTransaction();
            try {
                // 1. Fetch items to restore stock
                $itemsStmt = $pdo->prepare("SELECT product_id, quantity, product_name FROM invoice_items WHERE invoice_id = ?");
                $itemsStmt->execute([$invoiceId]);
                $items = $itemsStmt->fetchAll();

                $stockSelect = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                $stockUpdate = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $txInsert = $pdo->prepare("INSERT INTO inventory_transactions (
                    product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, created_by
                ) VALUES (?, 'cancel_reversal', ?, ?, ?, 'invoice', ?, ?, ?)");

                foreach ($items as $it) {
                    if ($it['product_id']) {
                        $stockSelect->execute([$it['product_id']]);
                        $prod = $stockSelect->fetch();
                        if ($prod) {
                            $prev = (int)$prod['stock_quantity'];
                            $new = $prev + (int)$it['quantity'];

                            $stockUpdate->execute([$it['quantity'], $it['product_id']]);
                            $txInsert->execute([
                                $it['product_id'],
                                $it['quantity'],
                                $prev,
                                $new,
                                $invoiceId,
                                "Restored via cancellation of invoice {$invoice['invoice_number']}: {$reason}",
                                getCurrentUserId()
                            ]);
                        }
                    }
                }

                // 2. Mark invoice as cancelled
                $upInv = $pdo->prepare("UPDATE invoices SET 
                    status = 'cancelled', 
                    cancel_reason = ?, 
                    cancelled_at = NOW(), 
                    cancelled_by = ? 
                    WHERE id = ?");
                $upInv->execute([$reason, getCurrentUserId(), $invoiceId]);

                // 3. Reverse customer balances
                if ($invoice['customer_id']) {
                    $upCust = $pdo->prepare("UPDATE customers SET 
                        total_purchases = GREATEST(0, total_purchases - ?), 
                        outstanding_balance = GREATEST(0, outstanding_balance - ?) 
                        WHERE id = ?");
                    $upCust->execute([$invoice['final_amount'], $invoice['due_amount'], $invoice['customer_id']]);
                }

                // 4. Audit Log
                logAudit(getCurrentUserId(), 'cancel', 'invoices', $invoiceId, ['status' => 'finalized'], ['status' => 'cancelled', 'reason' => $reason]);

                // 5. Notification
                $pdo->prepare("INSERT INTO notifications (title, message, type, link) VALUES (?, ?, 'danger', ?)")
                    ->execute([
                        "Invoice Cancelled: {$invoice['invoice_number']}",
                        "Bill #{$invoice['invoice_number']} cancelled. Reason: {$reason}. Inventory stock reversed.",
                        "modules/bill-history/view.php?id={$invoiceId}"
                    ]);

                $pdo->commit();
                redirect(url('modules/bill-history/view.php?id=' . $invoiceId), "Invoice {$invoice['invoice_number']} has been cancelled and inventory restored.");

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to cancel invoice: ' . $e->getMessage();
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
                <h4 class="mb-1 fw-bold text-danger">Cancel Invoice: <?php echo sanitize($invoice['invoice_number']); ?></h4>
                <p class="text-muted fs-sm mb-0">Invoice Date: <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?> &bull; Amount: <?php echo formatCurrency($invoice['final_amount']); ?></p>
            </div>
            <a href="<?php echo url('modules/bill-history/view.php?id=' . $invoice['id']); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Invoice
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- Warning Callout -->
        <div class="card mb-4 border-danger">
            <div class="card-body bg-danger-light">
                <div class="d-flex gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-2 text-danger"></i>
                    <div>
                        <h6 class="fw-bold text-danger mb-1">Important Cancellation Notice</h6>
                        <ul class="mb-0 fs-sm text-dark ps-3">
                            <li>All sold items in this bill will be automatically returned to inventory stock.</li>
                            <li>A reversal record will be permanently logged in the stock movement ledger.</li>
                            <li>The customer's outstanding credit balance and total purchases will be reduced accordingly.</li>
                            <li>Invoices are never deleted to preserve GST and tax compliance audit trails.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Name</label>
                        <input type="text" class="form-control bg-light" readonly value="<?php echo sanitize($invoice['customer_name'] ?: 'Cash Customer'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="cancel_reason" rows="3" required placeholder="State exact reason e.g. Customer returned items, wrong purity selected, duplicate bill generated, or payment declined."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?php echo url('modules/bill-history/view.php?id=' . $invoice['id']); ?>" class="btn btn-outline-secondary">
                            Do Not Cancel
                        </a>
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="bi bi-x-circle-fill me-1"></i> Confirm Cancellation & Revert Stock
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

