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

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$studentId) {
    echo json_encode(['success'=>false, 'message'=>'Missing student id']);
    exit;
}

// Check payments table exists
$res = $conn->query("SHOW TABLES LIKE 'payments'");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success'=>true, 'data'=>[], 'total'=>0]);
    exit;
}

// Determine if payments link to fees via fee_id
$hasFeeId = false;
$colRes = $conn->query("SHOW COLUMNS FROM payments LIKE 'fee_id'");
if ($colRes && $colRes->num_rows > 0) $hasFeeId = true;

$data = [];
$total = 0;

if ($hasFeeId) {
    $sql = "SELECT p.id, p.amount, p.description, p.created_at, p.payment_date, f.category
            FROM payments p
            LEFT JOIN fees f ON p.fee_id = f.id
            WHERE p.student_id = ? AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship'))
            ORDER BY COALESCE(p.created_at, p.payment_date, p.id) DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $r['amount'] = (float)$r['amount'];
        $data[] = $r;
        $total += $r['amount'];
    }
    $stmt->close();
} else {
    // payments only by student table: can't exclude categories reliably
    $sql = "SELECT id, amount, description, created_at, payment_date FROM payments WHERE student_id = ? ORDER BY COALESCE(created_at, payment_date, id) DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $r['amount'] = (float)$r['amount'];
        $data[] = $r;
        $total += $r['amount'];
    }
    $stmt->close();
}

echo json_encode(['success'=>true, 'data'=>$data, 'total'=>$total]);
exit;
