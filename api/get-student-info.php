<?php
header('Content-Type: application/json; charset=utf-8');

// Use same session name as student.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = intval($_SESSION['user_id']);

if ($stmt = $conn->prepare("SELECT id, name, email, grade_level, section, is_enrolled, avg_score FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $grade = isset($row['grade_level']) ? (string)$row['grade_level'] : '';
        $section = isset($row['section']) ? (string)$row['section'] : '';
        echo json_encode(['success' => true, 'grade' => $grade, 'section' => $section, 'name' => $row['name']]);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

echo json_encode(['success' => false, 'message' => 'Student not found']);
exit;
