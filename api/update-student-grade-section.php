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
$grade_level_input = trim(isset($_POST['grade_level']) ? $_POST['grade_level'] : '');
$section = isset($_POST['section']) ? strtoupper(trim($_POST['section'])) : null;

// --- Normalization & allowed list (NEW) ---
$allowedGrades = ['K1', 'K2', '1', '2', '3', '4', '5', '6'];

// Normalize common textual inputs to internal codes
$normalizedGrade = null;
$gl = strtoupper(trim($grade_level_input));
if (in_array($gl, $allowedGrades, true)) {
    $normalizedGrade = $gl;
} else {
    // Accept "Kinder 1", "Kinder 2" (case-insensitive) and variants
    $map = [
        'KINDER 1' => 'K1',
        'KINDER1'  => 'K1',
        'KINDER 2' => 'K2',
        'KINDER2'  => 'K2',
    ];
    $glNoSpaces = strtoupper(str_replace(' ', '', $grade_level_input));
    if (isset($map[$gl]) || isset($map[$glNoSpaces])) {
        $normalizedGrade = $map[$gl] ?? $map[$glNoSpaces];
    }
}

if ($student_id <= 0) {
	echo json_encode(['success' => false, 'message' => 'Invalid student id']);
	exit;
}

if (!$normalizedGrade) {
    echo json_encode(['success' => false, 'message' => 'Invalid grade level']);
    exit;
}

if (!in_array($normalizedGrade, $allowedGrades, true)) {
	echo json_encode(['success' => false, 'message' => 'Invalid grade level']);
	exit;
}

// Update DB using prepared statement
$updateStmt = $conn->prepare("UPDATE students SET grade_level = ?, section = ? WHERE id = ? LIMIT 1");
if (!$updateStmt) {
	error_log('Prepare failed: ' . $conn->error);
	echo json_encode(['success' => false, 'message' => 'Database error']);
	exit;
}
$updateStmt->bind_param('ssi', $normalizedGrade, $section, $student_id);
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
