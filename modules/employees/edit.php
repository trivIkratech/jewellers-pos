<?php
/**
 * Edit Employee
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$empId = (int)($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT e.*, u.id as user_account_id, u.username, u.role as user_role, u.is_active as user_active 
                       FROM employees e 
                       LEFT JOIN users u ON e.id = u.employee_id 
                       WHERE e.id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    redirect(url('modules/employees/index.php'), 'Employee not found.', 'danger');
}

$pageTitle = 'Edit Employee';
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
        $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : $emp['joining_date'];
        $salary = (float)($_POST['salary'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'terminated']) ? $_POST['status'] : 'active';

        $createLogin = !empty($_POST['create_login']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';

        if (empty($firstName) || empty($phone)) {
            $error = 'First name and phone number are required.';
        } else {
            $pdo->beginTransaction();
            try {
                // Update employee
                $upStmt = $pdo->prepare("UPDATE employees SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, address = ?,
                    designation = ?, department = ?, joining_date = ?, salary = ?, status = ? 
                    WHERE id = ?");
                $upStmt->execute([$firstName, $lastName, $email, $phone, $address, $designation, $department, $joiningDate, $salary, $status, $empId]);

                // Manage user account
                if ($emp['user_account_id']) {
                    // Update existing user account
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $uUp = $pdo->prepare("UPDATE users SET role = ?, password = ?, is_active = ? WHERE id = ?");
                        $uUp->execute([$role, $hash, ($status === 'active' ? 1 : 0), $emp['user_account_id']]);
                    } else {
                        $uUp = $pdo->prepare("UPDATE users SET role = ?, is_active = ? WHERE id = ?");
                        $uUp->execute([$role, ($status === 'active' ? 1 : 0), $emp['user_account_id']]);
                    }
                } elseif ($createLogin && $username) {
                    // Create new user account
                    $chkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $chkUser->execute([$username]);
                    if ($chkUser->fetch()) {
                        throw new Exception("Username {$username} is already taken.");
                    }
                    $hash = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
                    $uStmt = $pdo->prepare("INSERT INTO users (username, password, role, employee_id, is_active) VALUES (?, ?, ?, ?, 1)");
                    $uStmt->execute([$username, $hash, $role, $empId]);
                    $newUserId = $pdo->lastInsertId();
                    $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$newUserId, $empId]);
                }

                logAudit(getCurrentUserId(), 'update', 'employees', $empId, ['name' => $emp['first_name']], ['name' => $firstName]);

                $pdo->commit();
                redirect(url('modules/employees/index.php'), "Employee {$firstName} updated successfully!");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
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
                <h4 class="mb-1 fw-bold text-dark">Edit Employee Record</h4>
                <p class="text-muted fs-sm mb-0">Update staff details, designation, salary, or access permissions</p>
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

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person me-2 text-primary"></i>Personal & Contact Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required value="<?php echo sanitize($_POST['first_name'] ?? $emp['first_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo sanitize($_POST['last_name'] ?? $emp['last_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required value="<?php echo sanitize($_POST['phone'] ?? $emp['phone']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?php echo sanitize($_POST['email'] ?? $emp['email']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Residential Address</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo sanitize($_POST['address'] ?? $emp['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-briefcase me-2 text-primary"></i>Designation & Compensation</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Designation <span class="text-danger">*</span></label>
                            <?php $currentDesig = $_POST['designation'] ?? $emp['designation']; ?>
                            <select class="form-select" name="designation" id="designationSelect" required>
                                <option value="Owner" <?php echo strcasecmp($currentDesig, 'Owner') === 0 ? 'selected' : ''; ?>>Owner</option>
                                <option value="Cashier" <?php echo strcasecmp($currentDesig, 'Cashier') === 0 ? 'selected' : ''; ?>>Cashier</option>
                                <option value="Sales Executive" <?php echo (strcasecmp($currentDesig, 'Sales Executive') === 0 || empty($currentDesig)) ? 'selected' : ''; ?>>Sales Executive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="Sales" <?php echo $emp['department'] === 'Sales' ? 'selected' : ''; ?>>Sales & Showroom</option>
                                <option value="Accounts" <?php echo $emp['department'] === 'Accounts' ? 'selected' : ''; ?>>Accounts & Cash</option>
                                <option value="Inventory" <?php echo $emp['department'] === 'Inventory' ? 'selected' : ''; ?>>Inventory & Karigar</option>
                                <option value="Management" <?php echo $emp['department'] === 'Management' ? 'selected' : ''; ?>>Management</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" class="form-control" name="joining_date" value="<?php echo sanitize($_POST['joining_date'] ?? $emp['joining_date']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Salary (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" name="salary" value="<?php echo sanitize($_POST['salary'] ?? $emp['salary']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo $emp['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $emp['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="terminated" <?php echo $emp['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2 text-primary"></i>System Login Account</h6>
                </div>
                <div class="card-body">
                    <?php if ($emp['user_account_id']): ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control bg-light" readonly value="<?php echo sanitize($emp['username']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reset Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="password" id="resetPasswordInput" placeholder="Leave blank to keep unchanged">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('resetPasswordInput', this)" title="Show/Hide Password" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="employee" <?php echo $emp['user_role'] === 'employee' ? 'selected' : ''; ?>>Employee (Staff / Billing)</option>
                                <option value="admin" <?php echo $emp['user_role'] === 'admin' ? 'selected' : ''; ?>>Admin (Full Access)</option>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="create_login" id="toggleLogin" onchange="document.getElementById('loginFields').style.display = this.checked ? 'flex' : 'none';">
                        <label class="form-check-label" for="toggleLogin">Create a Login Account for this Employee</label>
                    </div>
                    <div class="row g-3" id="loginFields" style="display: none;">
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Username">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="password" id="newLoginPassword" placeholder="Enter password">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('newLoginPassword', this)" title="Show/Hide Password" style="border-color: #dee2e6;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="employee">Employee (Staff / Billing)</option>
                                <option value="admin">Admin (Full Access)</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-end mb-5">
                <a href="<?php echo url('modules/employees/index.php'); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Update Employee
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

