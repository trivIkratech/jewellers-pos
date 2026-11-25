<?php
/**
 * System Audit Trail Logs
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Security Audit Logs';
$pdo = getDBConnection();

$moduleFilter = trim($_GET['module'] ?? '');

$sql = "SELECT al.*, u.username 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($moduleFilter !== '') {
    $sql .= " AND al.module = ?";
    $params[] = $moduleFilter;
}

$sql .= " ORDER BY al.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Security Audit Trail</h4>
        <p class="text-muted fs-sm mb-0">Immutable tracking of billing events, cancellations, stock movements, and user authentications</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/settings/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-1"></i> Settings
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="module" onchange="this.form.submit()">
                    <option value="">All Functional Modules</option>
                    <option value="invoices" <?php echo $moduleFilter === 'invoices' ? 'selected' : ''; ?>>Invoices & Billing</option>
                    <option value="inventory" <?php echo $moduleFilter === 'inventory' ? 'selected' : ''; ?>>Inventory & Stock</option>
                    <option value="customers" <?php echo $moduleFilter === 'customers' ? 'selected' : ''; ?>>Customers</option>
                    <option value="expenses" <?php echo $moduleFilter === 'expenses' ? 'selected' : ''; ?>>Expenses</option>
                    <option value="auth" <?php echo $moduleFilter === 'auth' ? 'selected' : ''; ?>>User Logins & Auth</option>
                    <option value="settings" <?php echo $moduleFilter === 'settings' ? 'selected' : ''; ?>>Settings & Rates</option>
                </select>
            </div>
            <div class="col-md-2">
                <?php if ($moduleFilter): ?>
                <a href="<?php echo url('modules/settings/audit_logs.php'); ?>" class="btn btn-sm btn-outline-secondary">
                    Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Record ID</th>
                        <th>Details / Payload</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            No audit log records recorded yet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="text-muted fs-sm text-nowrap">
                            <?php echo date('d M Y, h:i:s A', strtotime($l['created_at'])); ?>
                        </td>
                        <td>
                            <strong class="text-dark"><i class="bi bi-person me-1"></i><?php echo sanitize($l['username'] ?: 'System'); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?php echo sanitize($l['module']); ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary-light text-primary"><?php echo sanitize($l['action']); ?></span>
                        </td>
                        <td class="text-muted fs-sm">
                            <?php echo $l['record_id'] ? '#' . $l['record_id'] : '-'; ?>
                        </td>
                        <td class="fs-xs font-monospace text-muted text-truncate" style="max-width: 250px;">
                            <?php echo sanitize($l['new_values'] ?: ($l['old_values'] ?: '-')); ?>
                        </td>
                        <td class="text-muted fs-xs">
                            <?php echo sanitize($l['ip_address'] ?: '127.0.0.1'); ?>
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

