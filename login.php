<?php
/**
 * Login Page
 * Jewellery ERP Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . url('modules/dashboard/index.php'));
    exit;
}

$error = '';
$flash = getFlashMessage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // CSRF check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (authenticateUser($username, $password)) {
            header('Location: ' . url('modules/dashboard/index.php'));
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jewellery ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #213C72;
            --primary-light: #2d4f94;
            --secondary: #F4F6F9;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--secondary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 40px;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-brand .icon {
            width: 64px;
            height: 64px;
            background: var(--primary);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .login-brand .icon i {
            font-size: 32px;
            color: #F4B740;
        }
        .login-brand h2 {
            font-size: 22px;
            font-weight: 700;
            color: #263238;
            margin: 0;
        }
        .login-brand p {
            font-size: 14px;
            color: #6B7280;
            margin: 4px 0 0;
        }
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #263238;
        }
        .form-control {
            padding: 10px 14px;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(33,60,114,0.1);
        }
        .btn-login {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: var(--primary-light);
            color: #fff;
        }
        .input-group-text {
            background: var(--secondary);
            border: 1px solid #E5E7EB;
            border-right: none;
            color: #6B7280;
        }
        .input-group .form-control {
            border-left: none;
        }
        .input-group .form-control:focus {
            border-left: none;
        }
        .alert {
            font-size: 13px;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #9CA3AF;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-brand">
                <div class="icon">
                    <i class="bi bi-gem"></i>
                </div>
                <h2>Jewellery ERP</h2>
                <p>Sign in to your account</p>
            </div>

            <?php if (!empty($flash['message'])): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> mb-3">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-3">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo sanitize($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Enter username or email" required autofocus
                               value="<?php echo sanitize($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required id="passwordField">
                        <button type="button" class="input-group-text" style="cursor:pointer;border-left:none;border-right:1px solid #E5E7EB;" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="login-footer">
                <p class="mb-1">Default Login: <strong>admin</strong> / <strong>admin123</strong></p>
                <p>&copy; <?php echo date('Y'); ?> Jewellery ERP. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const field = document.getElementById('passwordField');
        const icon = document.getElementById('toggleIcon');
        if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            field.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
    </script>
</body>
</html>

