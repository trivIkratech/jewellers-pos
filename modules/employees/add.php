<?php
/**
 * Add Employee
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$pageTitle = 'Add Employee';
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $department = trim($_POST['department'] ?? 'Sales');
        $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');
        $salary = (float)($_POST['salary'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'terminated']) ? $_POST['status'] : 'active';

        $createLogin = !empty($_POST['create_login']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';

        if (empty($firstName) || empty($phone)) {
            $error = 'First name and phone number are required.';
        } elseif ($createLogin && (empty($username) || empty($password))) {
            $error = 'Username and password are required to create a login account.';
        } else {
            // If creating login, check unique username
            $usernameExists = false;
            if ($createLogin) {
                $chkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chkUser->execute([$username]);
                if ($chkUser->fetch()) {
                    $usernameExists = true;
                    $error = 'Username "' . sanitize($username) . '" is already taken. Please pick another.';
                }
            }

            if (!$usernameExists) {
                $pdo->beginTransaction();
                try {
                    // 1. Insert employee
                    $stmt = $pdo->prepare("INSERT INTO employees (
                        first_name, last_name, email, phone, address, designation, department, joining_date, salary, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$firstName, $lastName, $email, $phone, $address, $designation, $department, $joiningDate, $salary, $status]);
                    $empId = $pdo->lastInsertId();

                    // 2. Insert user if enabled
                    if ($createLogin) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $uStmt = $pdo->prepare("INSERT INTO users (username, password, role, employee_id, is_active) VALUES (?, ?, ?, ?, 1)");
                        $uStmt->execute([$username, $hash, $role, $empId]);
                        $userId = $pdo->lastInsertId();

                        // Link employee to user
                        $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$userId, $empId]);
                    }

                    logAudit(getCurrentUserId(), 'create', 'employees', $empId, null, ['name' => $firstName . ' ' . $lastName]);

                    $pdo->commit();
                    redirect(url('modules/employees/index.php'), "Employee {$firstName} {$lastName} created successfully!");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Failed to create employee: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">Add New Staff Member</h4>
                <p class="text-muted fs-sm mb-0">Record employee details, salary, designation, and login access credentials</p>
            </div>
            <a href="<?php echo url('modules/employees/index.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Staff List
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- Personal Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Personal & Contact Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required value="<?php echo sanitize($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo sanitize($_POST['last_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required value="<?php echo sanitize($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Residential Address</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo sanitize($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job & Salary Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-briefcase me-2 text-primary"></i>Designation & Compensation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Designation <span class="text-danger">*</span></label>
                            <select class="form-select" name="designation" id="designationSelect" required>
                                <option value="Owner" <?php echo ($_POST['designation'] ?? '') === 'Owner' ? 'selected' : ''; ?>>Owner</option>
                                <option value="Cashier" <?php echo ($_POST['designation'] ?? '') === 'Cashier' ? 'selected' : ''; ?>>Cashier</option>
                                <option value="Sales Executive" <?php echo ($_POST['designation'] ?? '') === 'Sales Executive' || !isset($_POST['designation']) ? 'selected' : ''; ?>>Sales Executive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="Sales">Sales & Showroom</option>
                                <option value="Accounts">Accounts & Cash</option>
                                <option value="Inventory">Inventory & Karigar</option>
                                <option value="Management">Management</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" class="form-control" name="joining_date" value="<?php echo sanitize($_POST['joining_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Salary (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" name="salary" placeholder="0.00" value="<?php echo sanitize($_POST['salary'] ?? '25000.00'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Login Access -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>System Login Account</h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="create_login" id="toggleLogin" <?php echo !empty($_POST['create_login']) ? 'checked' : ''; ?> onchange="document.getElementById('loginFields').style.display = this.checked ? 'flex' : 'none';">
                        <label class="form-check-label" for="toggleLogin">Enable Login for this Employee</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="loginFields" style="display: <?php echo !empty($_POST['create_login']) ? 'flex' : 'none'; ?>;">
                        <div class="col-md-4">
                            <label class="form-label">Login Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="username" placeholder="Enter username" value="<?php echo sanitize($_POST['username'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Login Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="password" id="employeePassword" placeholder="Enter password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('employeePassword', this)" title="Show/Hide Password" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">System Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="systemRoleSelect">
                                <option value="employee" <?php echo ($_POST['role'] ?? '') === 'employee' || !isset($_POST['role']) ? 'selected' : ''; ?>>Employee (Staff / Billing)</option>
                                <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin (Full Access)</option>
                            </select>
                        </div>
                    </div>
                    <div class="fs-xs text-muted mt-2">
                        Employees with login access can create bills and look up jewellery inventory from the shop counters.
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <a href="<?php echo url('modules/employees/index.php'); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Save Employee Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
        btn.setAttribute('title', 'Hide Password');
    } else {
        field.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
        btn.setAttribute('title', 'Show Password');
    }
}

// Auto-sync System Role when Designation changes
document.getElementById('designationSelect')?.addEventListener('change', function() {
    const roleSelect = document.getElementById('systemRoleSelect');
    if (roleSelect) {
        if (this.value === 'Owner') {
            roleSelect.value = 'admin';
        } else {
            roleSelect.value = 'employee';
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

