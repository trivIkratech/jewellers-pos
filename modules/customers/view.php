<?php
/**
 * Customer 360 Profile & Purchase Ledger
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

$pageTitle = 'Customer Profile: ' . $customer['name'];

// Invoices for this customer
$invStmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC, id DESC");
$invStmt->execute([$customerId]);
$invoices = $invStmt->fetchAll();

// Payments made by this customer
$payStmt = $pdo->prepare("SELECT p.*, i.invoice_number 
                         FROM payments p 
                         JOIN invoices i ON p.invoice_id = i.id 
                         WHERE i.customer_id = ? 
                         ORDER BY p.payment_date DESC, p.id DESC");
$payStmt->execute([$customerId]);
$payments = $payStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div class="user-avatar" style="width: 52px; height: 52px; font-size: 22px;">
            <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
        </div>
        <div>
            <h4 class="mb-0 fw-bold text-dark"><?php echo sanitize($customer['name']); ?></h4>
            <span class="text-muted fs-sm"><i class="bi bi-telephone me-1"></i><?php echo sanitize($customer['phone']); ?></span>
            <?php if ($customer['city']): ?>
            <span class="text-muted fs-sm ms-2">&bull; <?php echo sanitize($customer['city']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/customers/edit.php?id=' . $customer['id']); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i> Edit Profile
        </a>
        <a href="<?php echo url('modules/billing/index.php'); ?>" class="btn btn-primary">
            <i class="bi bi-receipt me-1"></i> New Bill for Customer
        </a>
    </div>
</div>

<!-- Customer Metrics -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($customer['total_purchases']); ?></h3>
                <p>Lifetime Jewellery Purchases</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon <?php echo (float)$customer['outstanding_balance'] > 0 ? 'danger' : 'success'; ?>">
                <i class="bi bi-exclamation-circle"></i>
            </div>
            <div class="stat-details">
                <h3 class="<?php echo (float)$customer['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCurrency($customer['outstanding_balance']); ?>
                </h3>
                <p>Current Outstanding Balance</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-receipt"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo count($invoices); ?> Invoices</h3>
                <p>Total Purchase Transactions</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Profile & Contact Card -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Contact Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Phone Number</small>
                    <strong><?php echo sanitize($customer['phone']); ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Email Address</small>
                    <span><?php echo sanitize($customer['email'] ?: 'Not provided'); ?></span>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Billing Address</small>
                    <span><?php echo nl2br(sanitize($customer['address'] ?: 'Not provided')); ?></span>
                    <?php if ($customer['city'] || $customer['state']): ?>
                    <div class="text-muted fs-xs mt-1">
                        <?php echo sanitize($customer['city']); ?><?php if ($customer['state']) echo ', ' . sanitize($customer['state']); ?><?php if ($customer['pincode']) echo ' - ' . sanitize($customer['pincode']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">GSTIN</small>
                    <span><?php echo sanitize($customer['gstin'] ?: 'Unregistered / Consumer'); ?></span>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">PAN</small>
                    <span><?php echo sanitize($customer['pan'] ?: 'Not on record'); ?></span>
                </div>
                <?php if ($customer['notes']): ?>
                <div>
                    <small class="text-muted d-block">Notes</small>
                    <div class="p-2 bg-light rounded fs-xs text-muted"><?php echo sanitize($customer['notes']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Purchase Invoices List -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Purchase Invoice History</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Bill Total</th>
                                <th>Paid</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No purchase invoices found for this customer.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($invoices as $inv): 
                                $isCancelled = $inv['status'] === 'cancelled';
                            ?>
                            <tr class="<?php echo $isCancelled ? 'opacity-50 text-decoration-line-through' : ''; ?>">
                                <td>
                                    <strong class="text-primary"><?php echo sanitize($inv['invoice_number']); ?></strong>
                                </td>
                                <td class="text-muted fs-sm">
                                    <?php echo date('d M Y', strtotime($inv['invoice_date'])); ?>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($inv['final_amount']); ?></strong>
                                </td>
                                <td class="text-success">
                                    <?php echo formatCurrency($inv['paid_amount']); ?>
                                </td>
                                <td>
                                    <?php if ($inv['due_amount'] > 0 && !$isCancelled): ?>
                                    <span class="badge bg-danger">₹<?php echo number_format($inv['due_amount'], 2); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted fs-xs">₹0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isCancelled): ?>
                                    <span class="badge bg-secondary">Cancelled</span>
                                    <?php else: ?>
                                    <span class="status-badge <?php echo $inv['payment_status']; ?>">
                                        <?php echo ucfirst($inv['payment_status']); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo url('modules/bill-history/view.php?id=' . $inv['id']); ?>" class="btn btn-sm btn-outline-secondary" title="View Bill Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo url('modules/bill-history/print.php?id=' . $inv['id']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Invoice">
                                        <i class="bi bi-printer"></i>
                                    </a>
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

<!-- Payments Ledger -->
<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Customer Payment Ledger</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Against Invoice #</th>
                        <th>Payment Method</th>
                        <th>Reference / Txn ID</th>
                        <th>Amount Paid</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            No payment transactions recorded for this customer.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td class="text-muted fs-sm">
                            <?php echo date('d M Y', strtotime($pay['payment_date'])); ?>
                        </td>
                        <td>
                            <strong class="text-primary"><?php echo sanitize($pay['invoice_number']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?php echo strtoupper($pay['payment_method']); ?></span>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($pay['reference_number'] ?: '-'); ?>
                        </td>
                        <td>
                            <strong class="text-success"><?php echo formatCurrency($pay['amount']); ?></strong>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo sanitize($pay['notes'] ?: '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

