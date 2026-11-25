<?php
/**
 * User Profile & Password Change
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

$pageTitle = 'My Profile';
$pdo = getDBConnection();
$currentUserId = getCurrentUserId();

$error = '';
$success = '';

// Fetch user & employee record
$stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name, e.email, e.phone, e.designation, e.department 
                       FROM users u 
                       LEFT JOIN employees e ON u.employee_id = e.id 
                       WHERE u.id = ?");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid token.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Check if updating password
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $error = 'Please enter your current password to set a new password.';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password and confirmation do not match.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $currentUserId]);
                logAudit($currentUserId, 'change_password', 'users', $currentUserId);
                $success = 'Password changed successfully!';
            }
        }
    }
}

$csrfToken = generateCSRFToken();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1 fw-bold text-dark">My Profile & Security</h4>
                <p class="text-muted fs-sm mb-0">Manage your user account credentials and security settings</p>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle me-2"></i> <?php echo sanitize($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- User Information Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar" style="width: 60px; height: 60px; font-size: 26px;">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><?php echo sanitize($user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username']); ?></h5>
                        <div class="text-muted fs-sm mb-1">@<?php echo sanitize($user['username']); ?> &bull; <span class="badge bg-primary"><?php echo strtoupper($user['role']); ?></span></div>
                        <div class="text-muted fs-xs">
                            Designation: <?php echo sanitize($user['designation'] ?: 'Staff'); ?> &bull; Last Login: <?php echo $user['last_login'] ? date('d M Y, h:i A', strtotime($user['last_login'])) : 'Today'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-key me-2 text-primary"></i>Change Password</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" placeholder="Enter current password">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" placeholder="Min. 6 characters">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Repeat new password">
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-shield-lock me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

