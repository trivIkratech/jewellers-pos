<?php
/**
 * Export Invoices / Bills (CSV, Excel, PDF Report)
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pdo = getDBConnection();

$format = strtolower(trim($_GET['format'] ?? 'csv')); // 'csv', 'xls', 'pdf'
$type = strtolower(trim($_GET['type'] ?? 'all'));     // 'all', 'gst', 'non_gst'
$invoiceId = !empty($_GET['id']) ? (int) $_GET['id'] : null;

// Filters
$search = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$shopName = getSetting('shop_name', 'Sachin jewellers');
$shopPhone = getSetting('shop_phone', '');
$shopGstin = getSetting('shop_gstin', '');

function writeCsvRow($handle, array $fields) {
    fputcsv($handle, $fields, ',', '"', "\\");
}

function sanitizeFilename($string) {
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $string);
}

// =========================================================================
// 1. SINGLE BILL EXPORT (Excel / CSV)
// =========================================================================
if ($invoiceId) {
    $stmt = $pdo->prepare("SELECT i.*, u.username as creator_name, c.name as full_cust_name, c.phone as full_cust_phone 
                           FROM invoices i 
                           LEFT JOIN users u ON i.created_by = u.id 
                           LEFT JOIN customers c ON i.customer_id = c.id 
                           WHERE i.id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die("Invoice not found.");
    }

    $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $itemsStmt->execute([$invoiceId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $isGst = ($invoice['total_tax'] > 0 || $invoice['invoice_type'] === 'tax_invoice');
    $billTypeLabel = $isGst ? 'GST Bill (Tax Invoice)' : 'Non-GST Bill (Retail Estimate)';
    $filename = sanitizeFilename($invoice['invoice_number']) . '_' . ($isGst ? 'GST' : 'NonGST') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Microsoft Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header Info
    writeCsvRow($output, [$shopName]);
    writeCsvRow($output, ['GSTIN:', $isGst ? $shopGstin : 'N/A', 'Phone:', $shopPhone]);
    writeCsvRow($output, []);
    writeCsvRow($output, ['INVOICE DETAILS']);
    writeCsvRow($output, ['Invoice / Bill #:', $invoice['invoice_number'], 'Date:', date('d-m-Y', strtotime($invoice['invoice_date']))]);
    writeCsvRow($output, ['Bill Type:', $billTypeLabel, 'Status:', ucfirst($invoice['status'])]);
    writeCsvRow($output, ['Customer Name:', $invoice['customer_name'] ?: 'Walk-in Customer']);
    writeCsvRow($output, ['Customer Phone:', $invoice['customer_phone'] ?: 'N/A']);
    writeCsvRow($output, ['Customer Address:', $invoice['customer_address'] ?: 'N/A']);
    if ($isGst) {
        writeCsvRow($output, ['Customer GSTIN:', $invoice['customer_gstin'] ?: 'N/A']);
    }
    writeCsvRow($output, []);

    // Line Items Header
    writeCsvRow($output, [
        '#',
        'Product Code',
        'Description',
        'Metal',
        'Purity',
        'HSN',
        'Qty',
        'Gross Wt (g)',
        'Stone Wt (g)',
        'Net Wt (g)',
        'Rate/g (Rs)',
        'Metal Value (Rs)',
        'Making Type',
        'Making Charges (Rs)',
        'Line Total (Rs)'
    ]);

    $idx = 1;
    foreach ($items as $item) {
        writeCsvRow($output, [
            $idx++,
            $item['product_code'],
            $item['product_name'],
            ucfirst($item['metal_type']),
            $item['purity'],
            $isGst ? $item['hsn_code'] : 'N/A',
            $item['quantity'],
            number_format($item['gross_weight'], 3, '.', ''),
            number_format($item['stone_weight'], 3, '.', ''),
            number_format($item['net_weight'], 3, '.', ''),
            number_format($item['rate_per_gram'], 2, '.', ''),
            number_format($item['metal_value'], 2, '.', ''),
            $item['making_charges_type'],
            number_format($item['making_charges'], 2, '.', ''),
            number_format($item['item_total'], 2, '.', '')
        ]);
    }

    writeCsvRow($output, []);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Subtotal (Metal Value):', number_format($invoice['subtotal'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Total Making Charges:', number_format($invoice['total_making_charges'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Discount:', number_format($invoice['discount_amount'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Taxable Amount:', number_format($invoice['taxable_amount'], 2, '.', '')]);
    if ($isGst) {
        writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'CGST (' . $invoice['cgst_rate'] . '%):', number_format($invoice['cgst_amount'], 2, '.', '')]);
        writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'SGST (' . $invoice['sgst_rate'] . '%):', number_format($invoice['sgst_amount'], 2, '.', '')]);
        if ($invoice['igst_amount'] > 0) {
            writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'IGST (' . $invoice['igst_rate'] . '%):', number_format($invoice['igst_amount'], 2, '.', '')]);
        }
        writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Total GST Tax:', number_format($invoice['total_tax'], 2, '.', '')]);
    }
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Round Off:', number_format($invoice['round_off'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'FINAL AMOUNT (Rs):', number_format($invoice['final_amount'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Paid Amount (Rs):', number_format($invoice['paid_amount'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Balance Due (Rs):', number_format($invoice['due_amount'], 2, '.', '')]);
    writeCsvRow($output, ['', '', '', '', '', '', '', '', '', '', '', 'Payment Status:', ucfirst($invoice['payment_status'])]);

    fclose($output);
    exit;
}

// =========================================================================
// 2. BATCH INVOICES EXPORT (GST Bills / Non-GST Bills / All Bills)
// =========================================================================
$sql = "SELECT i.*, COUNT(ii.id) as items_count 
        FROM invoices i 
        LEFT JOIN invoice_items ii ON i.id = ii.invoice_id 
        WHERE 1=1";
$params = [];

if ($type === 'gst') {
    $sql .= " AND (i.invoice_type = 'tax_invoice' OR i.total_tax > 0)";
    $reportTitle = 'GST Invoices Register (Tax Bills)';
    $baseFilename = 'GST_Bills_Report_' . date('Y-m-d');
} elseif ($type === 'non_gst') {
    $sql .= " AND (i.invoice_type = 'estimate' OR (i.total_tax = 0 AND (i.invoice_type IS NULL OR i.invoice_type != 'tax_invoice')))";
    $reportTitle = 'Non-GST Bills Register (Retail Estimates)';
    $baseFilename = 'Non_GST_Bills_Report_' . date('Y-m-d');
} else {
    $reportTitle = 'All Invoices & Bills Register';
    $baseFilename = 'All_Bills_Report_' . date('Y-m-d');
}

if ($search !== '') {
    $sql .= " AND (i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($startDate !== '') {
    $sql .= " AND i.invoice_date >= ?";
    $params[] = $startDate;
}

if ($endDate !== '') {
    $sql .= " AND i.invoice_date <= ?";
    $params[] = $endDate;
}

if ($paymentStatus !== '') {
    $sql .= " AND i.payment_status = ?";
    $params[] = $paymentStatus;
}

if ($statusFilter !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY i.id ORDER BY i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary totals
$totalGross = 0;
$totalTax = 0;
$totalPaid = 0;
$totalDue = 0;
foreach ($invoices as $inv) {
    if ($inv['status'] !== 'cancelled') {
        $totalGross += (float) $inv['final_amount'];
        $totalTax += (float) $inv['total_tax'];
        $totalPaid += (float) $inv['paid_amount'];
        $totalDue += (float) $inv['due_amount'];
    }
}

// -------------------------------------------------------------------------
// CSV / XLS EXPORT
// -------------------------------------------------------------------------
if ($format === 'csv' || $format === 'xls') {
    $filename = $baseFilename . ($format === 'xls' ? '.xls' : '.csv');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Summary metadata
    writeCsvRow($output, [$shopName . ' - ' . $reportTitle]);
    writeCsvRow($output, ['Exported On:', date('d-m-Y H:i:s'), 'Total Bills:', count($invoices)]);
    writeCsvRow($output, ['Total Value (Rs):', number_format($totalGross, 2, '.', ''), 'Total Tax (Rs):', number_format($totalTax, 2, '.', ''), 'Total Due (Rs):', number_format($totalDue, 2, '.', '')]);
    writeCsvRow($output, []);

    // Table Columns
    $headers = [
        'Invoice #',
        'Bill Type',
        'Date',
        'Customer Name',
        'Mobile',
        'Address',
        'GSTIN',
        'Items Qty',
        'Metal Subtotal (Rs)',
        'Making Charges (Rs)',
        'Discount (Rs)',
        'Taxable Value (Rs)'
    ];

    if ($type === 'gst' || $type === 'all') {
        $headers[] = 'CGST (Rs)';
        $headers[] = 'SGST (Rs)';
        $headers[] = 'IGST (Rs)';
        $headers[] = 'Total Tax (Rs)';
    }

    $headers[] = 'Final Amount (Rs)';
    $headers[] = 'Paid (Rs)';
    $headers[] = 'Due Balance (Rs)';
    $headers[] = 'Payment Status';
    $headers[] = 'Status';

    writeCsvRow($output, $headers);

    foreach ($invoices as $inv) {
        $isGstBill = ($inv['total_tax'] > 0 || $inv['invoice_type'] === 'tax_invoice');
        $bType = $isGstBill ? 'GST Bill (Tax Invoice)' : 'Non-GST (Retail Estimate)';

        $row = [
            $inv['invoice_number'],
            $bType,
            date('d-m-Y', strtotime($inv['invoice_date'])),
            $inv['customer_name'] ?: 'Walk-in Customer',
            $inv['customer_phone'] ?: '',
            $inv['customer_address'] ?: '',
            $isGstBill ? $inv['customer_gstin'] : '',
            $inv['items_count'],
            number_format($inv['subtotal'], 2, '.', ''),
            number_format($inv['total_making_charges'], 2, '.', ''),
            number_format($inv['discount_amount'], 2, '.', ''),
            number_format($inv['taxable_amount'], 2, '.', '')
        ];

        if ($type === 'gst' || $type === 'all') {
            $row[] = number_format($inv['cgst_amount'], 2, '.', '');
            $row[] = number_format($inv['sgst_amount'], 2, '.', '');
            $row[] = number_format($inv['igst_amount'], 2, '.', '');
            $row[] = number_format($inv['total_tax'], 2, '.', '');
        }

        $row[] = number_format($inv['final_amount'], 2, '.', '');
        $row[] = number_format($inv['paid_amount'], 2, '.', '');
        $row[] = number_format($inv['due_amount'], 2, '.', '');
        $row[] = ucfirst($inv['payment_status']);
        $row[] = ucfirst($inv['status']);

        writeCsvRow($output, $row);
    }

    // Grand Totals Row
    $totalRow = ['', '', '', 'TOTALS (' . count($invoices) . ' bills)', '', '', '', '', '', '', '', ''];
    if ($type === 'gst' || $type === 'all') {
        $totalRow[] = '';
        $totalRow[] = '';
        $totalRow[] = '';
        $totalRow[] = number_format($totalTax, 2, '.', '');
    }
    $totalRow[] = number_format($totalGross, 2, '.', '');
    $totalRow[] = number_format($totalPaid, 2, '.', '');
    $totalRow[] = number_format($totalDue, 2, '.', '');
    $totalRow[] = '';
    $totalRow[] = '';

    writeCsvRow($output, []);
    writeCsvRow($output, $totalRow);

    fclose($output);
    exit;
}

// -------------------------------------------------------------------------
// PDF PRINT / DOWNLOAD VIEW
// -------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($reportTitle); ?> - <?php echo sanitize($shopName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- html2pdf for direct PDF file generation & download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; background: #F8FAFC; color: #1E293B; font-size: 13px; }
        .export-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; margin: 20px auto; max-width: 1200px; }
        .table-custom th { background: #213C72; color: #fff; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px; }
        .table-custom td { padding: 10px 8px; font-size: 12.5px; border-bottom: 1px solid #E2E8F0; }
        .no-print { position: sticky; top: 0; z-index: 1000; background: #213C72; color: #fff; padding: 12px 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .export-card { box-shadow: none !important; padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>

<!-- Action Bar -->
<div class="no-print d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-gem text-warning fs-5"></i>
        <strong class="fs-6"><?php echo sanitize($shopName); ?> &bull; <?php echo sanitize($reportTitle); ?></strong>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?php echo url('modules/bill-history/export.php?format=csv&type=' . $type . ($search ? '&search=' . urlencode($search) : '')); ?>" class="btn btn-sm btn-outline-light">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Excel / CSV
        </a>
        <button type="button" class="btn btn-sm btn-warning fw-bold text-dark" onclick="downloadReportPdf()">
            <i class="bi bi-download me-1"></i> Download PDF File
        </button>
        <button type="button" class="btn btn-sm btn-light fw-semibold" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <a href="<?php echo url('modules/bill-history/index.php?type=' . $type); ?>" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="container-fluid py-4" id="reportContent">
    <div class="export-card">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-primary"><?php echo sanitize($shopName); ?></h3>
                <div class="text-muted fs-sm"><?php echo sanitize(getSetting('shop_address', '')); ?></div>
                <div class="text-muted fs-sm">Phone: <?php echo sanitize($shopPhone); ?> <?php if ($type !== 'non_gst' && $shopGstin): ?>| GSTIN: <strong><?php echo sanitize($shopGstin); ?></strong><?php endif; ?></div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold mb-1 <?php echo $type === 'gst' ? 'text-success' : ($type === 'non_gst' ? 'text-secondary' : 'text-primary'); ?>">
                    <?php echo sanitize($reportTitle); ?>
                </h4>
                <div class="text-muted fs-sm">Generated: <strong><?php echo date('d M Y, h:i A'); ?></strong></div>
                <div class="text-muted fs-sm">Filter: <strong><?php echo $type === 'gst' ? 'GST Bills (Tax Invoice)' : ($type === 'non_gst' ? 'Non-GST (Retail Estimate)' : 'All Invoices'); ?></strong></div>
            </div>
        </div>

        <!-- Metrics Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-xs text-uppercase fw-semibold">Total Records</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?php echo count($invoices); ?> bills</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-xs text-uppercase fw-semibold">Gross Invoiced Value</div>
                    <div class="fs-4 fw-bold text-primary mt-1">₹<?php echo number_format($totalGross, 2); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-xs text-uppercase fw-semibold"><?php echo $type === 'non_gst' ? 'Tax (0% Estimate)' : 'GST Tax Collected'; ?></div>
                    <div class="fs-4 fw-bold text-success mt-1">₹<?php echo number_format($totalTax, 2); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted fs-xs text-uppercase fw-semibold">Pending Receivables</div>
                    <div class="fs-4 fw-bold <?php echo $totalDue > 0 ? 'text-danger' : 'text-success'; ?> mt-1">₹<?php echo number_format($totalDue, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <?php if ($type !== 'non_gst'): ?>
                        <th>GSTIN</th>
                        <th class="text-end">Tax (₹)</th>
                        <?php endif; ?>
                        <th class="text-end">Final Amount (₹)</th>
                        <th class="text-end">Paid (₹)</th>
                        <th class="text-end">Due (₹)</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="<?php echo $type !== 'non_gst' ? '11' : '9'; ?>" class="text-center py-4 text-muted">
                            No bills found matching this criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        $isGst = ($inv['total_tax'] > 0 || $inv['invoice_type'] === 'tax_invoice');
                    ?>
                    <tr>
                        <td class="fw-bold text-primary"><?php echo sanitize($inv['invoice_number']); ?></td>
                        <td>
                            <?php if ($isGst): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">GST Bill</span>
                            <?php else: ?>
                            <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle">Non-GST</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                        <td><strong><?php echo sanitize($inv['customer_name'] ?: 'Walk-in Customer'); ?></strong></td>
                        <td><?php echo sanitize($inv['customer_phone'] ?: '-'); ?></td>
                        <?php if ($type !== 'non_gst'): ?>
                        <td><small><?php echo $isGst ? sanitize($inv['customer_gstin'] ?: '-') : '-'; ?></small></td>
                        <td class="text-end text-success">₹<?php echo number_format($inv['total_tax'], 2); ?></td>
                        <?php endif; ?>
                        <td class="text-end fw-bold">₹<?php echo number_format($inv['final_amount'], 2); ?></td>
                        <td class="text-end text-success">₹<?php echo number_format($inv['paid_amount'], 2); ?></td>
                        <td class="text-end <?php echo $inv['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                            ₹<?php echo number_format($inv['due_amount'], 2); ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?php echo $inv['status'] === 'cancelled' ? 'bg-secondary' : ($inv['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'); ?>">
                                <?php echo ucfirst($inv['status'] === 'cancelled' ? 'Cancelled' : $inv['payment_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-light fw-bold">
                        <td colspan="5" class="text-end">TOTALS:</td>
                        <?php if ($type !== 'non_gst'): ?>
                        <td></td>
                        <td class="text-end text-success">₹<?php echo number_format($totalTax, 2); ?></td>
                        <?php endif; ?>
                        <td class="text-end text-primary fs-6">₹<?php echo number_format($totalGross, 2); ?></td>
                        <td class="text-end text-success">₹<?php echo number_format($totalPaid, 2); ?></td>
                        <td class="text-end text-danger">₹<?php echo number_format($totalDue, 2); ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-3 border-top d-flex justify-content-between text-muted fs-xs">
            <div>Jewellery POS & ERP System &bull; Confidential & Internal Report</div>
            <div>Page 1 of 1 &bull; <?php echo date('d-m-Y'); ?></div>
        </div>
    </div>
</div>

<script>
function downloadReportPdf() {
    const element = document.getElementById('reportContent');
    const opt = {
        margin:       [8, 8, 8, 8],
        filename:     '<?php echo $baseFilename; ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>
</body>
</html>
