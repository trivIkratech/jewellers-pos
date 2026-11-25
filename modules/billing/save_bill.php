<?php
/**
 * Save Bill Transaction Processor
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('modules/billing/index.php'), 'Invalid request method.', 'danger');
}

// Check CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirect(url('modules/billing/index.php'), 'Session expired or invalid token. Please try again.', 'danger');
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Decode form parameters
$rawCustomerId = trim($_POST['customer_id'] ?? '');
$customerId = is_numeric($rawCustomerId) ? (int) $rawCustomerId : null;
$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$customerGstin = trim($_POST['customer_gstin'] ?? '');
$invoiceDate = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d');
$notes = trim($_POST['notes'] ?? '');

// Metal rates recorded at time of bill
$goldRate24k = (float) ($_POST['gold_rate_24k'] ?? 0);
$goldRate22k = (float) ($_POST['gold_rate_22k'] ?? 0);
$goldRate18k = (float) ($_POST['gold_rate_18k'] ?? 0);
$silverRate = (float) ($_POST['silver_rate'] ?? 0);

// Items array
$items = $_POST['items'] ?? [];
if (empty($items) || !is_array($items)) {
    redirect(url('modules/billing/index.php'), 'Please add at least one jewellery product to the bill.', 'danger');
}

// Totals from form & calculated server-side for verification
$subtotal = 0.00;
$totalMakingCharges = 0.00;
$totalStoneCharges = 0.00;
$totalWastageCharges = 0.00;

$processedItems = [];

foreach ($items as $item) {
    $productId = !empty($item['product_id']) ? (int) $item['product_id'] : null;
    $productCode = trim($item['product_code'] ?? '');
    $productName = trim($item['product_name'] ?? '');
    $category = trim($item['category'] ?? '');
    $metalType = trim($item['metal_type'] ?? 'gold');
    $purity = trim($item['purity'] ?? '22K');
    $hsnCode = trim($item['hsn_code'] ?? '7113');
    $qty = max(1, (int) ($item['quantity'] ?? 1));
    $grossWeight = (float) ($item['gross_weight'] ?? 0);
    $stoneWeight = (float) ($item['stone_weight'] ?? 0);
    $netWeight = max(0, (float) ($item['net_weight'] ?? ($grossWeight - $stoneWeight)));
    $ratePerGram = (float) ($item['rate_per_gram'] ?? 0);

    // Metal value
    $metalValue = round($netWeight * $ratePerGram, 2);

    // Making charges
    $makingType = in_array($item['making_charges_type'] ?? '', ['per_gram', 'percentage', 'fixed']) ? $item['making_charges_type'] : 'per_gram';
    $makingRate = (float) ($item['making_charges_rate'] ?? 0);
    if ($makingType === 'per_gram') {
        $makingCharges = round($netWeight * $makingRate, 2);
    } elseif ($makingType === 'percentage') {
        $makingCharges = round(($metalValue * $makingRate) / 100, 2);
    } else {
        $makingCharges = round($makingRate, 2);
    }

    // Working Charges (मजदूरी)
    // Note: Per shop preference, only working charges are added to the bill (wastage & extra charges not applied)
    $wastagePercentage = 0.00;
    $wastageCharges = 0.00;
    $stoneCharges = 0.00;

    // Item line total (Metal Value + Working Charges)
    $itemTotal = round($metalValue + $makingCharges, 2) * $qty;

    $subtotal += $metalValue * $qty;
    $totalMakingCharges += $makingCharges * $qty;
    $totalWastageCharges = 0.00;
    $totalStoneCharges = 0.00;

    $processedItems[] = [
        'product_id' => $productId,
        'product_code' => $productCode,
        'product_name' => $productName,
        'category' => $category,
        'metal_type' => $metalType,
        'purity' => $purity,
        'hsn_code' => $hsnCode,
        'quantity' => $qty,
        'gross_weight' => $grossWeight,
        'net_weight' => $netWeight,
        'stone_weight' => $stoneWeight,
        'rate_per_gram' => $ratePerGram,
        'metal_value' => $metalValue * $qty,
        'making_charges_type' => $makingType,
        'making_charges_rate' => $makingRate,
        'making_charges' => $makingCharges * $qty,
        'wastage_percentage' => $wastagePercentage,
        'wastage_charges' => $wastageCharges,
        'stone_charges' => $stoneCharges,
        'item_total' => $itemTotal
    ];
}

// Discount: Applied on (Metal Value + Working Charges)
$rawSubtotalWithCharges = $subtotal + $totalMakingCharges;
$discountType = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'fixed';
$discountValue = (float) ($_POST['discount_value'] ?? 0);
if ($discountType === 'percentage') {
    $discountAmount = round(($rawSubtotalWithCharges * $discountValue) / 100, 2);
} else {
    $discountAmount = min($rawSubtotalWithCharges, $discountValue);
}

// Taxable amount
$taxableAmount = max(0, $rawSubtotalWithCharges - $discountAmount);

// GST Calculation (Option for With GST [3%] or Without GST [0% / Estimate])
$billType = ($_POST['bill_type'] ?? 'with_gst') === 'without_gst' ? 'estimate' : 'tax_invoice';
$isIgst = !empty($_POST['is_igst']);

if ($billType === 'estimate') {
    // Non-GST / Estimate Bill
    $cgstRate = 0.00;
    $cgstAmount = 0.00;
    $sgstRate = 0.00;
    $sgstAmount = 0.00;
    $igstRate = 0.00;
    $igstAmount = 0.00;
    $totalTax = 0.00;
} elseif ($isIgst) {
    $gstRate = (float) getSetting('gst_rate', '3.00');
    $cgstRate = 0.00;
    $cgstAmount = 0.00;
    $sgstRate = 0.00;
    $sgstAmount = 0.00;
    $igstRate = $gstRate;
    $igstAmount = round(($taxableAmount * $igstRate) / 100, 2);
    $totalTax = $igstAmount;
} else {
    $gstRate = (float) getSetting('gst_rate', '3.00');
    $halfRate = round($gstRate / 2, 2);
    $cgstRate = $halfRate;
    $cgstAmount = round(($taxableAmount * $cgstRate) / 100, 2);
    $sgstRate = $halfRate;
    $sgstAmount = round(($taxableAmount * $sgstRate) / 100, 2);
    $igstRate = 0.00;
    $igstAmount = 0.00;
    $totalTax = round($cgstAmount + $sgstAmount, 2);
}

$grandTotal = round($taxableAmount + $totalTax, 2);
$finalAmount = round($grandTotal);
$roundOff = round($finalAmount - $grandTotal, 2);

$amountInWords = numberToWords($finalAmount);

// Payment details
$paidAmount = min($finalAmount, max(0, (float) ($_POST['paid_amount'] ?? 0)));
$dueAmount = max(0, round($finalAmount - $paidAmount, 2));
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$paymentRef = trim($_POST['payment_reference'] ?? '');

$paymentStatus = 'unpaid';
if ($paidAmount >= $finalAmount) {
    $paymentStatus = 'paid';
} elseif ($paidAmount > 0) {
    $paymentStatus = 'partial';
}

// BEGIN ATOMIC TRANSACTION
$pdo->beginTransaction();

try {
    // 1. Resolve / Auto-Register Customer into System
    // Check if this is a Walk-in Customer (no personal details on bill, but tracked in customers database)
    $isWalkIn = (
        $rawCustomerId === 'walk_in' ||
        strcasecmp($customerName, 'Walk-in Customer') === 0 ||
        strcasecmp($customerPhone, 'Walk-in') === 0 ||
        (empty($customerName) && empty($customerPhone))
    );

    if ($isWalkIn) {
        // Find or create the master "Walk-in Customer" record in customers table
        $walkInStmt = $pdo->prepare("SELECT id FROM customers WHERE name = 'Walk-in Customer' LIMIT 1");
        $walkInStmt->execute();
        $walkInRow = $walkInStmt->fetch(PDO::FETCH_ASSOC);

        if ($walkInRow) {
            $customerId = (int) $walkInRow['id'];
        } else {
            $insWalkIn = $pdo->prepare("INSERT INTO customers (name, phone, address, total_purchases, outstanding_balance, is_active) 
                                        VALUES ('Walk-in Customer', 'Walk-in', 'Counter Sale', 0.00, 0.00, 1)");
            $insWalkIn->execute();
            $customerId = (int) $pdo->lastInsertId();
        }

        $customerName = 'Walk-in Customer';
        $customerPhone = '';    // No phone on final invoice
        $customerAddress = '';  // No address on final invoice
        $customerGstin = '';
    } elseif (empty($customerId)) {
        // If cashier didn't select an existing customer from dropdown, auto-link or auto-create in customers table
        if (!empty($customerPhone)) {
            $findCust = $pdo->prepare("SELECT id, name, address, gstin FROM customers WHERE phone = ? LIMIT 1");
            $findCust->execute([$customerPhone]);
            $existingCust = $findCust->fetch(PDO::FETCH_ASSOC);

            if ($existingCust) {
                $customerId = (int) $existingCust['id'];
                // Update missing address, name, or GSTIN if provided on this bill
                $updateFields = [];
                $updateParams = [];
                if (empty($existingCust['name']) && !empty($customerName)) {
                    $updateFields[] = "name = ?";
                    $updateParams[] = $customerName;
                }
                if (empty($existingCust['address']) && !empty($customerAddress)) {
                    $updateFields[] = "address = ?";
                    $updateParams[] = $customerAddress;
                }
                if (empty($existingCust['gstin']) && !empty($customerGstin)) {
                    $updateFields[] = "gstin = ?";
                    $updateParams[] = $customerGstin;
                }
                if (!empty($updateFields)) {
                    $updateParams[] = $customerId;
                    $pdo->prepare("UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($updateParams);
                }
            } else {
                // Auto-create new customer in customers table
                $displayName = !empty($customerName) ? $customerName : 'Walk-in Customer';
                $insCust = $pdo->prepare("INSERT INTO customers (
                    name, phone, address, gstin, total_purchases, outstanding_balance, is_active
                ) VALUES (?, ?, ?, ?, 0.00, 0.00, 1)");
                $insCust->execute([
                    $displayName,
                    $customerPhone,
                    $customerAddress,
                    $customerGstin
                ]);
                $customerId = (int) $pdo->lastInsertId();

                logAudit($userId, 'create', 'customers', $customerId, null, [
                    'name' => $displayName,
                    'phone' => $customerPhone,
                    'auto_registered' => true,
                    'source' => 'pos_billing'
                ]);
            }
        } elseif (!empty($customerName)) {
            // Customer phone was not provided, but name is provided
            $insCust = $pdo->prepare("INSERT INTO customers (
                name, phone, address, gstin, total_purchases, outstanding_balance, is_active
            ) VALUES (?, '', ?, ?, 0.00, 0.00, 1)");
            $insCust->execute([
                $customerName,
                $customerAddress,
                $customerGstin
            ]);
            $customerId = (int) $pdo->lastInsertId();
        }
    } else {
        // Customer was chosen by ID; sync missing address/GSTIN if provided
        if (!empty($customerAddress) || !empty($customerGstin)) {
            $updCust = $pdo->prepare("UPDATE customers SET 
                address = CASE WHEN (address IS NULL OR address = '') THEN ? ELSE address END,
                gstin = CASE WHEN (gstin IS NULL OR gstin = '') THEN ? ELSE gstin END
                WHERE id = ?");
            $updCust->execute([$customerAddress, $customerGstin, $customerId]);
        }
    }

    // 2. Generate unique invoice number (Separate Series: INV- for GST, EST- for Non-GST)
    $invoiceNumber = generateInvoiceNumber($billType);

    // Double check unique invoice number
    $checkStmt = $pdo->prepare("SELECT id FROM invoices WHERE invoice_number = ?");
    $checkStmt->execute([$invoiceNumber]);
    if ($checkStmt->fetch()) {
        $invoiceNumber = $invoiceNumber . '-' . rand(10, 99);
    }

    // 3. Insert Invoice
    $invStmt = $pdo->prepare("INSERT INTO invoices (
        invoice_number, invoice_type, invoice_date, customer_id, customer_name, customer_phone, customer_address, customer_gstin,
        gold_rate_24k, gold_rate_22k, gold_rate_18k, silver_rate,
        subtotal, total_making_charges, total_stone_charges, total_wastage_charges,
        discount_type, discount_value, discount_amount, taxable_amount,
        cgst_rate, cgst_amount, sgst_rate, sgst_amount, igst_rate, igst_amount, total_tax,
        grand_total, round_off, final_amount, amount_in_words,
        paid_amount, due_amount, payment_status, status, notes, created_by
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, 'finalized', ?, ?
    )");

    $invStmt->execute([
        $invoiceNumber,
        $billType,
        $invoiceDate,
        $customerId,
        $customerName,
        $customerPhone,
        $customerAddress,
        $customerGstin,
        $goldRate24k,
        $goldRate22k,
        $goldRate18k,
        $silverRate,
        $subtotal,
        $totalMakingCharges,
        $totalStoneCharges,
        $totalWastageCharges,
        $discountType,
        $discountValue,
        $discountAmount,
        $taxableAmount,
        $cgstRate,
        $cgstAmount,
        $sgstRate,
        $sgstAmount,
        $igstRate,
        $igstAmount,
        $totalTax,
        $grandTotal,
        $roundOff,
        $finalAmount,
        $amountInWords,
        $paidAmount,
        $dueAmount,
        $paymentStatus,
        $notes,
        $userId
    ]);

    $invoiceId = (int) $pdo->lastInsertId();

    // 3. Insert Invoice Items & Update Inventory
    $itemInsertStmt = $pdo->prepare("INSERT INTO invoice_items (
        invoice_id, product_id, product_code, product_name, category, metal_type, purity, hsn_code,
        quantity, gross_weight, net_weight, stone_weight, rate_per_gram, metal_value,
        making_charges_type, making_charges_rate, making_charges,
        wastage_percentage, wastage_charges, stone_charges, item_total
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?
    )");

    $stockUpdateStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $stockSelectStmt = $pdo->prepare("SELECT stock_quantity, name, min_stock_level FROM products WHERE id = ? FOR UPDATE");

    $txStmt = $pdo->prepare("INSERT INTO inventory_transactions (
        product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, created_by
    ) VALUES (
        ?, 'sale', ?, ?, ?, 'invoice', ?, ?, ?
    )");

    foreach ($processedItems as $pItem) {
        $itemInsertStmt->execute([
            $invoiceId,
            $pItem['product_id'],
            $pItem['product_code'],
            $pItem['product_name'],
            $pItem['category'],
            $pItem['metal_type'],
            $pItem['purity'],
            $pItem['hsn_code'],
            $pItem['quantity'],
            $pItem['gross_weight'],
            $pItem['net_weight'],
            $pItem['stone_weight'],
            $pItem['rate_per_gram'],
            $pItem['metal_value'],
            $pItem['making_charges_type'],
            $pItem['making_charges_rate'],
            $pItem['making_charges'],
            $pItem['wastage_percentage'],
            $pItem['wastage_charges'],
            $pItem['stone_charges'],
            $pItem['item_total']
        ]);

        // If tied to product, deduct inventory and record stock transaction
        if ($pItem['product_id']) {
            $stockSelectStmt->execute([$pItem['product_id']]);
            $prod = $stockSelectStmt->fetch();
            if ($prod) {
                $prevStock = (int) $prod['stock_quantity'];
                $newStock = max(0, $prevStock - $pItem['quantity']);

                $stockUpdateStmt->execute([$pItem['quantity'], $pItem['product_id']]);

                $txStmt->execute([
                    $pItem['product_id'],
                    $pItem['quantity'],
                    $prevStock,
                    $newStock,
                    $invoiceId,
                    "Sold via Invoice {$invoiceNumber}",
                    $userId
                ]);

                // If new stock is at or below minimum level, generate notification
                if ($newStock <= (int) $prod['min_stock_level']) {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type, link) VALUES (?, ?, 'warning', ?)");
                    $notifStmt->execute([
                        "Low Stock Alert: {$prod['name']}",
                        "Product stock is down to {$newStock} units following bill {$invoiceNumber}.",
                        'modules/inventory/index.php'
                    ]);
                }
            }
        }
    }

    // 4. Record Payment if paid amount > 0
    if ($paidAmount > 0) {
        $payStmt = $pdo->prepare("INSERT INTO payments (
            invoice_id, payment_date, amount, payment_method, reference_number, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $payStmt->execute([
            $invoiceId,
            $invoiceDate,
            $paidAmount,
            $paymentMethod,
            $paymentRef,
            $paymentStatus === 'paid' ? 'Full payment received' : 'Partial payment received',
            $userId
        ]);
    }

    // 5. Update Customer Total Purchases & Outstanding Balance
    if ($customerId) {
        $custStmt = $pdo->prepare("UPDATE customers 
                                   SET total_purchases = total_purchases + ?, 
                                       outstanding_balance = outstanding_balance + ? 
                                   WHERE id = ?");
        $custStmt->execute([$finalAmount, $dueAmount, $customerId]);
    }

    // 6. Audit Log
    logAudit($userId, 'create', 'invoices', $invoiceId, null, [
        'invoice_number' => $invoiceNumber,
        'final_amount' => $finalAmount,
        'paid_amount' => $paidAmount,
        'due_amount' => $dueAmount,
        'customer' => $customerName
    ]);

    // Commit transaction
    $pdo->commit();

    // Redirect to bill view or print
    $_SESSION['flash_message'] = "Bill {$invoiceNumber} created successfully!";
    $_SESSION['flash_type'] = 'success';
    header('Location: ' . url("modules/bill-history/view.php?id={$invoiceId}&print=1"));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Bill creation error: " . $e->getMessage());
    redirect(url('modules/billing/index.php'), 'Failed to create bill: ' . $e->getMessage(), 'danger');
}

