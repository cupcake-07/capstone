<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../includes/admin-check.php';

// ensure admin is authenticated
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
	exit;
}

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : null;
$section = isset($_POST['section']) ? strtoupper(trim($_POST['section'])) : null;

if ($student_id <= 0) {
	echo json_encode(['success' => false, 'message' => 'Invalid student id']);
	exit;
}

// basic validation for grade and section
if ($grade_level === null || !preg_match('/^[1-6]$/', (string)$grade_level)) {
	echo json_encode(['success' => false, 'message' => 'Invalid grade level']);
	exit;
}
if ($section === null || !preg_match('/^[A-Z]$/', $section)) {
	echo json_encode(['success' => false, 'message' => 'Invalid section']);
	exit;
}

// Update DB using prepared statement
$updateStmt = $conn->prepare("UPDATE students SET grade_level = ?, section = ? WHERE id = ? LIMIT 1");
if (!$updateStmt) {
	error_log('Prepare failed: ' . $conn->error);
	echo json_encode(['success' => false, 'message' => 'Database error']);
	exit;
}
$updateStmt->bind_param('isi', $grade_level, $section, $student_id);
$ok = $updateStmt->execute();
if ($ok === false) {
	error_log('Execute failed: ' . $updateStmt->error);
	echo json_encode(['success' => false, 'message' => 'Failed to update student']);
	$updateStmt->close();
	exit;
}

// Note: affected_rows can be 0 if values were the same; treat as success
$updateStmt->close();

echo json_encode(['success' => true, 'message' => 'Student updated']);
exit;
?>
