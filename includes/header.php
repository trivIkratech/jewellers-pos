<?php
/**
 * Header Component
 * Jewellery ERP Management System
 */

// Ensure auth is loaded
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}

$currentUser = getCurrentUser();
$flash = getFlashMessage();

// Get unread notifications count
$notifCount = 0;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $stmt->execute([$currentUser['id']]);
    $notifCount = $stmt->fetch()['count'] ?? 0;
} catch (PDOException $e) {
    $notifCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Jewellery ERP'; ?> - <?php echo getSetting('shop_name', 'Jewellery ERP'); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
    
    <?php if (isset($extraCSS)) echo $extraCSS; ?>
</head>
<body>
<div class="wrapper">

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="topbar">
            <div class="topbar-left">
                <button class="btn-toggle-sidebar" id="toggleSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
            </div>
            <div class="topbar-right">
                <!-- Search -->
                <div class="topbar-search d-none d-lg-block">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search..." id="globalSearch">
                </div>
                
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="topbar-icon" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="badge-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="dropdown-header">
                            <span>Notifications</span>
                            <?php if ($notifCount > 0): ?>
                            <a href="#" class="text-primary fs-sm" id="markAllRead">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div id="notificationList">
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fs-4 d-block mb-2"></i>
                                <small>No new notifications</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <div class="user-dropdown" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                        </div>
                        <div class="user-info d-none d-md-block">
                            <div class="name"><?php echo sanitize($currentUser['name']); ?></div>
                            <div class="role"><?php echo sanitize($currentUser['role']); ?></div>
                        </div>
                        <i class="bi bi-chevron-down fs-sm text-muted d-none d-md-block"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo url('modules/settings/profile.php'); ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo url('modules/settings/index.php'); ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo url('logout.php'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <?php if (!empty($flash['message'])): ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show mx-4 mt-3" role="alert">
            <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo sanitize($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="page-content">

