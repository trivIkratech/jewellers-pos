<?php
/**
 * Database Setup & Installation Wizard
 * Jewellery ERP Management System
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$step = $_GET['step'] ?? '1';
$message = '';
$messageType = 'info';
$installed = false;

// Attempt database connection
$connected = false;
$tablesCount = 0;
$dbError = '';

try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $connected = true;

    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    if ($stmt->fetch()) {
        $pdo->exec("USE `" . DB_NAME . "`");
        $tStmt = $pdo->query("SHOW TABLES");
        $tables = $tStmt->fetchAll(PDO::FETCH_COLUMN);
        $tablesCount = count($tables);
        if ($tablesCount >= 10) {
            $installed = true;
        }
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Action: Run migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    if (!$connected) {
        $message = "Cannot install: Unable to connect to MySQL host (" . DB_HOST . "). Error: " . $dbError;
        $messageType = 'danger';
    } else {
        try {
            // Read SQL file
            $sqlFile = __DIR__ . '/database/jewellery_erp.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("Schema file database/jewellery_erp.sql not found.");
            }

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // Execute SQL script
            $sqlContent = file_get_contents($sqlFile);
            
            // Execute statements
            $pdo->exec($sqlContent);

            $message = "Database `" . DB_NAME . "` installed and initialized successfully with all tables and sample demonstration records!";
            $messageType = 'success';
            $installed = true;

            // Re-count tables
            $tStmt = $pdo->query("SHOW TABLES");
            $tablesCount = count($tStmt->fetchAll(PDO::FETCH_COLUMN));

        } catch (Exception $e) {
            $message = "Installation error: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installation - Jewellery ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #F4F6F9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            width: 100%;
            max-width: 600px;
            padding: 40px;
        }
    </style>
</head>
<body>

    <div class="setup-card">
        <div class="text-center mb-4">
            <div style="width: 64px; height: 64px; background: #213C72; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; color: #F4B740; font-size: 32px; margin-bottom: 16px;">
                <i class="bi bi-gem"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Jewellery ERP System</h3>
            <p class="text-muted fs-sm">Database Setup & Verification Wizard</p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> mb-4">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- System Checks -->
        <div class="card mb-4 bg-light border">
            <div class="card-body">
                <h6 class="fw-bold mb-3">System & Connection Status</h6>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>PHP Version (PHP 8.0+):</span>
                    <span class="badge bg-success"><?php echo PHP_VERSION; ?></span>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>PDO MySQL Driver:</span>
                    <?php if (extension_loaded('pdo_mysql')): ?>
                    <span class="badge bg-success">Available</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Missing</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>MySQL Connection (<?php echo DB_HOST; ?>):</span>
                    <?php if ($connected): ?>
                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>Connected</span>
                    <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-x me-1"></i>Disconnected</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span>Target Database (`<?php echo DB_NAME; ?>`):</span>
                    <?php if ($installed): ?>
                    <span class="badge bg-success"><?php echo $tablesCount; ?> Tables Active</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Not initialized</span>
                    <?php endif; ?>
                </div>

                <?php if ($dbError): ?>
                <div class="mt-2 p-2 bg-white rounded border fs-xs text-danger">
                    <strong>MySQL Notice:</strong> <?php echo sanitize($dbError); ?><br>
                    <small class="text-muted">Ensure your MySQL service is started (e.g. in XAMPP, MAMP, or via `brew services start mysql`).</small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Area -->
        <?php if ($installed): ?>
        <div class="text-center">
            <div class="alert alert-success py-3 mb-4">
                <i class="bi bi-check-circle-fill fs-4 text-success d-block mb-1"></i>
                <strong>Jewellery ERP is Ready to Use!</strong>
                <div class="fs-sm mt-1">All database tables and seed records are active.</div>
            </div>

            <div class="card bg-light border mb-4 text-start p-3 fs-sm">
                <div class="fw-bold mb-1">Default Login Credentials:</div>
                <div class="row g-2">
                    <div class="col-6">
                        <strong>Admin User:</strong><br>
                        Username: <code>admin</code><br>
                        Password: <code>admin123</code>
                    </div>
                    <div class="col-6">
                        <strong>Sales Staff:</strong><br>
                        Username: <code>employee</code><br>
                        Password: <code>password123</code>
                    </div>
                </div>
            </div>

            <a href="<?php echo url('login.php'); ?>" class="btn btn-primary btn-lg w-100 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-2"></i> Launch Application Login
            </a>
        </div>
        <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="install">
            <p class="fs-sm text-muted mb-3 text-center">
                Click below to create the database <code><?php echo DB_NAME; ?></code> and import all 16 tables, metal rates, sample jewellery inventory, suppliers, and default admin user.
            </p>
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm" <?php echo !$connected ? 'disabled' : ''; ?>>
                <i class="bi bi-database-check me-2"></i> Initialize Database & Demo Records
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4 fs-xs text-muted">
            Jewellery ERP Management System &bull; Core PHP 8+ & MySQL
        </div>
    </div>

</body>
</html>

