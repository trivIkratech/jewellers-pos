<?php
/**
 * View Invoice Details
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$invoiceId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT i.*, u.username as creator_name, c.name as full_cust_name, c.phone as full_cust_phone, c.email as cust_email, c.address as full_cust_address, cu.username as canceller_name 
                       FROM invoices i 
                       LEFT JOIN users u ON i.created_by = u.id 
                       LEFT JOIN users cu ON i.cancelled_by = cu.id 
                       LEFT JOIN customers c ON i.customer_id = c.id 
                       WHERE i.id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    redirect(url('modules/bill-history/index.php'), 'Invoice not found.', 'danger');
}

$pageTitle = 'Invoice ' . $invoice['invoice_number'];

// Fetch invoice items
$itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll();

// Fetch payments recorded against this invoice
$payStmt = $pdo->prepare("SELECT p.*, u.username as user_name 
                         FROM payments p 
                         LEFT JOIN users u ON p.created_by = u.id 
                         WHERE p.invoice_id = ? 
                         ORDER BY p.payment_date ASC, p.id ASC");
$payStmt->execute([$invoiceId]);
$payments = $payStmt->fetchAll();

$isCancelled = $invoice['status'] === 'cancelled';
$shopName = getSetting('shop_name', 'Sachin jewellers');
$shopPhone = getSetting('shop_phone', '+91 98765 43210');

// Clean customer phone number for WhatsApp
$rawCustPhone = $invoice['full_cust_phone'] ?: $invoice['customer_phone'];
$cleanCustPhoneDigits = preg_replace('/[^0-9]/', '', $rawCustPhone);
$defaultWaPhone = (strlen($cleanCustPhoneDigits) >= 10) ? substr($cleanCustPhoneDigits, -10) : '';

include __DIR__ . '/../../includes/header.php';
?>

<!-- html2pdf for direct PDF file generation & WhatsApp attachment -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- Hidden A4 Invoice Iframe for 100% accurate PDF generation -->
<iframe id="pdfPrintIframe" src="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4&plain=1'); ?>" style="position: absolute; left: -9999px; top: -9999px; width: 800px; height: 1100px; border: 0;" title="Invoice PDF Generator"></iframe>

<?php if (!empty($_GET['created']) || !empty($_GET['print'])): ?>
<!-- Bill Created Success Alert Banner with 1-Click WhatsApp & PDF -->
<div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 p-3 shadow-xs border-success rounded-3 no-print">
    <div class="d-flex align-items-center gap-3">
        <div class="p-2 bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
            <i class="bi bi-check-lg fs-3"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold text-success">Bill <?php echo sanitize($invoice['invoice_number']); ?> Generated Successfully!</h5>
            <div class="fs-xs text-muted">You can share the PDF bill directly to customer's WhatsApp, print receipt or download PDF.</div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-success fw-bold d-inline-flex align-items-center gap-2 shadow-sm px-3" onclick="openWhatsAppModal()">
            <i class="bi bi-whatsapp fs-5"></i> Send Bill on WhatsApp
        </button>
        <button type="button" class="btn btn-primary fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" onclick="downloadInvoicePdf()">
            <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF
        </button>
        <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4'); ?>" target="_blank" class="btn btn-outline-dark fw-medium">
            <i class="bi bi-printer"></i> Print Invoice
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Header Actions -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h4 class="mb-0 fw-bold text-dark">Invoice: <?php echo sanitize($invoice['invoice_number']); ?></h4>
            <?php if ($invoice['total_tax'] > 0): ?>
            <span class="badge bg-success-subtle text-success border border-success fs-xs"><i class="bi bi-shield-check me-1"></i>Tax Invoice</span>
            <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary border border-secondary fs-xs"><i class="bi bi-file-earmark-text me-1"></i>Estimate Memo</span>
            <?php endif; ?>
            <?php if ($isCancelled): ?>
            <span class="badge bg-danger fs-sm">Cancelled</span>
            <?php else: ?>
            <span class="status-badge <?php echo $invoice['payment_status']; ?> fs-sm">
                <?php echo ucfirst($invoice['payment_status']); ?>
            </span>
            <?php endif; ?>
        </div>
        <small class="text-muted">
            Generated on <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?> 
            &bull; Billed by <?php echo sanitize($invoice['creator_name'] ?: 'Staff'); ?>
        </small>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-success d-inline-flex align-items-center gap-1 shadow-xs fw-bold" onclick="openWhatsAppModal()">
            <i class="bi bi-whatsapp"></i> Send on WhatsApp
        </button>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-xs" onclick="downloadInvoicePdf()">
            <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF
        </button>
        <a href="<?php echo url('modules/bill-history/export.php?id=' . $invoice['id'] . '&format=csv'); ?>" class="btn btn-outline-success d-inline-flex align-items-center gap-1 shadow-xs">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Download Excel/CSV
        </a>
        <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4'); ?>" target="_blank" class="btn btn-outline-primary d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer"></i> Print A4 Invoice
        </a>
            <i class="bi bi-printer"></i> Print A4 Invoice
        </a>
        <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=thermal'); ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
            <i class="bi bi-receipt-cutoff"></i> Thermal Print
        </a>
        <?php if ($invoice['due_amount'] > 0 && !$isCancelled): ?>
        <a href="<?php echo url('modules/bill-history/add_payment.php?id=' . $invoice['id']); ?>" class="btn btn-warning text-dark">
            <i class="bi bi-cash-coin me-1"></i> Collect Due (₹<?php echo number_format($invoice['due_amount'], 2); ?>)
        </a>
        <?php endif; ?>
        <?php if (!$isCancelled && isAdmin()): ?>
        <a href="<?php echo url('modules/bill-history/cancel.php?id=' . $invoice['id']); ?>" class="btn btn-outline-danger" onclick="return confirm('Cancel this invoice? Stock will be reversed automatically.');">
            <i class="bi bi-x-circle me-1"></i> Cancel Bill
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isCancelled): ?>
<div class="alert alert-danger mb-4">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>This invoice was cancelled on <?php echo date('d M Y, h:i A', strtotime($invoice['cancelled_at'])); ?> by <?php echo sanitize($invoice['canceller_name'] ?: 'Admin'); ?>.</strong>
            <div>Reason: <em><?php echo sanitize($invoice['cancel_reason'] ?: 'No reason stated'); ?></em>. (All sold inventory units have been safely reversed back into stock).</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Customer & Rates Details Card -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Customer Billing Information</h6>
            </div>
            <div class="card-body">
                <h5 class="mb-1 text-dark fw-bold"><?php echo sanitize($invoice['customer_name'] ?: 'Walk-in Customer'); ?></h5>
                <?php if (!empty($invoice['customer_phone']) && !in_array(strtolower($invoice['customer_phone']), ['walk-in', 'walkin', 'n/a', 'null'])): ?>
                <div class="mb-2 text-muted fs-sm">
                    <i class="bi bi-telephone me-1"></i> <?php echo sanitize($invoice['customer_phone']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_address']) && !in_array(strtolower($invoice['customer_address']), ['counter sale', 'counter walk-in sale'])): ?>
                <div class="mb-2 text-muted fs-sm">
                    <i class="bi bi-geo-alt me-1"></i> <?php echo sanitize($invoice['customer_address']); ?>
                </div>
                <?php elseif (empty($invoice['customer_phone']) || in_array(strtolower($invoice['customer_phone']), ['walk-in', 'walkin', 'n/a', 'null'])): ?>
                <div class="mb-2 text-muted fs-xs">
                    <span class="badge bg-light text-muted border"><i class="bi bi-person-walking me-1"></i>Walk-in Sale (No personal customer details)</span>
                </div>
                <?php endif; ?>
                <?php if ($invoice['total_tax'] > 0 && !empty($invoice['customer_gstin'])): ?>
                <div class="mb-1">
                    <span class="badge bg-light text-dark border">GSTIN: <?php echo sanitize($invoice['customer_gstin']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-lightning-charge me-2 text-warning"></i>Metal Rates Applied at Billing</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <small class="text-muted d-block">Gold 24K</small>
                        <strong class="text-primary fs-6">₹<?php echo number_format($invoice['gold_rate_24k'], 2); ?></strong>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Gold 22K</small>
                        <strong class="text-primary fs-6">₹<?php echo number_format($invoice['gold_rate_22k'], 2); ?></strong>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Gold 18K</small>
                        <strong class="text-primary fs-6">₹<?php echo number_format($invoice['gold_rate_18k'], 2); ?></strong>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Silver 999</small>
                        <strong class="text-dark fs-6">₹<?php echo number_format($invoice['silver_rate'], 2); ?></strong>
                    </div>
                </div>
                <?php if ($invoice['notes']): ?>
                <div class="mt-3 pt-3 border-top fs-sm text-muted">
                    <strong>Bill Notes:</strong> <?php echo sanitize($invoice['notes']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Itemized Product Table -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold"><i class="bi bi-gem me-2 text-primary"></i>Invoiced Jewellery Items</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product & Code</th>
                        <th>Purity / Metal</th>
                        <th>Weights (Gross / Net)</th>
                        <th>Rate/g</th>
                        <th>Metal Value</th>
                        <th>Working Chg</th>
                        <th>Wastage</th>
                        <th>Stone Chg</th>
                        <th class="text-end">Item Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($items as $it): ?>
                    <tr>
                        <td><?php echo $idx++; ?></td>
                        <td>
                            <strong class="text-dark d-block"><?php echo sanitize($it['product_name']); ?></strong>
                            <span class="badge bg-light text-dark border"><?php echo sanitize($it['product_code']); ?></span>
                            <?php if ($invoice['total_tax'] > 0 && $it['hsn_code']): ?>
                            <small class="text-muted">HSN: <?php echo sanitize($it['hsn_code']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-primary-light text-primary"><?php echo ucfirst($it['metal_type']); ?></span>
                            <span class="badge bg-light text-dark border"><?php echo sanitize($it['purity']); ?></span>
                        </td>
                        <td>
                            <div><strong class="text-dark"><?php echo formatWeight($it['net_weight']); ?></strong> <small class="text-muted">(Net)</small></div>
                            <small class="text-muted"><?php echo formatWeight($it['gross_weight']); ?> Gross <?php if ($it['stone_weight'] > 0) echo '| ' . formatWeight($it['stone_weight']) . ' St'; ?></small>
                        </td>
                        <td>₹<?php echo number_format($it['rate_per_gram'], 2); ?></td>
                        <td><?php echo formatCurrency($it['metal_value']); ?></td>
                        <td>
                            <?php echo formatCurrency($it['making_charges']); ?>
                            <div class="fs-xs text-muted">(<?php echo $it['making_charges_type'] === 'per_gram' ? '₹' . $it['making_charges_rate'] . '/g' : ($it['making_charges_type'] === 'percentage' ? $it['making_charges_rate'] . '%' : 'Fix'); ?>)</div>
                        </td>
                        <td>
                            <?php echo formatCurrency($it['wastage_charges']); ?>
                            <?php if ($it['wastage_percentage'] > 0): ?>
                            <div class="fs-xs text-muted">(<?php echo $it['wastage_percentage']; ?>%)</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatCurrency($it['stone_charges']); ?></td>
                        <td class="text-end">
                            <strong class="text-dark"><?php echo formatCurrency($it['item_total']); ?></strong>
                            <?php if ($it['quantity'] > 1): ?>
                            <div class="fs-xs text-muted">(Qty: <?php echo $it['quantity']; ?>)</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Financial Summary & Payments -->
<div class="row g-4 mb-4">
    <!-- Payment Ledger for this Invoice -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2 text-primary"></i>Payment Transactions</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No payments recorded yet for this invoice.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td class="text-muted fs-sm"><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo strtoupper($pay['payment_method']); ?></span></td>
                                <td class="text-muted fs-sm"><?php echo sanitize($pay['reference_number'] ?: '-'); ?></td>
                                <td class="text-muted fs-sm"><?php echo sanitize($pay['user_name'] ?: 'Staff'); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo formatCurrency($pay['amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Final Calculations Breakdown -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Subtotal (Metal Value):</span>
                    <span><?php echo formatCurrency($invoice['subtotal']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Working Charges:</span>
                    <span><?php echo formatCurrency($invoice['total_making_charges']); ?></span>
                </div>
                <?php if ($invoice['total_wastage_charges'] > 0): ?>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Total Wastage Charges:</span>
                    <span><?php echo formatCurrency($invoice['total_wastage_charges']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($invoice['total_stone_charges'] > 0): ?>
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Total Stone Charges:</span>
                    <span><?php echo formatCurrency($invoice['total_stone_charges']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($invoice['discount_amount'] > 0): ?>
                <div class="d-flex justify-content-between py-1 text-danger">
                    <span>Discount (<?php echo $invoice['discount_type'] === 'percentage' ? $invoice['discount_value'] . '%' : 'Fixed'; ?>):</span>
                    <span>-<?php echo formatCurrency($invoice['discount_amount']); ?></span>
                </div>
                <?php endif; ?>
                
                <hr class="my-2">
                
                <?php if ($invoice['total_tax'] > 0): ?>
                <div class="d-flex justify-content-between py-1 fw-semibold">
                    <span>Taxable Value:</span>
                    <span><?php echo formatCurrency($invoice['taxable_amount']); ?></span>
                </div>
                
                    <?php if ($invoice['igst_amount'] > 0): ?>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>IGST (<?php echo $invoice['igst_rate']; ?>%):</span>
                        <span><?php echo formatCurrency($invoice['igst_amount']); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>CGST (<?php echo $invoice['cgst_rate']; ?>%):</span>
                        <span><?php echo formatCurrency($invoice['cgst_amount']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 text-muted">
                        <span>SGST (<?php echo $invoice['sgst_rate']; ?>%):</span>
                        <span><?php echo formatCurrency($invoice['sgst_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between py-1 text-muted">
                    <span>Round Off:</span>
                    <span><?php echo ($invoice['round_off'] >= 0 ? '+' : '') . formatCurrency($invoice['round_off']); ?></span>
                </div>
                
                <hr class="my-2">
                
                <div class="d-flex justify-content-between py-2 fs-5 fw-bold text-primary">
                    <span>Final Bill Total:</span>
                    <span><?php echo formatCurrency($invoice['final_amount']); ?></span>
                </div>
                
                <div class="p-2 bg-light rounded fs-xs text-muted mb-3">
                    <strong>Amount in Words:</strong> <?php echo sanitize($invoice['amount_in_words']); ?>
                </div>

                <div class="d-flex justify-content-between pt-2 border-top">
                    <div>
                        <small class="text-muted d-block">Amount Received</small>
                        <strong class="text-success fs-6"><?php echo formatCurrency($invoice['paid_amount']); ?></strong>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block">Balance Due</small>
                        <strong class="<?php echo $invoice['due_amount'] > 0 ? 'text-danger' : 'text-success'; ?> fs-6">
                            <?php echo formatCurrency($invoice['due_amount']); ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Bottom Action Bar for Instant WhatsApp & PDF Share -->
<div class="mobile-step-action-bar d-lg-none no-print shadow-lg bg-white p-2">
    <button type="button" class="btn btn-success fw-bold py-2 px-3 flex-grow-1 d-flex align-items-center justify-content-center gap-2 shadow-sm text-nowrap" onclick="openWhatsAppModal()">
        <i class="bi bi-whatsapp fs-5"></i> Send on WhatsApp
    </button>
    <button type="button" class="btn btn-primary fw-bold py-2 px-3 d-flex align-items-center justify-content-center gap-1 shadow-sm text-nowrap" onclick="downloadInvoicePdf()">
        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
    </button>
    <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4'); ?>" target="_blank" class="btn btn-outline-secondary py-2 px-3 d-flex align-items-center justify-content-center text-nowrap" title="Print">
        <i class="bi bi-printer"></i>
    </a>
</div>

<!-- ========================================================================= -->
<!-- Modal: Professional WhatsApp PDF Share                                    -->
<!-- ========================================================================= -->
<div class="modal fade" id="whatsappShareModal" tabindex="-1" aria-labelledby="whatsappShareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 38px; height: 38px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-whatsapp fs-4 text-white"></i>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold text-white" id="whatsappShareModalLabel">Send Bill on WhatsApp</h6>
                        <small class="text-white-50 fs-xs">Share official PDF invoice & bill summary</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                
                <!-- Phone Number Field -->
                <div class="mb-3">
                    <label class="form-label fs-xs fw-bold text-muted mb-1 text-uppercase">Customer WhatsApp Number</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted fw-bold">+91</span>
                        <input type="tel" class="form-control fw-bold" id="waRecipientPhone" value="<?php echo sanitize($defaultWaPhone); ?>" placeholder="10-digit mobile number" maxlength="10">
                    </div>
                    <div class="form-text fs-xs">Bill will be sent to this WhatsApp contact.</div>
                </div>

                <!-- PDF File Status -->
                <div class="p-2 mb-3 bg-light rounded border d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 min-width-0">
                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-3"></i>
                        <div class="min-width-0">
                            <strong class="d-block text-dark fs-xs text-truncate"><?php echo sanitize($invoice['invoice_number']); ?>_bill.pdf</strong>
                            <small class="text-muted fs-xs">Official A4 GST Tax Invoice</small>
                        </div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-xs">Ready</span>
                </div>

                <!-- Message Preview -->
                <div class="mb-3">
                    <label class="form-label fs-xs fw-bold text-muted mb-1 text-uppercase d-flex justify-content-between align-items-center">
                        <span>WhatsApp Message Preview</span>
                        <span class="text-muted fw-normal" style="font-size: 10px;">Includes PDF Link</span>
                    </label>
                    <textarea class="form-control font-monospace fs-xs bg-light" id="waMessageText" rows="6" style="resize: vertical;"></textarea>
                </div>

                <!-- Primary Action Buttons -->
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success btn-lg fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" id="btnShareWaNative" onclick="sendInvoiceWhatsApp('share')">
                        <i class="bi bi-share-fill"></i> Share PDF on WhatsApp
                    </button>
                    <button type="button" class="btn btn-outline-success fw-semibold d-flex align-items-center justify-content-center gap-2" onclick="sendInvoiceWhatsApp('chat')">
                        <i class="bi bi-whatsapp"></i> Open WhatsApp Web / App
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const SHOP_NAME = "<?php echo addslashes($shopName); ?>";
const SHOP_PHONE = "<?php echo addslashes($shopPhone); ?>";
const INVOICE_NO = "<?php echo addslashes($invoice['invoice_number']); ?>";
const INVOICE_DATE = "<?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>";
const CUST_NAME = "<?php echo addslashes($invoice['customer_name'] ?: 'Customer'); ?>";
const FINAL_AMOUNT = "₹<?php echo number_format($invoice['final_amount'], 2); ?>";
const PAID_AMOUNT = "₹<?php echo number_format($invoice['paid_amount'], 2); ?>";
const DUE_AMOUNT = "₹<?php echo number_format($invoice['due_amount'], 2); ?>";
const PDF_URL = "<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4&download=pdf'); ?>";

function getInvoiceWhatsAppText() {
    return `*${SHOP_NAME}*
🧾 *Invoice No:* ${INVOICE_NO}
📅 *Date:* ${INVOICE_DATE}
👤 *Customer:* ${CUST_NAME}

💰 *Bill Total:* ${FINAL_AMOUNT}
💵 *Amount Paid:* ${PAID_AMOUNT}
${parseFloat('<?php echo (float)$invoice['due_amount']; ?>') > 0 ? `⚠️ *Balance Due:* ${DUE_AMOUNT}\n` : '✅ *Payment Status:* Fully Paid\n'}
📄 *Download Official PDF Bill:*
${PDF_URL}

Thank you for shopping with us!
📞 Contact: ${SHOP_PHONE}`;
}

// Initialise Message Text
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('waMessageText');
    if (textarea) {
        textarea.value = getInvoiceWhatsAppText();
    }
});

function openWhatsAppModal() {
    const modalEl = document.getElementById('whatsappShareModal');
    if (modalEl) {
        const textarea = document.getElementById('waMessageText');
        if (textarea) textarea.value = getInvoiceWhatsAppText();
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

async function generateInvoicePdfBlob() {
    const iframe = document.getElementById('pdfPrintIframe');
    let element = null;
    if (iframe && iframe.contentWindow && iframe.contentWindow.document) {
        element = iframe.contentWindow.document.querySelector('.invoice-container');
    }
    if (!element) {
        element = document.querySelector('.invoice-container') || document.body;
    }

    const opt = {
        margin: [6, 6, 6, 6],
        filename: `${INVOICE_NO}_bill.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    return await html2pdf().set(opt).from(element).output('blob');
}

function downloadInvoicePdf() {
    const iframe = document.getElementById('pdfPrintIframe');
    if (iframe && iframe.contentWindow && iframe.contentWindow.document) {
        const element = iframe.contentWindow.document.querySelector('.invoice-container');
        if (element && typeof html2pdf !== 'undefined') {
            const opt = {
                margin: [6, 6, 6, 6],
                filename: `${INVOICE_NO}_bill.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
            return;
        }
    }
    window.open(PDF_URL, '_blank');
}

async function sendInvoiceWhatsApp(mode) {
    const phoneInput = document.getElementById('waRecipientPhone');
    let phone = phoneInput ? phoneInput.value.replace(/[^0-9]/g, '') : '';
    if (!phone || phone.length < 10) {
        alert('Please enter a valid 10-digit mobile number.');
        if (phoneInput) phoneInput.focus();
        return;
    }
    if (phone.length === 10) {
        phone = '91' + phone;
    }

    const textarea = document.getElementById('waMessageText');
    const message = textarea ? textarea.value : getInvoiceWhatsAppText();
    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

    if (mode === 'chat') {
        window.open(waUrl, '_blank');
        return;
    }

    const btn = document.getElementById('btnShareWaNative');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing PDF file...';
    }

    // Try Web Share API (native WhatsApp file attachment on Mobile)
    try {
        const pdfBlob = await generateInvoicePdfBlob();
        const file = new File([pdfBlob], `${INVOICE_NO}_bill.pdf`, { type: 'application/pdf' });
        
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: `Invoice ${INVOICE_NO}`,
                text: message
            });
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
            return;
        }
    } catch(err) {
        console.warn('Native share error or dismissed:', err);
    }

    // Desktop / Fallback flow: Download PDF automatically and launch WhatsApp Web with chat & message!
    downloadInvoicePdf();
    window.open(waUrl, '_blank');

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

