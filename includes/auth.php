<?php
/**
 * Authentication Helper
 * Jewellery ERP Management System
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin() {
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive >= SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
            $_SESSION['flash_message'] = 'Your session has expired. Please login again.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: ' . url('login.php'));
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
    
    if (!isLoggedIn()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        $_SESSION['flash_message'] = 'Access denied. Admin privileges required.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . url('modules/dashboard/index.php'));
        exit;
    }
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
    ];
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name 
                           FROM users u 
                           LEFT JOIN employees e ON u.employee_id = e.id 
                           WHERE (u.username = ? OR e.email = ?) AND u.is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] ? $user['first_name'] . ' ' . $user['last_name'] : $user['username'];
        $_SESSION['last_activity'] = time();
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Log the login
        logAudit($user['id'], 'login', 'auth');
        
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], 'logout', 'auth');
    }
    session_unset();
    session_destroy();
}

/**
 * Check module access permission
 */
function hasModuleAccess($module) {
    if (isAdmin()) return true;
    
    $permissions = [
        'employee' => ['dashboard', 'billing', 'customers', 'bill-history', 'inventory_view'],
    ];
    
    $role = $_SESSION['user_role'] ?? '';
    return in_array($module, $permissions[$role] ?? []);
}

