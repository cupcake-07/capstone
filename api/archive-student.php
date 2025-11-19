<?php
// Minimal API to archive a student (admin only)
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../includes/admin-check.php';

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Ensure students table has the column we need
$colRes = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if (!$colRes || $colRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Missing is_archived column. Run migration: /admin/api/add_is_archived_column.php']);
    exit;
}

// Validate input
$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student_id']);
    exit;
}

// Update student to be archived
$stmt = $conn->prepare("UPDATE students SET is_archived = 1 WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('i', $studentId);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made (student may not exist or already archived)']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
