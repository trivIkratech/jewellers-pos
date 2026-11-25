<?php
/**
 * Index - Redirect to Dashboard or Login
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . url('modules/dashboard/index.php'));
} else {
    header('Location: ' . url('login.php'));
}
exit;

