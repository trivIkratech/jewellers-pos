<?php
/**
 * Database Configuration
 * Jewellery ERP Management System
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jewellery_erp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'Jewellery ERP');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/POS-Jewellery');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// CSRF Token name
define('CSRF_TOKEN_NAME', 'csrf_token');

/**
 * Helper to build application relative or absolute URLs
 * Works seamlessly whether hosted at root (e.g. php -S localhost:8000)
 * or inside a subfolder (e.g. /POS-Jewellery/)
 */
function appUrl($path = '') {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = '';
    if (strpos($scriptName, '/POS-Jewellery') === 0) {
        $base = '/POS-Jewellery';
    }
    $path = ltrim($path, '/');
    return $base . ($path ? '/' . $path : '');
}

function url($path = '') {
    return appUrl($path);
}

/**
 * Get PDO database connection
 * Uses singleton pattern
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $hosts = [DB_HOST, '127.0.0.1', 'localhost'];
        $lastException = null;

        foreach (array_unique($hosts) as $h) {
            try {
                $dsn = "mysql:host=" . $h . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                break;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        if ($pdo === null) {
            error_log("Database connection failed: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
            die('<div style="text-align:center;padding:50px;font-family:sans-serif;">
                <h2>Database Connection Error</h2>
                <p>Unable to connect to the database. Please check your configuration.</p>
                <p style="color:#888;font-size:12px;">' . htmlspecialchars($lastException ? $lastException->getMessage() : '') . '</p>
                </div>');
        }
    }
    
    return $pdo;
}

/**
 * Get a setting value from the database
 */
function getSetting($key, $default = '') {
    static $cache = [];
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $value = $result ? $result['setting_value'] : $default;
        $cache[$key] = $value;
        return $value;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Update a setting value
 */
function updateSetting($key, $value) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log an audit action
 */
function logAudit($userId, $action, $module, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, old_values, new_values, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $module,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Format weight
 */
function formatWeight($weight) {
    return number_format((float)$weight, 3) . ' g';
}

/**
 * Convert number to words (Indian numbering system)
 */
function numberToWords($number) {
    $number = round($number, 2);
    $decimal = round(($number - floor($number)) * 100);
    $number = floor($number);
    
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    if ($number == 0) {
        $words = 'Zero';
    } else {
        $words = '';
        
        // Crores
        if ($number >= 10000000) {
            $crores = floor($number / 10000000);
            $words .= numberToWordsHelper($crores, $ones, $tens) . ' Crore ';
            $number %= 10000000;
        }
        
        // Lakhs
        if ($number >= 100000) {
            $lakhs = floor($number / 100000);
            $words .= numberToWordsHelper($lakhs, $ones, $tens) . ' Lakh ';
            $number %= 100000;
        }
        
        // Thousands
        if ($number >= 1000) {
            $thousands = floor($number / 1000);
            $words .= numberToWordsHelper($thousands, $ones, $tens) . ' Thousand ';
            $number %= 1000;
        }
        
        // Hundreds
        if ($number >= 100) {
            $words .= $ones[floor($number / 100)] . ' Hundred ';
            $number %= 100;
        }
        
        if ($number > 0) {
            if ($words != '') $words .= 'and ';
            if ($number < 20) {
                $words .= $ones[$number];
            } else {
                $words .= $tens[floor($number / 10)];
                if ($number % 10 > 0) {
                    $words .= ' ' . $ones[$number % 10];
                }
            }
        }
    }
    
    $words = trim($words) . ' Rupees';
    if ($decimal > 0) {
        $words .= ' and ';
        if ($decimal < 20) {
            $words .= $ones[$decimal];
        } else {
            $words .= $tens[floor($decimal / 10)];
            if ($decimal % 10 > 0) {
                $words .= ' ' . $ones[$decimal % 10];
            }
        }
        $words .= ' Paise';
    }
    $words .= ' Only';
    
    return $words;
}

function numberToWordsHelper($number, $ones, $tens) {
    $words = '';
    if ($number >= 100) {
        $words .= $ones[floor($number / 100)] . ' Hundred ';
        $number %= 100;
    }
    if ($number >= 20) {
        $words .= $tens[floor($number / 10)];
        if ($number % 10 > 0) {
            $words .= ' ' . $ones[$number % 10];
        }
    } elseif ($number > 0) {
        $words .= $ones[$number];
    }
    return trim($words);
}

/**
 * Generate next invoice number (supports separate series for GST vs Non-GST/Estimate bills)
 */
function generateInvoiceNumber($type = 'tax_invoice') {
    $pdo = getDBConnection();
    if ($type === 'estimate') {
        $prefix = getSetting('estimate_prefix', 'EST');
        $defaultStart = (int) getSetting('estimate_start_number', '1000');
    } else {
        $prefix = getSetting('invoice_prefix', 'INV');
        $defaultStart = (int) getSetting('invoice_start_number', '1000');
    }
    
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(invoice_number, LENGTH('$prefix') + 2) AS UNSIGNED)) as last_num FROM invoices WHERE invoice_number LIKE '{$prefix}-%'");
    $result = $stmt->fetch();
    $lastNum = $result['last_num'] ?? $defaultStart;
    $nextNum = $lastNum + 1;
    
    return $prefix . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

/**
 * Get current metal rates
 */
function getCurrentMetalRates() {
    $pdo = getDBConnection();
    $rates = [];
    $metals = ['gold_24k', 'gold_22k', 'gold_18k', 'silver_999', 'silver_925', 'platinum'];
    
    foreach ($metals as $metal) {
        $stmt = $pdo->prepare("SELECT rate_per_gram FROM metal_rates WHERE metal_type = ? ORDER BY effective_date DESC, id DESC LIMIT 1");
        $stmt->execute([$metal]);
        $result = $stmt->fetch();
        $rates[$metal] = $result ? (float)$result['rate_per_gram'] : 0;
    }
    
    return $rates;
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect with flash message
 */
function redirect($url, $message = '', $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? '';
    $type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return ['message' => $message, 'type' => $type];
}

