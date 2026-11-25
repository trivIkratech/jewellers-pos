<?php
/**
 * Notifications AJAX Endpoint
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

if ($action === 'mark_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
    $stmt->execute([$currentUser['id']]);
    echo json_encode(['success' => true]);
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare("SELECT id, title, message, type, is_read, link, created_at 
                       FROM notifications 
                       WHERE user_id = ? OR user_id IS NULL 
                       ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$currentUser['id']]);
$notifications = $stmt->fetchAll();

foreach ($notifications as &$notif) {
    $time = strtotime($notif['created_at']);
    $diff = time() - $time;
    if ($diff < 60) {
        $notif['time_ago'] = 'Just now';
    } elseif ($diff < 3600) {
        $notif['time_ago'] = floor($diff / 60) . ' mins ago';
    } elseif ($diff < 86400) {
        $notif['time_ago'] = floor($diff / 3600) . ' hours ago';
    } else {
        $notif['time_ago'] = date('d M, Y', $time);
    }
}

echo json_encode(['notifications' => $notifications]);

