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

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Not logged in.']);
	exit;
}

$raw = file_get_contents('php://input');
if (!$raw) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request.']);
	exit;
}
$payload = json_decode($raw, true);
if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
	exit;
}

$student_id = isset($payload['student_id']) ? intval($payload['student_id']) : 0;
$amount = isset($payload['amount']) ? floatval($payload['amount']) : 0.0;
if ($student_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid student id.']);
	exit;
}
if ($amount <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
	exit;
}

// Utilities to detect schema and compute totals (reused logic)
function tableExists($conn, $table) {
	$table = $conn->real_escape_string($table);
	$res = $conn->query("SHOW TABLES LIKE '{$table}'");
	return ($res && $res->num_rows > 0);
}
function columnExists($conn, $table, $column) {
	$table = $conn->real_escape_string($table);
	$column = $conn->real_escape_string($column);
	$res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
	return ($res && $res->num_rows > 0);
}

$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');
$paymentsHasStudentId = $paymentsExists && columnExists($conn, 'payments', 'student_id');
$paymentsHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');

if (!$paymentsExists) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'The payments table is missing; cannot save payment.']);
	exit;
}

// We will attempt to attach to a fee if fee_id exists and there is a fee for this student (not in excluded categories)
$fee_id_to_use = null;
if ($paymentsHasFeeId && $feesExists) {
	$excl = ['Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'];
	$placeholders = implode(',', array_fill(0, count($excl), '?'));
	// We will select one non-excluded fee for this student if available
	// Note: we will use a prepared statement
	$stmt = $conn->prepare("SELECT id FROM fees WHERE student_id = ? AND category NOT IN ($placeholders) LIMIT 1");
	if ($stmt) {
		$bind_types = 'i' . str_repeat('s', count($excl));
		$params = array_merge([$student_id], $excl);
		// bind dynamically
		$args = [];
		$args[] = $bind_types;
		foreach ($params as $p) $args[] = $p;
		$refArr = [];
		foreach ($args as $key => $value) {
			$refArr[$key] = &$args[$key];
		}
		call_user_func_array([$stmt, 'bind_param'], $refArr);
		$stmt->execute();
		$res = $stmt->get_result();
		if ($row = $res->fetch_assoc()) {
			$fee_id_to_use = intval($row['id']);
		}
		$stmt->close();
	}
}

// Build INSERT dynamically based on columns on payments
$columns = [];
$placeholders = [];
$values = [];
$types = '';

if ($paymentsHasStudentId) {
	$columns[] = 'student_id';
	$placeholders[] = '?';
	$types .= 'i';
	$values[] = $student_id;
}
if ($paymentsHasFeeId) {
	$columns[] = 'fee_id';
	$placeholders[] = '?';
	$types .= 'i';
	$values[] = ($fee_id_to_use !== null) ? $fee_id_to_use : null;
}
$columns[] = 'amount';
$placeholders[] = '?';
$types .= 'd';
$values[] = $amount;
// optional: created_at column if exists
if (columnExists($conn, 'payments', 'created_at')) {
	$columns[] = 'created_at';
	$placeholders[] = '?';
	$types .= 's';
	$values[] = date('Y-m-d H:i:s');
}

// prepare statement
$cols_sql = implode(',', $columns);
$placeholders_sql = implode(',', $placeholders);

$sql = "INSERT INTO payments ({$cols_sql}) VALUES ({$placeholders_sql})";
$stmt = $conn->prepare($sql);
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement: ' . $conn->error]);
	exit;
}

// Bind parameters dynamically
$bind_names[] = $types;
foreach ($values as $key => $value) {
	$bind_names[] = &$values[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

if (!$stmt->execute()) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to insert payment: ' . $stmt->error]);
	exit;
}
$stmt->close();

// Now compute updated totals consistent with the AccountBalance calculation
define('FIXED_TOTAL_FEE', 15000.00);
$total_fees = FIXED_TOTAL_FEE;
$total_payments = 0.0;

// If fees exists and payments has fee_id, sum payments that are joined to fees excluding categories
if ($feesExists && $paymentsHasFeeId) {
	$query = "
		SELECT IFNULL(SUM(p.amount), 0) AS total_paid
		FROM payments p
		JOIN fees f2 ON p.fee_id = f2.id
		WHERE f2.student_id = ?
		  AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')
	";
	$stmt = $conn->prepare($query);
	$stmt->bind_param('i', $student_id);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($r = $res->fetch_assoc()) $total_payments = (float)$r['total_paid'];
	$stmt->close();
} elseif ($paymentsHasStudentId) {
	$query = "SELECT IFNULL(SUM(amount), 0) AS total_paid FROM payments WHERE student_id = ?";
	$stmt = $conn->prepare($query);
	$stmt->bind_param('i', $student_id);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($r = $res->fetch_assoc()) $total_payments = (float)$r['total_paid'];
	$stmt->close();
} else {
	// cannot compute totals; return error
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Cannot compute payments totals with current database schema.']);
	exit;
}

$balance = round($total_fees - $total_payments, 2);

echo json_encode([
	'success' => true,
	'message' => 'Payment saved.',
	'totals' => [
		'total_fees' => $total_fees,
		'total_payments' => $total_payments,
		'balance' => $balance
	]
]);
exit;
