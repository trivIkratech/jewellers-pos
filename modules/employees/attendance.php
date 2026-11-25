<?php
/**
 * Employee Attendance Management
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Attendance Tracker';
$pdo = getDBConnection();
$msg = '';

$selectedDate = !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle attendance save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $attendanceData = $_POST['attendance'] ?? [];
        $attDate = $_POST['attendance_date'] ?? $selectedDate;

        foreach ($attendanceData as $empId => $att) {
            $status = in_array($att['status'] ?? '', ['present', 'absent', 'half_day', 'leave']) ? $att['status'] : 'present';
            $checkIn = !empty($att['check_in']) ? $att['check_in'] : null;
            $checkOut = !empty($att['check_out']) ? $att['check_out'] : null;
            $notes = trim($att['notes'] ?? '');

            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, notes) 
                                   VALUES (?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = ?, check_in = ?, check_out = ?, notes = ?");
            $stmt->execute([$empId, $attDate, $status, $checkIn, $checkOut, $notes, $status, $checkIn, $checkOut, $notes]);
        }

        logAudit(getCurrentUserId(), 'save_attendance', 'employees', null, null, ['date' => $attDate]);
        redirect(url('modules/employees/attendance.php?date=' . $attDate), 'Attendance records updated successfully!');
    }
}

// Fetch active employees with their attendance for the selected date
$sql = "SELECT e.id as emp_id, e.first_name, e.last_name, e.designation, e.department, 
               a.id as attendance_id, a.status, a.check_in, a.check_out, a.notes 
        FROM employees e 
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ? 
        WHERE e.status = 'active' 
        ORDER BY e.first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedDate]);
$staffAttendance = $stmt->fetchAll();

// Daily counts
$counts = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'leave' => 0, 'not_marked' => 0];
foreach ($staffAttendance as $s) {
    if ($s['status']) {
        $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
    } else {
        $counts['not_marked']++;
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Staff Attendance Register</h4>
        <p class="text-muted fs-sm mb-0">Record and monitor daily staff punches, leaves, and working hours</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('modules/employees/index.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-people me-1"></i> Staff Directory
        </a>
    </div>
</div>

<!-- Date Selector & Day Stats -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <form method="GET" action="" class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 fw-semibold text-nowrap">Select Date:</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $selectedDate; ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="col-md-8">
                <div class="d-flex justify-content-md-end align-items-center flex-wrap gap-3">
                    <span class="badge bg-success-light text-success px-3 py-2 fs-6">
                        <i class="bi bi-check-circle me-1"></i> Present: <?php echo $counts['present']; ?>
                    </span>
                    <span class="badge bg-danger-light text-danger px-3 py-2 fs-6">
                        <i class="bi bi-x-circle me-1"></i> Absent: <?php echo $counts['absent']; ?>
                    </span>
                    <span class="badge bg-warning-light text-warning px-3 py-2 fs-6">
                        <i class="bi bi-clock-history me-1"></i> Half Day: <?php echo $counts['half_day']; ?>
                    </span>
                    <span class="badge bg-light text-muted border px-3 py-2 fs-6">
                        <i class="bi bi-hourglass me-1"></i> Unmarked: <?php echo $counts['not_marked']; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="attendance_date" value="<?php echo $selectedDate; ?>">

    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-container">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Designation</th>
                            <th style="min-width: 280px;">Status</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffAttendance as $sa): 
                            $currStatus = $sa['status'] ?? 'present';
                            $id = $sa['emp_id'];
                        ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?php echo sanitize($sa['first_name'] . ' ' . $sa['last_name']); ?></strong>
                                <small class="text-muted"><?php echo sanitize($sa['department']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo sanitize($sa['designation']); ?></span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm w-100" role="group">
                                    <input type="radio" class="btn-check" name="attendance[<?php echo $id; ?>][status]" id="pres_<?php echo $id; ?>" value="present" <?php echo $currStatus === 'present' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-success" for="pres_<?php echo $id; ?>">Present</label>

                                    <input type="radio" class="btn-check" name="attendance[<?php echo $id; ?>][status]" id="half_<?php echo $id; ?>" value="half_day" <?php echo $currStatus === 'half_day' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-warning" for="half_<?php echo $id; ?>">Half Day</label>

                                    <input type="radio" class="btn-check" name="attendance[<?php echo $id; ?>][status]" id="abs_<?php echo $id; ?>" value="absent" <?php echo $currStatus === 'absent' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-danger" for="abs_<?php echo $id; ?>">Absent</label>

                                    <input type="radio" class="btn-check" name="attendance[<?php echo $id; ?>][status]" id="lev_<?php echo $id; ?>" value="leave" <?php echo $currStatus === 'leave' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-secondary" for="lev_<?php echo $id; ?>">Leave</label>
                                </div>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" name="attendance[<?php echo $id; ?>][check_in]" value="<?php echo $sa['check_in'] ? substr($sa['check_in'], 0, 5) : '09:30'; ?>" style="max-width: 120px;">
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" name="attendance[<?php echo $id; ?>][check_out]" value="<?php echo $sa['check_out'] ? substr($sa['check_out'], 0, 5) : '19:30'; ?>" style="max-width: 120px;">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="attendance[<?php echo $id; ?>][notes]" placeholder="Optional note" value="<?php echo sanitize($sa['notes'] ?? ''); ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
            <i class="bi bi-save me-1"></i> Save Attendance for <?php echo date('d M Y', strtotime($selectedDate)); ?>
        </button>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

