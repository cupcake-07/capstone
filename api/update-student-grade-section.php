<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$gradeLevel = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : '';
$section = isset($_POST['section']) ? trim($_POST['section']) : '';

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid student id']);
    exit;
}

// Basic validation for grade and section
$allowedGrades = ['1','2','3','4','5','6'];
$allowedSections = ['A','B','C'];

if (!in_array($gradeLevel, $allowedGrades, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid grade level']);
    exit;
}
if (!in_array($section, $allowedSections, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid section']);
    exit;
}

$stmt = $conn->prepare("UPDATE students SET grade_level = ?, section = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error (prepare)']);
    exit;
}
$stmt->bind_param('ssi', $gradeLevel, $section, $studentId);
$ok = $stmt->execute();
if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update student']);
}
$stmt->close();
$conn->close();
?>
