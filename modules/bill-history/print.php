<?php
/**
 * Professional Jewellery Invoice Printer
 * Supports A4 full page and 80mm thermal receipt formats
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$invoiceId = (int) ($_GET['id'] ?? 0);
$format = trim($_GET['format'] ?? 'a4'); // 'a4' or 'thermal'

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT i.*, u.username as creator_name, c.name as full_cust_name, c.phone as full_cust_phone, c.email as cust_email, c.address as full_cust_address 
                       FROM invoices i 
                       LEFT JOIN users u ON i.created_by = u.id 
                       LEFT JOIN customers c ON i.customer_id = c.id 
                       WHERE i.id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

// Fetch items
$itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
$itemsStmt->execute([$invoiceId]);
$items = $itemsStmt->fetchAll();

// Fetch payments
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date ASC");
$payStmt->execute([$invoiceId]);
$payments = $payStmt->fetchAll();

// Shop settings
$shopName = getSetting('shop_name', 'Royal Jewellers');
$shopAddress = getSetting('shop_address', '123 Swarna Bazaar, Zaveri Market, Mumbai - 400002');
$shopPhone = getSetting('shop_phone', '+91 98765 43210');
$shopEmail = getSetting('shop_email', 'billing@royaljewellers.com');
$shopGstin = getSetting('shop_gstin', '27AABCR9912D1Z4');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo sanitize($invoice['invoice_number']); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo url('assets/css/invoice.css'); ?>" rel="stylesheet">
    <!-- html2pdf for direct PDF file generation & download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Toolbar */
        .print-toolbar {
            background: #213C72;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        @media print {
            .print-toolbar {
                display: none !important;
            }

            body {
                background: #fff !important;
            }
        }
    </style>
</head>

<body style="background: #F4F6F9; min-height: 100vh;">

    <?php if (empty($_GET['plain'])): ?>
    <!-- Print Control Toolbar -->
    <div class="print-toolbar no-print">
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold"><i class="bi bi-gem text-warning me-1"></i>
                <?php echo sanitize($invoice['invoice_number']); ?></span>
            <div class="btn-group btn-group-sm">
                <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4'); ?>"
                    class="btn <?php echo $format === 'a4' ? 'btn-light fw-bold text-primary' : 'btn-outline-light'; ?>">
                    <i class="bi bi-file-earmark-text"></i> A4 Standard
                </a>
                <a href="<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=thermal'); ?>"
                    class="btn <?php echo $format === 'thermal' ? 'btn-light fw-bold text-primary' : 'btn-outline-light'; ?>">
                    <i class="bi bi-receipt"></i> Thermal 80mm
                </a>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm fw-bold d-flex align-items-center gap-1 shadow-xs" onclick="shareInvoiceWhatsApp()">
                <i class="bi bi-whatsapp"></i> Send on WhatsApp
            </button>
            <button type="button" class="btn btn-light btn-sm fw-bold text-dark d-flex align-items-center gap-1 shadow-xs" onclick="downloadInvoicePdf()">
                <i class="bi bi-file-earmark-pdf-fill text-danger"></i> Download PDF
            </button>
            <a href="<?php echo url('modules/bill-history/export.php?id=' . $invoice['id'] . '&format=csv'); ?>"
                class="btn btn-outline-light btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-file-earmark-spreadsheet text-success"></i> Download Excel/CSV
            </a>
            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold" onclick="window.print()">
                <i class="bi bi-printer-fill me-1"></i> Print Bill Now
            </button>
            <a href="<?php echo url('modules/bill-history/view.php?id=' . $invoice['id']); ?>"
                class="btn btn-outline-light btn-sm">
                <i class="bi bi-x-lg"></i> Close
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="py-4">
        <?php if ($format === 'thermal'): ?>
            <!-- ================= THERMAL RECEIPT 80MM ================= -->
            <div class="invoice-container invoice-thermal shadow-sm rounded">
                <!-- Header -->
                <div class="invoice-header">
                    <h2 class="fw-bold mb-1"><?php echo sanitize($shopName); ?></h2>
                    <div><?php echo sanitize($shopAddress); ?></div>
                    <div>Ph: <?php echo sanitize($shopPhone); ?></div>
                    <?php if ($invoice['total_tax'] > 0 && $shopGstin): ?>
                        <div>GSTIN: <?php echo sanitize($shopGstin); ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['total_tax'] > 0): ?>
                        <div class="mt-2 fw-bold fs-6">TAX INVOICE</div>
                    <?php else: ?>
                        <div class="mt-2 fw-bold fs-6">RETAIL ESTIMATE</div>
                    <?php endif; ?>
                </div>

                <!-- Meta -->
                <div class="py-2 border-bottom border-dashed" style="font-size: 11px;">
                    <div class="d-flex justify-content-between">
                        <span>Invoice #: <strong><?php echo sanitize($invoice['invoice_number']); ?></strong></span>
                        <span>Date: <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></span>
                    </div>
                    <div class="mt-1">Customer:
                        <strong><?php echo sanitize($invoice['customer_name'] ?: 'Walk-in Customer'); ?></strong>
                    </div>
                    <?php if (!empty($invoice['customer_phone']) && !in_array(strtolower($invoice['customer_phone']), ['walk-in', 'walkin', 'n/a', 'null'])): ?>
                        <div>Phone: <?php echo sanitize($invoice['customer_phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['total_tax'] > 0 && !empty($invoice['customer_gstin'])): ?>
                        <div>GSTIN: <?php echo sanitize($invoice['customer_gstin']); ?></div>
                    <?php endif; ?>
                </div>

                <!-- Rates -->
                <div class="py-1 border-bottom border-dashed text-center fs-xs text-muted">
                    Rate: 22K: ₹<?php echo number_format($invoice['gold_rate_22k'], 2); ?>/g | 24K:
                    ₹<?php echo number_format($invoice['gold_rate_24k'], 2); ?>/g
                </div>

                <!-- Items -->
                <table class="invoice-table my-2">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Wt(g)</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($it['product_name']); ?></strong>
                                    <div class="fs-xs"><?php echo sanitize($it['purity']); ?> | Rate:
                                        ₹<?php echo number_format($it['rate_per_gram'], 2); ?></div>
                                </td>
                                <td class="text-center">
                                    <?php echo formatWeight($it['net_weight']); ?>
                                </td>
                                <td class="text-right">
                                    ₹<?php echo number_format($it['item_total'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Summary -->
                <div class="border-top border-dashed pt-2" style="font-size: 11px;">
                    <?php if ($invoice['total_tax'] > 0): ?>
                        <div class="d-flex justify-content-between">
                            <span>Taxable Amount:</span>
                            <span>₹<?php echo number_format($invoice['taxable_amount'], 2); ?></span>
                        </div>
                        <?php if ($invoice['igst_amount'] > 0): ?>
                            <div class="d-flex justify-content-between">
                                <span>IGST (<?php echo $invoice['igst_rate']; ?>%):</span>
                                <span>₹<?php echo number_format($invoice['igst_amount'], 2); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="d-flex justify-content-between">
                                <span>CGST + SGST (<?php echo ($invoice['cgst_rate'] + $invoice['sgst_rate']); ?>%):</span>
                                <span>₹<?php echo number_format($invoice['total_tax'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="d-flex justify-content-between text-danger">
                            <span>Discount:</span>
                            <span>-₹<?php echo number_format($invoice['discount_amount'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between fw-bold fs-6 border-top border-dark pt-1 mt-1">
                        <span>GRAND TOTAL:</span>
                        <span>₹<?php echo number_format($invoice['final_amount'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-success mt-1">
                        <span>Paid Amount:</span>
                        <span>₹<?php echo number_format($invoice['paid_amount'], 2); ?></span>
                    </div>
                    <?php if ($invoice['due_amount'] > 0): ?>
                        <div class="d-flex justify-content-between text-danger fw-bold">
                            <span>Due Balance:</span>
                            <span>₹<?php echo number_format($invoice['due_amount'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Words -->
                <div class="mt-2 py-1 border-top border-dashed fs-xs text-center">
                    <?php echo sanitize($invoice['amount_in_words']); ?>
                </div>

                <!-- Footer -->
                <div class="invoice-footer mt-2 pt-2 text-center" style="font-size: 10px;">
                    <p class="mb-1">Certified BIS Hallmarked Jewellery.</p>
                    <p class="mb-1">Thank you for shopping with us!</p>
                    <div class="mt-3">*** E. & O.E. ***</div>
                </div>
            </div>

        <?php else: ?>
            <!-- ================= A4 FULL PAGE INVOICE ================= -->
            <div class="invoice-container invoice-a4 shadow rounded" id="invoicePrintArea">

                <!-- Invoice Header -->
                <div class="invoice-header">
                    <div class="invoice-brand">
                        <div
                            style="width: 54px; height: 54px; background: #213C72; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #F4B740; font-size: 28px;">
                            <i class="bi bi-gem"></i>
                        </div>
                        <div>
                            <h2><?php echo sanitize($shopName); ?></h2>
                            <p><?php echo sanitize($shopAddress); ?></p>
                            <p>Phone: <?php echo sanitize($shopPhone); ?> | Email: <?php echo sanitize($shopEmail); ?></p>
                            <?php if ($invoice['total_tax'] > 0 && $shopGstin): ?>
                                <p><strong>GSTIN: <?php echo sanitize($shopGstin); ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="invoice-title">
                        <?php if ($invoice['total_tax'] > 0): ?>
                            <h3>TAX INVOICE</h3>
                        <?php else: ?>
                            <h3>RETAIL ESTIMATE</h3>
                        <?php endif; ?>
                        <div class="invoice-number">Invoice #:
                            <strong><?php echo sanitize($invoice['invoice_number']); ?></strong>
                        </div>
                        <div class="invoice-date">Date:
                            <strong><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Customer & Rate Information -->
                <div class="invoice-info">
                    <div class="invoice-info-box">
                        <h6>BILLED TO:</h6>
                        <div class="name"><?php echo sanitize($invoice['customer_name'] ?: 'Walk-in Customer'); ?></div>
                        <?php if (!empty($invoice['customer_phone']) && !in_array(strtolower($invoice['customer_phone']), ['walk-in', 'walkin', 'n/a', 'null'])): ?>
                            <p><i class="bi bi-telephone text-muted me-1"></i><?php echo sanitize($invoice['customer_phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['customer_address']) && !in_array(strtolower($invoice['customer_address']), ['counter sale', 'counter walk-in sale'])): ?>
                            <p><?php echo nl2br(sanitize($invoice['customer_address'])); ?></p>
                        <?php endif; ?>
                        <?php if ($invoice['total_tax'] > 0 && !empty($invoice['customer_gstin'])): ?>
                            <p><strong>GSTIN: <?php echo sanitize($invoice['customer_gstin']); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-info-box text-end">
                        <h6>INVOICE DETAILS:</h6>
                        <?php if ($invoice['total_tax'] > 0): ?>
                        <p>Place of Supply: <strong>Intra-State / State Code: 27</strong></p>
                        <?php endif; ?>
                        <p>Payment Mode:
                            <strong><?php echo strtoupper($payments[0]['payment_method'] ?? 'CASH'); ?></strong>
                        </p>
                        <p>Status: <span
                                class="status-badge <?php echo $invoice['payment_status']; ?>"><?php echo ucfirst($invoice['payment_status']); ?></span>
                        </p>
                    </div>
                </div>

                <!-- Metal Rates Bar -->
                <div class="invoice-rate-info">
                    <div class="rate-item">
                        <span class="rate-label">Gold 24K:</span>
                        <span class="rate-value">₹<?php echo number_format($invoice['gold_rate_24k'], 2); ?>/g</span>
                    </div>
                    <div class="rate-item">
                        <span class="rate-label">Gold 22K:</span>
                        <span class="rate-value">₹<?php echo number_format($invoice['gold_rate_22k'], 2); ?>/g</span>
                    </div>
                    <div class="rate-item">
                        <span class="rate-label">Gold 18K:</span>
                        <span class="rate-value">₹<?php echo number_format($invoice['gold_rate_18k'], 2); ?>/g</span>
                    </div>
                    <div class="rate-item">
                        <span class="rate-label">Silver 999:</span>
                        <span class="rate-value">₹<?php echo number_format($invoice['silver_rate'], 2); ?>/g</span>
                    </div>
                </div>

                <!-- Products Table -->
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="<?php echo $invoice['total_tax'] > 0 ? 'width: 25%;' : 'width: 33%;'; ?>">Item Description</th>
                            <?php if ($invoice['total_tax'] > 0): ?>
                            <th style="width: 8%;">HSN</th>
                            <?php endif; ?>
                            <th style="width: 10%;">Purity</th>
                            <th style="width: 10%;" class="text-center">Gross (g)</th>
                            <th style="width: 10%;" class="text-center">Net (g)</th>
                            <th style="width: 10%;" class="text-right">Rate/g</th>
                            <th style="width: 10%;" class="text-right">Working</th>
                            <th style="width: 12%;" class="text-right">Total (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1;
                        foreach ($items as $it): ?>
                            <tr>
                                <td><?php echo $idx++; ?></td>
                                <td>
                                    <div class="product-name"><?php echo sanitize($it['product_name']); ?></div>
                                    <div class="product-details">Code: <?php echo sanitize($it['product_code']); ?>
                                        <?php if ($it['stone_charges'] > 0)
                                            echo '| Stone: ₹' . number_format($it['stone_charges'], 2); ?>
                                    </div>
                                </td>
                                <?php if ($invoice['total_tax'] > 0): ?>
                                <td><?php echo sanitize($it['hsn_code']); ?></td>
                                <?php endif; ?>
                                <td><?php echo sanitize($it['purity']); ?></td>
                                <td class="text-center"><?php echo formatWeight($it['gross_weight']); ?></td>
                                <td class="text-center fw-bold"><?php echo formatWeight($it['net_weight']); ?></td>
                                <td class="text-right">₹<?php echo number_format($it['rate_per_gram'], 2); ?></td>
                                <td class="text-right">₹<?php echo number_format($it['making_charges'], 2); ?></td>
                                <td class="text-right fw-bold">₹<?php echo number_format($it['item_total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Summary & Calculations -->
                <div class="invoice-summary">
                    <div class="invoice-summary-table">
                        <div class="summary-row">
                            <span class="label">Total Metal Value:</span>
                            <span class="value">₹<?php echo number_format($invoice['subtotal'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="label">Working Charges:</span>
                            <span class="value">₹<?php echo number_format($invoice['total_making_charges'], 2); ?></span>
                        </div>
                        <?php if ($invoice['total_wastage_charges'] > 0): ?>
                            <div class="summary-row">
                                <span class="label">Wastage Charges:</span>
                                <span class="value">₹<?php echo number_format($invoice['total_wastage_charges'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($invoice['total_stone_charges'] > 0): ?>
                            <div class="summary-row">
                                <span class="label">Stone Charges:</span>
                                <span class="value">₹<?php echo number_format($invoice['total_stone_charges'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                            <div class="summary-row text-danger">
                                <span class="label text-danger">Discount:</span>
                                <span class="value">-₹<?php echo number_format($invoice['discount_amount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($invoice['total_tax'] > 0): ?>
                            <div class="summary-row subtotal">
                                <span class="label fw-bold">Taxable Amount:</span>
                                <span class="value fw-bold">₹<?php echo number_format($invoice['taxable_amount'], 2); ?></span>
                            </div>
                            <?php if ($invoice['igst_amount'] > 0): ?>
                                <div class="summary-row">
                                    <span class="label">IGST (<?php echo $invoice['igst_rate']; ?>%):</span>
                                    <span class="value">₹<?php echo number_format($invoice['igst_amount'], 2); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="summary-row">
                                    <span class="label">CGST (<?php echo $invoice['cgst_rate']; ?>%):</span>
                                    <span class="value">₹<?php echo number_format($invoice['cgst_amount'], 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">SGST (<?php echo $invoice['sgst_rate']; ?>%):</span>
                                    <span class="value">₹<?php echo number_format($invoice['sgst_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="summary-row">
                            <span class="label">Round Off:</span>
                            <span
                                class="value"><?php echo ($invoice['round_off'] >= 0 ? '+' : '') . number_format($invoice['round_off'], 2); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span class="label text-primary">Final Amount:</span>
                            <span
                                class="value text-primary">₹<?php echo number_format($invoice['final_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Amount in Words -->
                <div class="invoice-words">
                    <div class="label">Amount Chargeable (in words):</div>
                    <div class="words"><?php echo sanitize($invoice['amount_in_words']); ?></div>
                </div>

                <!-- Payment Summary -->
                <div class="invoice-payment d-flex justify-content-between align-items-center bg-light p-3 rounded">
                    <div>
                        <strong>Payment Received:</strong> ₹<?php echo number_format($invoice['paid_amount'], 2); ?>
                        <span
                            class="badge bg-success ms-2"><?php echo strtoupper($payments[0]['payment_method'] ?? 'CASH'); ?></span>
                    </div>
                    <div>
                        <strong>Balance Due:</strong>
                        <span class="<?php echo $invoice['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                            ₹<?php echo number_format($invoice['due_amount'], 2); ?>
                        </span>
                    </div>
                </div>

                <!-- Terms & Signatures -->
                <div class="invoice-footer">
                    <div class="terms">
                        <h6>Terms & Conditions:</h6>
                        <ol class="ps-3 mb-0" style="font-size: 10px; line-height: 1.5;">
                            <li>All gold jewellery sold is hallmarked with BIS Hallmark and verified purity standards.</li>
                            <li>Goods once sold will be accepted for return/exchange within 7 days against invoice only.
                            </li>
                            <li>Old gold valuation/buyback is subject to purity testing and current bullion market
                                deductions.</li>
                            <li>Subject to local jurisdiction.</li>
                        </ol>
                    </div>
                    <div class="signature">
                        <div class="line"></div>
                        <p class="mb-0 fw-semibold text-dark">Authorized Signatory</p>
                        <small class="text-muted">For <?php echo sanitize($shopName); ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function downloadInvoicePdf() {
        const isThermal = '<?php echo $format; ?>' === 'thermal';
        const element = document.querySelector('.invoice-container');
        if (!element) return;
        
        const opt = {
            margin:       isThermal ? [4, 4, 4, 4] : [6, 6, 6, 6],
            filename:     '<?php echo sanitize($invoice['invoice_number']); ?>_' + (isThermal ? 'receipt' : 'bill') + '.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: isThermal ? [80, 240] : 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    async function shareInvoiceWhatsApp() {
        const customerPhone = '<?php echo preg_replace('/[^0-9]/', '', $invoice['full_cust_phone'] ?: $invoice['customer_phone']); ?>';
        let targetPhone = customerPhone;
        if (!targetPhone || targetPhone.length < 10) {
            const entered = prompt("Enter customer 10-digit WhatsApp phone number:", "");
            if (!entered) return;
            targetPhone = entered.replace(/[^0-9]/g, '');
        }
        if (targetPhone.length === 10) {
            targetPhone = '91' + targetPhone;
        }

        const shopName = "<?php echo addslashes($shopName); ?>";
        const invoiceNo = "<?php echo addslashes($invoice['invoice_number']); ?>";
        const invoiceDate = "<?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>";
        const custName = "<?php echo addslashes($invoice['customer_name'] ?: 'Customer'); ?>";
        const total = "₹<?php echo number_format($invoice['final_amount'], 2); ?>";
        const paid = "₹<?php echo number_format($invoice['paid_amount'], 2); ?>";
        const due = "₹<?php echo number_format($invoice['due_amount'], 2); ?>";
        const pdfLink = "<?php echo url('modules/bill-history/print.php?id=' . $invoice['id'] . '&format=a4&download=pdf'); ?>";

        const textMessage = `*${shopName}*
🧾 *Invoice:* ${invoiceNo}
📅 *Date:* ${invoiceDate}
👤 *Customer:* ${custName}

💰 *Total Amount:* ${total}
💵 *Amount Paid:* ${paid}
${parseFloat('<?php echo (float)$invoice['due_amount']; ?>') > 0 ? `⚠️ *Balance Due:* ${due}\n` : '✅ *Payment Status:* Fully Paid\n'}
📄 *Download Official PDF Bill:*
${pdfLink}

Thank you for shopping with us!
📞 <?php echo addslashes($shopPhone); ?>`;

        const element = document.querySelector('.invoice-container');
        const isThermal = '<?php echo $format; ?>' === 'thermal';
        const opt = {
            margin:       isThermal ? [4, 4, 4, 4] : [6, 6, 6, 6],
            filename:     '<?php echo sanitize($invoice['invoice_number']); ?>_bill.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: isThermal ? [80, 240] : 'a4', orientation: 'portrait' }
        };

        // Try Web Share API with PDF File on Mobile
        try {
            if (element && typeof html2pdf !== 'undefined') {
                const pdfBlob = await html2pdf().set(opt).from(element).output('blob');
                const file = new File([pdfBlob], '<?php echo sanitize($invoice['invoice_number']); ?>_bill.pdf', { type: 'application/pdf' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: 'Invoice ' + invoiceNo,
                        text: textMessage
                    });
                    return;
                }
            }
        } catch(e) {
            console.warn('Native file share skipped:', e);
        }

        // Desktop / Fallback: Download PDF & open WhatsApp Web
        if (element && typeof html2pdf !== 'undefined') {
            html2pdf().set(opt).from(element).save();
        }
        const waUrl = `https://wa.me/${targetPhone}?text=${encodeURIComponent(textMessage)}`;
        window.open(waUrl, '_blank');
    }

    <?php if (!empty($_GET['download']) && $_GET['download'] === 'pdf'): ?>
    window.addEventListener('load', function() {
        setTimeout(downloadInvoicePdf, 400);
    });
    <?php endif; ?>
    </script>
</body>

</html>