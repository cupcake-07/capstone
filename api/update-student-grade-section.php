<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$studentId = $_POST['student_id'] ?? null;
$gradeLevel = $_POST['grade_level'] ?? null;
$section = $_POST['section'] ?? null;

if (!$studentId || !$gradeLevel || !$section) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$studentId = intval($studentId);
$stmt = $conn->prepare("UPDATE students SET grade_level = ?, section = ? WHERE id = ?");
$stmt->bind_param("ssi", $gradeLevel, $section, $studentId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
?>
