<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/admin-session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    echo json_encode(['success'=>false, 'message'=>'Not authorized']);
    exit;
}

$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$amount = isset($_POST['amount']) ? floatval(str_replace(',', '', $_POST['amount'])) : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$feeId = isset($_POST['fee_id']) ? (intval($_POST['fee_id']) ?: null) : null;
$paymentDate = isset($_POST['date']) ? trim($_POST['date']) : null;

if (!$studentId || !$amount || $amount <= 0) {
    echo json_encode(['success'=>false, 'message'=>'Invalid student or amount']);
    exit;
}

// check payments table exists
$res = $conn->query("SHOW TABLES LIKE 'payments'");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success'=>false, 'message'=>'Payments table not found']);
    exit;
}

// check if payments table has columns fee_id, description, payment_date
$hasFeeId = false;
$hasDescription = false;
$hasPaymentDate = false;
$colRes = $conn->query("SHOW COLUMNS FROM payments");
if ($colRes) {
    $cols = [];
    while ($row = $colRes->fetch_assoc()) $cols[] = $row['Field'];
    $hasFeeId = in_array('fee_id', $cols);
    $hasDescription = in_array('description', $cols) || in_array('note', $cols);
    $hasPaymentDate = in_array('payment_date', $cols) || in_array('created_at', $cols);
}

// Build insert statement dynamically
$fields = ['student_id','amount'];
$placeholders = ['?','?'];
$types = 'id';
$vals = [$studentId, $amount];

if ($hasFeeId) { $fields[] = 'fee_id'; $placeholders[] = '?'; $types .= 'i'; $vals[] = $feeId; }
if ($hasDescription) { $fields[] = 'description'; $placeholders[] = '?'; $types .= 's'; $vals[] = $description; }
if ($hasPaymentDate && $paymentDate) { $fields[] = 'payment_date'; $placeholders[] = '?'; $types .= 's'; $vals[] = $paymentDate; }

$sql = 'INSERT INTO payments (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success'=>false, 'message'=>'Prepare failed: ' . $conn->error]);
    exit;
}

// bind types
// Build types string: 'i' for int, 'd' for double, 's' for string
$paramTypes = '';
foreach ($vals as $v) {
    if (is_int($v)) $paramTypes .= 'i';
    else if (is_float($v) || is_double($v)) $paramTypes .= 'd';
    else $paramTypes .= 's';
}
$bindNames = array_merge([ $paramTypes ], $vals);
// Use call_user_func_array for bind_param
$tmp = [];
foreach ($bindNames as $k => $v) $tmp[$k] = &$bindNames[$k];
call_user_func_array([$stmt, 'bind_param'], $tmp);

$ok = $stmt->execute();
if (!$ok) {
    echo json_encode(['success'=>false, 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$insertedId = $stmt->insert_id;
$stmt->close();

// Optional: compute new total and return it
$totalRes = $conn->prepare("SELECT IFNULL(SUM(amount),0) as total FROM payments p WHERE p.student_id = ? " . ($hasFeeId ? "AND (p.fee_id IS NULL OR p.fee_id NOT IN (SELECT id FROM fees WHERE category IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')))" : ""));
if ($totalRes) {
    $totalRes->bind_param('i', $studentId);
    $totalRes->execute();
    $totalRes->bind_result($newTotal);
    $totalRes->fetch();
    $totalRes->close();
} else {
    $newTotal = null;
}

echo json_encode(['success'=>true, 'payment_id' => $insertedId, 'new_total' => (float)$newTotal]);
exit;
