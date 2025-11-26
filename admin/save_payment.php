<?php
// Minimal JSON API to save a payment and return updated totals
// Requires admin session and DB connection; be conservative and validate inputs.

// Session + auth
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$studentId = isset($input['student_id']) ? (int)$input['student_id'] : null;
$amount = isset($input['amount']) ? (float)$input['amount'] : null;
$paymentDate = isset($input['payment_date']) ? $input['payment_date'] : date('Y-m-d');
$paymentFor = isset($input['payment_for']) ? $input['payment_for'] : null;

// Validate inputs
if (!$studentId || $amount === null || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student ID or amount']);
    exit;
}

try {
    // Detect payment table columns
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM payments");
    while ($col = $result->fetch_assoc()) {
        $columns[$col['Field']] = true;
    }

    // Build insert - always include note/notes column if it exists
    $insertCols = ['student_id', 'amount'];
    $insertVals = [$studentId, $amount];
    $insertParams = ['i', 'd'];

    if (isset($columns['date'])) {
        $insertCols[] = 'date';
        $insertVals[] = $paymentDate;
        $insertParams[] = 's';
    } elseif (isset($columns['payment_date'])) {
        $insertCols[] = 'payment_date';
        $insertVals[] = $paymentDate;
        $insertParams[] = 's';
    }

    // Add note/notes column with the payment_for value
    if (isset($columns['note'])) {
        $insertCols[] = 'note';
        $insertVals[] = $paymentFor;
        $insertParams[] = 's';
    } elseif (isset($columns['notes'])) {
        $insertCols[] = 'notes';
        $insertVals[] = $paymentFor;
        $insertParams[] = 's';
    }

    // Build and execute insert
    $colList = implode(', ', $insertCols);
    $valPlaceholders = implode(', ', array_fill(0, count($insertVals), '?'));
    $sql = "INSERT INTO payments ({$colList}) VALUES ({$valPlaceholders})";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $paramTypes = implode('', $insertParams);
    $stmt->bind_param($paramTypes, ...$insertVals);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // capture inserted id
    $insertId = $stmt->insert_id;
    $stmt->close();

    // Normalize response data
    $noteVal = ($paymentFor === null) ? '' : (string)$paymentFor;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'data' => [
            'id' => $insertId,
            'student_id' => $studentId,
            'amount' => $amount,
            'date' => $paymentDate,
            'payment_for' => $noteVal,
            'note' => $noteVal
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
