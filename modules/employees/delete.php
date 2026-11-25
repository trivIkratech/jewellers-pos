<?php
/**
 * Delete Employee
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAdmin();

$empId = (int)($_GET['id'] ?? 0);
if ($empId <= 1) {
    redirect(url('modules/employees/index.php'), 'Primary admin employee cannot be deleted.', 'danger');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT e.*, u.id as user_id FROM employees e LEFT JOIN users u ON e.id = u.employee_id WHERE e.id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    redirect(url('modules/employees/index.php'), 'Employee not found.', 'danger');
}

$pdo->beginTransaction();
try {
    // Delete linked user login if exists
    if ($emp['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$emp['user_id']]);
    }

    // Delete attendance records
    $pdo->prepare("DELETE FROM attendance WHERE employee_id = ?")->execute([$empId]);

    // Delete employee
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$empId]);

    logAudit(getCurrentUserId(), 'delete', 'employees', $empId, ['name' => $emp['first_name'] . ' ' . $emp['last_name']]);

    $pdo->commit();
    redirect(url('modules/employees/index.php'), "Employee {$emp['first_name']} deleted successfully.");
} catch (Exception $e) {
    $pdo->rollBack();
    redirect(url('modules/employees/index.php'), 'Failed to delete employee: ' . $e->getMessage(), 'danger');
}

