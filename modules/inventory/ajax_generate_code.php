<?php
/**
 * AJAX Endpoint: Generate next available product code & barcode
 * Jewellery ERP Management System
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$metal = strtolower(trim($_GET['metal'] ?? 'gold'));
$subcat = trim($_GET['subcat'] ?? '');

$prefixes = [
    'gold' => 'GLD',
    'silver' => 'SLV',
    'diamond' => 'DMD',
    'platinum' => 'PLT',
    'other' => 'OTH'
];

$prefix = $prefixes[$metal] ?? 'GLD';

// Optional: if subcat name has specific prefix (e.g. Ring -> R, Necklace -> N)
if (!empty($subcat)) {
    $firstLetter = strtoupper(substr($subcat, 0, 1));
    $metalLetter = strtoupper(substr($metal, 0, 1));
    // E.g. GR-001 for Gold Ring, SR-001 for Silver Ring, DR-001 for Diamond Ring
    $catPrefix = $metalLetter . $firstLetter;
} else {
    $catPrefix = $prefix;
}

// Find existing codes matching prefix
// Check both short prefix (e.g. GR-001 or GLD-001)
$stmt = $pdo->prepare("SELECT product_code FROM products WHERE product_code LIKE ? ORDER BY id DESC LIMIT 50");
$stmt->execute([$prefix . '-%']);
$codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Also check catPrefix
if ($catPrefix !== $prefix) {
    $stmt2 = $pdo->prepare("SELECT product_code FROM products WHERE product_code LIKE ? ORDER BY id DESC LIMIT 50");
    $stmt2->execute([$catPrefix . '-%']);
    $catCodes = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    $allCodes = array_merge($codes, $catCodes);
} else {
    $allCodes = $codes;
}

// Extract max sequence number
$maxSeq = 0;
foreach ($allCodes as $c) {
    if (preg_match('/-(\d+)$/', $c, $m)) {
        $num = (int)$m[1];
        if ($num > $maxSeq) {
            $maxSeq = $num;
        }
    }
}

$nextSeq = $maxSeq + 1;
$nextCode = sprintf('%s-%03d', $prefix, $nextSeq);

// Barcode: 890 (India) + 2-digit metal code + 5-digit sequence
$metalNum = match($metal) {
    'gold' => '10',
    'silver' => '20',
    'diamond' => '30',
    'platinum' => '40',
    default => '50'
};
$barcode = sprintf('890%s%05d', $metalNum, $nextSeq);

echo json_encode([
    'success' => true,
    'metal' => $metal,
    'product_code' => $nextCode,
    'barcode' => $barcode,
    'next_seq' => $nextSeq
]);

