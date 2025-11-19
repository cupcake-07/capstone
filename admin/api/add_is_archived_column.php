<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/admin-session.php';
require_once __DIR__ . '/../../includes/admin-check.php';

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if column already exists
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($check && $check->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'is_archived already exists']);
    exit;
}

// Add the column with a safe default
$sql = "ALTER TABLE students ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0";
if ($conn->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Column added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add column: ' . $conn->error]);
}
$check->close();
$conn->close();
