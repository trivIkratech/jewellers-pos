<?php
/**
 * Employee Management - Directory
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Employee Management';
$pdo = getDBConnection();

$today = date('Y-m-d');

// Fetch employees with linked user accounts
$sql = "SELECT e.*, u.username, u.role as user_role, u.last_login, a.status as today_attendance 
        FROM employees e 
        LEFT JOIN users u ON e.id = u.employee_id 
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ? 
        ORDER BY e.status ASC, e.first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$today]);
$employees = $stmt->fetchAll();

// Statistics
$totalEmp = count($employees);
$activeEmp = 0;
$todayPresent = 0;
$totalSalary = 0.00;

foreach ($employees as $e) {
    if ($e['status'] === 'active') {
        $activeEmp++;
        $totalSalary += (float)$e['salary'];
    }
    if ($e['today_attendance'] === 'present') {
        $todayPresent++;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Metrics -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $totalEmp; ?></h3>
                <p>Total Staff Members</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-details">
                <h3 class="text-success"><?php echo $activeEmp; ?> Active</h3>
                <p>Active Staff</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $todayPresent; ?> Present</h3>
                <p>Marked Today (<?php echo date('d M'); ?>)</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo formatCurrency($totalSalary); ?></h3>
                <p>Monthly Payroll</p>
            </div>
        </div>
    </div>
</div>

<!-- Controls -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 fw-bold text-dark">Staff & Users Directory</h5>
                <small class="text-muted">Manage staff credentials, role-based access, and salary details</small>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo url('modules/employees/attendance.php'); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-calendar-check me-1"></i> Today's Attendance
                </a>
                <a href="<?php echo url('modules/employees/add.php'); ?>" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Employee
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Employee Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-container">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation & Dept</th>
                        <th>Contact</th>
                        <th>Role & Access</th>
                        <th>Monthly Salary</th>
                        <th>Today's Attendance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            No employees found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees as $e): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width: 36px; height: 36px;">
                                    <?php echo strtoupper(substr($e['first_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong class="text-dark d-block"><?php echo sanitize($e['first_name'] . ' ' . $e['last_name']); ?></strong>
                                    <?php if ($e['username']): ?>
                                    <small class="text-muted"><i class="bi bi-person-badge"></i> @<?php echo sanitize($e['username']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?php echo sanitize($e['designation'] ?: 'Staff'); ?></div>
                            <small class="text-muted"><?php echo sanitize($e['department'] ?: 'Showroom'); ?></small>
                        </td>
                        <td>
                            <div class="text-dark"><i class="bi bi-telephone text-muted me-1"></i><?php echo sanitize($e['phone']); ?></div>
                            <?php if ($e['email']): ?>
                            <small class="text-muted"><?php echo sanitize($e['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($e['user_role'] === 'admin'): ?>
                            <span class="badge bg-danger"><i class="bi bi-shield-lock-fill me-1"></i>Admin</span>
                            <?php elseif ($e['user_role'] === 'employee'): ?>
                            <span class="badge bg-primary"><i class="bi bi-person-fill me-1"></i>Employee</span>
                            <?php else: ?>
                            <span class="badge bg-light text-muted border">No Login</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-dark"><?php echo formatCurrency($e['salary']); ?></strong>
                        </td>
                        <td>
                            <?php if ($e['today_attendance'] === 'present'): ?>
                            <span class="badge bg-success">Present</span>
                            <?php elseif ($e['today_attendance'] === 'absent'): ?>
                            <span class="badge bg-danger">Absent</span>
                            <?php elseif ($e['today_attendance'] === 'half_day'): ?>
                            <span class="badge bg-warning text-dark">Half Day</span>
                            <?php elseif ($e['today_attendance'] === 'leave'): ?>
                            <span class="badge bg-secondary">On Leave</span>
                            <?php else: ?>
                            <span class="badge bg-light text-muted border">Not Marked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $e['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst($e['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="<?php echo url('modules/employees/edit.php?id=' . $e['id']); ?>" class="btn btn-sm btn-outline-secondary" title="Edit Employee">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($e['id'] != 1): // Protect main admin ?>
                                <a href="<?php echo url('modules/employees/delete.php?id=' . $e['id']); ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Remove employee <?php echo addslashes($e['first_name']); ?>?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
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

