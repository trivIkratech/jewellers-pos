<?php
/**
 * Bulk Printable QR Code & Jewellery Tag Sheets (A4 Size)
 * Prints 21 tags per A4 sheet (3 columns x 7 rows) or customizable layouts
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pdo = getDBConnection();
$shopName = getSetting('shop_name', 'Sachin Jewellers');
$shopPhone = getSetting('shop_phone', '');
$shopCity = getSetting('shop_city', 'Jewellers Market');

// Filter params
$selectedIds = !empty($_GET['ids']) ? array_filter(array_map('intval', explode(',', $_GET['ids']))) : [];
$filterMetal = strtolower(trim($_GET['metal'] ?? ''));
$filterCategory = (int)($_GET['category'] ?? 0);
$perStockQty = !empty($_GET['per_stock']) && $_GET['per_stock'] === '1'; // 1 tag per piece in stock vs 1 tag per product

// Build query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 ";
$params = [];

if (!empty($selectedIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql .= " AND p.id IN ($placeholders) ";
    $params = array_merge($params, $selectedIds);
}

if (!empty($filterMetal) && in_array($filterMetal, ['gold', 'silver', 'diamond', 'platinum', 'other'])) {
    $sql .= " AND p.metal_type = ? ";
    $params[] = $filterMetal;
}

if ($filterCategory > 0) {
    $sql .= " AND p.category_id = ? ";
    $params[] = $filterCategory;
}

$sql .= " ORDER BY p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expand items if perStockQty is enabled
$tagsList = [];
foreach ($rawProducts as $p) {
    $repeatCount = $perStockQty ? max(1, (int)$p['stock_quantity']) : 1;
    for ($i = 0; $i < $repeatCount; $i++) {
        $tagsList[] = [
            'id' => $p['id'],
            'code' => $p['product_code'],
            'barcode' => $p['barcode'] ?: $p['product_code'],
            'name' => $p['name'],
            'category' => $p['category_name'] ?: ucfirst($p['metal_type']),
            'metal' => ucfirst($p['metal_type']),
            'purity' => $p['purity'] ?: '22K',
            'net_weight' => number_format((float)$p['net_weight'], 3) . ' g',
            'gross_weight' => number_format((float)$p['gross_weight'], 3) . ' g',
            'selling_price' => (float)$p['selling_price'] > 0 ? '₹' . number_format((float)$p['selling_price'], 2) : '',
            'tag_num' => ($repeatCount > 1) ? ($i + 1) . '/' . $repeatCount : ''
        ];
    }
}

// Fetch categories for filter dropdown
$catList = $pdo->query("SELECT id, name, metal_type FROM categories WHERE is_active = 1 ORDER BY metal_type, name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Jewellery QR Tags (A4 Sheet) - <?php echo sanitize($shopName); ?></title>
    
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
    
    <!-- QR Code Generator Library -->
    <script src="<?php echo url('assets/js/qrcode.min.js'); ?>"></script>

    <style>
        /* Screen & Controls Styling */
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        .controls-toolbar {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 24px;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        /* A4 Sheet Container */
        .a4-page-wrapper {
            display: flex;
            justify-content: center;
            padding: 24px 12px 48px;
        }

        .a4-sheet {
            width: 210mm;
            min-height: 297mm;
            padding: 8mm 6mm;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
        }

        /* 3-Column Tag Grid */
        .tags-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3.5mm 3.5mm;
            width: 100%;
        }

        /* Individual Tag */
        .jewellery-tag {
            width: 100%;
            height: 38mm;
            border: 1px dashed #94a3b8;
            border-radius: 4px;
            padding: 2mm 2.5mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
            position: relative;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .tag-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1.2mm;
            margin-bottom: 1mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tag-shop-name {
            font-size: 7.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 125px;
        }

        .tag-hallmark {
            font-size: 6.5pt;
            font-weight: 700;
            color: #b45309;
            background: #fef3c7;
            padding: 0.5px 3px;
            border-radius: 2px;
            border: 0.5px solid #fde68a;
            white-space: nowrap;
        }

        .tag-body {
            display: flex;
            align-items: center;
            gap: 2mm;
            flex: 1;
        }

        .tag-qr-wrapper {
            width: 25mm;
            height: 25mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #cbd5e1;
            padding: 1mm;
            background: #ffffff;
            border-radius: 2px;
        }

        .tag-qr-wrapper canvas,
        .tag-qr-wrapper img {
            width: 23mm !important;
            height: 23mm !important;
            display: block;
        }

        .tag-details {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            line-height: 1.25;
        }

        .tag-prod-title {
            font-size: 7.8pt;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 1mm;
        }

        .tag-prod-code {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 8.5pt;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            margin-bottom: 1mm;
        }

        .tag-meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 7pt;
            color: #334155;
            margin-bottom: 0.6mm;
        }

        .tag-purity {
            font-weight: 700;
            color: #0f172a;
        }

        .tag-weight {
            font-weight: 800;
            color: #047857;
        }

        .tag-barcode-text {
            font-size: 6pt;
            font-family: monospace;
            color: #64748b;
            text-align: right;
            margin-top: 0.5mm;
            letter-spacing: 0.5px;
        }

        .tag-footer-info {
            font-size: 6pt;
            color: #64748b;
            border-top: 0.5px solid #f1f5f9;
            padding-top: 0.5mm;
            display: flex;
            justify-content: space-between;
        }

        /* PRINT OPTIMIZATION (A4 Size) */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .a4-page-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }

            .a4-sheet {
                width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 4mm 2mm !important;
                margin: 0 !important;
            }

            .jewellery-tag {
                border: 0.8px dashed #64748b !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            @page {
                size: A4 portrait;
                margin: 8mm 6mm;
            }
        }
    </style>
</head>
<body>

    <!-- Controls Toolbar (Hidden in Print) -->
    <div class="controls-toolbar no-print">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 max-w-7xl mx-auto">
            
            <div class="d-flex align-items-center gap-3">
                <a href="<?php echo url('modules/inventory/index.php'); ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Inventory
                </a>
                <div>
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-qr-code text-primary"></i> Bulk Jewellery QR Sheet (A4)
                    </h5>
                    <small class="text-muted">
                        Total <strong><?php echo count($tagsList); ?> tags</strong> ready to print 
                        (<?php echo ceil(count($tagsList) / 21); ?> A4 Sheets @ 21 tags/sheet)
                    </small>
                </div>
            </div>

            <!-- Filters & Options Form -->
            <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
                <?php if (!empty($selectedIds)): ?>
                <input type="hidden" name="ids" value="<?php echo implode(',', $selectedIds); ?>">
                <?php endif; ?>

                <select name="metal" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 120px;">
                    <option value="">All Metals</option>
                    <option value="gold" <?php echo $filterMetal === 'gold' ? 'selected' : ''; ?>>Gold</option>
                    <option value="silver" <?php echo $filterMetal === 'silver' ? 'selected' : ''; ?>>Silver</option>
                    <option value="diamond" <?php echo $filterMetal === 'diamond' ? 'selected' : ''; ?>>Diamond</option>
                    <option value="platinum" <?php echo $filterMetal === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                    <option value="other" <?php echo $filterMetal === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>

                <div class="form-check form-switch ms-2 me-2">
                    <input class="form-check-input cursor-pointer" type="checkbox" name="per_stock" value="1" id="perStockChk" <?php echo $perStockQty ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="form-check-label fs-xs fw-semibold cursor-pointer" for="perStockChk">
                        1 Tag Per Stock Qty
                    </label>
                </div>

                <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill fw-bold d-flex align-items-center gap-2 shadow" onclick="window.print()">
                    <i class="bi bi-printer-fill fs-6"></i>
                    <span>Print A4 QR Sheet (Ctrl+P)</span>
                </button>
            </form>

        </div>
    </div>

    <!-- Printable A4 Sheet Body -->
    <div class="a4-page-wrapper">
        <div class="a4-sheet">

            <?php if (empty($tagsList)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-dark">No Products Found for QR Generation</h5>
                <p class="text-muted fs-sm">Please add products to inventory or clear your filters.</p>
                <a href="<?php echo url('modules/inventory/add.php'); ?>" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                </a>
            </div>
            <?php else: ?>

            <div class="tags-grid" id="tagsGridContainer">
                <?php foreach ($tagsList as $idx => $tag): ?>
                <div class="jewellery-tag">
                    
                    <!-- Header: Shop & Purity -->
                    <div class="tag-header">
                        <span class="tag-shop-name"><?php echo sanitize($shopName); ?></span>
                        <span class="tag-hallmark"><?php echo sanitize($tag['purity']); ?></span>
                    </div>

                    <!-- Body: QR Code + Item Details -->
                    <div class="tag-body">
                        
                        <!-- QR Code Canvas -->
                        <div class="tag-qr-wrapper" id="qrCanvas_<?php echo $idx; ?>" data-code="<?php echo sanitize($tag['code']); ?>"></div>

                        <!-- Tag Details -->
                        <div class="tag-details">
                            <div class="tag-prod-title" title="<?php echo sanitize($tag['name']); ?>">
                                <?php echo sanitize($tag['name']); ?>
                            </div>
                            
                            <div class="tag-prod-code">
                                <?php echo sanitize($tag['code']); ?>
                            </div>

                            <div class="tag-meta-row">
                                <span class="tag-purity"><?php echo sanitize($tag['metal']); ?></span>
                                <span class="tag-weight"><?php echo sanitize($tag['net_weight']); ?></span>
                            </div>

                            <div class="tag-meta-row" style="margin-bottom: 0;">
                                <span style="font-size: 6.5pt; color: #64748b;">Gross: <?php echo sanitize($tag['gross_weight']); ?></span>
                                <?php if ($tag['tag_num']): ?>
                                <span class="badge bg-light text-dark border p-0 px-1" style="font-size: 5.5pt;"><?php echo $tag['tag_num']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="tag-barcode-text">
                                <?php echo sanitize($tag['barcode']); ?>
                            </div>
                        </div>

                    </div>

                    <!-- Footer line -->
                    <div class="tag-footer-info">
                        <span>Scan for POS Bill</span>
                        <span><?php echo date('d/m/Y'); ?></span>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- Script to Generate High-Res QR Codes in Parallel -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const qrWrappers = document.querySelectorAll(".tag-qr-wrapper");
        qrWrappers.forEach((el, index) => {
            const code = el.getAttribute("data-code");
            if (code) {
                // Generate crisp black-on-white QR Code
                new QRCode(el, {
                    text: code,
                    width: 90,
                    height: 90,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    });
    </script>

</body>
</html>
