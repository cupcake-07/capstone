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

$paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
if (!$paymentId) {
    echo json_encode(['success'=>false, 'message'=>'Missing payment id']);
    exit;
}

// Verify existence
$res = $conn->prepare("SELECT id, student_id FROM payments WHERE id = ?");
$res->bind_param('i', $paymentId);
$res->execute();
$r = $res->get_result()->fetch_assoc();
$res->close();
if (!$r) {
    echo json_encode(['success'=>false, 'message'=>'Not found']);
    exit;
}
$studentId = $r['student_id'];

// Delete
$del = $conn->prepare("DELETE FROM payments WHERE id = ?");
$del->bind_param('i', $paymentId);
$ok = $del->execute();
$del->close();

if (!$ok) {
    echo json_encode(['success'=>false, 'message'=>'Delete failed']);
    exit;
}

// Compute new total payments for the student, excluding excluded categories if possible
$res2 = $conn->query("SHOW COLUMNS FROM payments LIKE 'fee_id'");
$hasFeeId = ($res2 && $res2->num_rows > 0);
if ($hasFeeId) {
    $stmt = $conn->prepare("SELECT IFNULL(SUM(p.amount), 0) as total FROM payments p JOIN fees f ON p.fee_id = f.id WHERE p.student_id = ? AND f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship')");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stmt->bind_result($newTotal);
    $stmt->fetch();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total FROM payments WHERE student_id = ?");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stmt->bind_result($newTotal);
    $stmt->fetch();
    $stmt->close();
}

echo json_encode(['success'=>true, 'new_total'=> (float)$newTotal]);
exit;
