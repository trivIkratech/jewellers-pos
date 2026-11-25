<?php
/**
 * Discount Approvals AJAX Endpoint
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Salesperson submits discount approval request
if ($action === 'request') {
    $customerName = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $billSubtotal = (float)($_POST['bill_subtotal'] ?? 0);
    $discountType = in_array($_POST['discount_type'] ?? '', ['fixed', 'percentage']) ? $_POST['discount_type'] : 'fixed';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($discountValue <= 0) {
        echo json_encode(['success' => false, 'message' => 'Discount value must be greater than zero.']);
        exit;
    }

    if ($billSubtotal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Bill subtotal is required to calculate discount.']);
        exit;
    }

    // Calculate requested discount amount
    if ($discountType === 'percentage') {
        $requestedAmount = round(($billSubtotal * $discountValue) / 100, 2);
    } else {
        $requestedAmount = min($billSubtotal, $discountValue);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO discount_approvals 
            (salesperson_id, salesperson_name, customer_name, bill_subtotal, requested_discount_type, requested_discount_value, requested_discount_amount, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([
            $currentUser['id'],
            $currentUser['name'] ?: $currentUser['username'],
            $customerName,
            $billSubtotal,
            $discountType,
            $discountValue,
            $requestedAmount,
            $reason
        ]);
        $requestId = $pdo->lastInsertId();

        // Send Notification for Admin
        $notifTitle = "Discount Approval Request: " . $customerName;
        $discLabel = $discountType === 'percentage' ? $discountValue . '%' : '₹' . number_format($discountValue, 2);
        $notifMsg = ($currentUser['name'] ?: $currentUser['username']) . " requested " . $discLabel . " (₹" . number_format($requestedAmount, 2) . ") discount on subtotal ₹" . number_format($billSubtotal, 2);
        
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, link) VALUES (NULL, ?, ?, 'warning', 0, 'modules/dashboard/index.php')");
        $notifStmt->execute([$notifTitle, $notifMsg]);

        echo json_encode([
            'success' => true,
            'request_id' => $requestId,
            'requested_type' => $discountType,
            'requested_value' => $discountValue,
            'requested_amount' => $requestedAmount,
            'message' => 'Discount approval request submitted to admin.'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// 2. Check request status (called from billing page polling/refresh)
if ($action === 'check_status') {
    $requestId = (int)($_GET['request_id'] ?? 0);
    if (!$requestId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM discount_approvals WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status' => $req['status'],
        'approved_type' => $req['approved_discount_type'],
        'approved_value' => (float)$req['approved_discount_value'],
        'approved_amount' => (float)$req['approved_discount_amount'],
        'admin_name' => $req['admin_name'],
        'admin_notes' => $req['admin_notes']
    ]);
    exit;
}

// 3. Admin action: review, modify discount type/value, and approve or reject
if ($action === 'admin_action') {
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin permission required.']);
        exit;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = strtolower(trim($_POST['decision'] ?? '')); // 'approve' or 'reject'
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!$requestId || !in_array($decision, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid decision or request ID']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM discount_approvals WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    if ($decision === 'reject') {
        $updateStmt = $pdo->prepare("UPDATE discount_approvals SET status = 'rejected', admin_id = ?, admin_name = ?, admin_notes = ? WHERE id = ?");
        $updateStmt->execute([$currentUser['id'], $currentUser['name'] ?: $currentUser['username'], $adminNotes, $requestId]);

        // Send Notification to Salesperson
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, link) VALUES (?, ?, ?, 'danger', 0, 'modules/billing/index.php')");
        $notifStmt->execute([
            $req['salesperson_id'],
            "Discount Request Rejected",
            "Admin rejected discount request for " . $req['customer_name'] . ($adminNotes ? ": " . $adminNotes : "")
        ]);

        echo json_encode(['success' => true, 'status' => 'rejected', 'message' => 'Discount request rejected.']);
        exit;
    }

    // Approve: Admin can change type ('fixed' or 'percentage') and value!
    $approvedType = in_array($_POST['approved_type'] ?? '', ['fixed', 'percentage']) ? $_POST['approved_type'] : $req['requested_discount_type'];
    $approvedValue = isset($_POST['approved_value']) ? (float)$_POST['approved_value'] : (float)$req['requested_discount_value'];

    $subtotal = (float)$req['bill_subtotal'];
    if ($approvedType === 'percentage') {
        $approvedAmount = round(($subtotal * $approvedValue) / 100, 2);
    } else {
        $approvedAmount = min($subtotal, $approvedValue);
    }

    $updateStmt = $pdo->prepare("UPDATE discount_approvals 
        SET status = 'approved', approved_discount_type = ?, approved_discount_value = ?, approved_discount_amount = ?, admin_id = ?, admin_name = ?, admin_notes = ? 
        WHERE id = ?");
    $updateStmt->execute([
        $approvedType,
        $approvedValue,
        $approvedAmount,
        $currentUser['id'],
        $currentUser['name'] ?: $currentUser['username'],
        $adminNotes,
        $requestId
    ]);

    // Send Notification to Salesperson
    $approvedLabel = $approvedType === 'percentage' ? $approvedValue . '%' : '₹' . number_format($approvedValue, 2);
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, link) VALUES (?, ?, ?, 'success', 0, 'modules/billing/index.php')");
    $notifStmt->execute([
        $req['salesperson_id'],
        "Discount Approved: " . $approvedLabel,
        "Admin approved " . $approvedLabel . " (₹" . number_format($approvedAmount, 2) . ") discount for " . $req['customer_name']
    ]);

    echo json_encode([
        'success' => true,
        'status' => 'approved',
        'approved_type' => $approvedType,
        'approved_value' => $approvedValue,
        'approved_amount' => $approvedAmount,
        'message' => 'Discount approved successfully.'
    ]);
    exit;
}

// 4. Get details of a single request
if ($action === 'get_details') {
    $requestId = (int)($_GET['request_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM discount_approvals WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    echo json_encode(['success' => true, 'request' => $req]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

