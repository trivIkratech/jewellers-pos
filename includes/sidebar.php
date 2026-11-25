<?php
/**
 * Sidebar Component
 * Jewellery ERP Management System
 */

// Determine current page for active state
$currentPage = basename(dirname($_SERVER['SCRIPT_NAME']));
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div style="width:36px;height:36px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-gem" style="font-size:20px;color:#F4B740;"></i>
        </div>
        <div>
            <h4><?php echo getSetting('shop_name', 'Jewellery ERP'); ?></h4>
            <span>Management System</span>
        </div>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-section">Main Menu</li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/dashboard/index.php'); ?>" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/billing/index.php'); ?>" class="nav-link <?php echo $currentPage === 'billing' ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i>
                <span>Billing / POS</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/inventory/index.php'); ?>" class="nav-link <?php echo $currentPage === 'inventory' ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory</span>
                <?php
                // Low stock count
                try {
                    $pdo = getDBConnection();
                    $lowStockStmt = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1");
                    $lowStock = $lowStockStmt->fetch()['cnt'] ?? 0;
                    if ($lowStock > 0):
                ?>
                <span class="badge bg-danger"><?php echo $lowStock; ?></span>
                <?php endif; } catch(Exception $e) {} ?>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/customers/index.php'); ?>" class="nav-link <?php echo $currentPage === 'customers' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
        </li>
        
        <?php if (isAdmin()): ?>
        <li class="nav-section">Management</li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/employees/index.php'); ?>" class="nav-link <?php echo $currentPage === 'employees' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i>
                <span>Employees</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/finance/index.php'); ?>" class="nav-link <?php echo $currentPage === 'finance' ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i>
                <span>Revenue & Expenses</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-section">Records</li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/bill-history/index.php'); ?>" class="nav-link <?php echo $currentPage === 'bill-history' && empty($_GET['type']) ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i>
                <span>All Bills</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo url('modules/bill-history/index.php?type=gst'); ?>" class="nav-link <?php echo $currentPage === 'bill-history' && ($_GET['type'] ?? '') === 'gst' ? 'active' : ''; ?>" style="padding-left: 28px; font-size: 13px;">
                <i class="bi bi-patch-check text-success"></i>
                <span>GST Bills</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo url('modules/bill-history/index.php?type=non_gst'); ?>" class="nav-link <?php echo $currentPage === 'bill-history' && ($_GET['type'] ?? '') === 'non_gst' ? 'active' : ''; ?>" style="padding-left: 28px; font-size: 13px;">
                <i class="bi bi-file-earmark-text text-secondary"></i>
                <span>Non-GST Bills</span>
            </a>
        </li>
        
        <?php if (isAdmin()): ?>
        <li class="nav-section">System</li>
        
        <li class="nav-item">
            <a href="<?php echo url('modules/settings/index.php'); ?>" class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <!-- Sidebar Footer -->
    <div style="padding:16px 20px;border-top:1px solid rgba(255,255,255,0.1);margin-top:auto;">
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.5);">
            <i class="bi bi-shield-check"></i>
            <span>v<?php echo APP_VERSION; ?></span>
        </div>
    </div>
</aside>

