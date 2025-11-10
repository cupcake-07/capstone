<?php
require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Simple helper
function json_err($msg) {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
$grade = isset($_POST['grade']) ? trim($_POST['grade']) : '';
$sections = isset($_POST['sections']) ? trim($_POST['sections']) : '';

if ($teacher_id <= 0) {
    json_err('Invalid teacher ID');
}

// Ensure columns exist, add them if missing
$colsToAdd = [];
$res = $conn->query("SHOW COLUMNS FROM teachers LIKE 'grade'");
if (!$res || $res->num_rows == 0) $colsToAdd[] = "ADD `grade` VARCHAR(10) DEFAULT NULL";
$res = $conn->query("SHOW COLUMNS FROM teachers LIKE 'sections'");
if (!$res || $res->num_rows == 0) $colsToAdd[] = "ADD `sections` VARCHAR(255) DEFAULT NULL";

if (!empty($colsToAdd)) {
    $alterSql = "ALTER TABLE teachers " . implode(", ", $colsToAdd);
    if (!$conn->query($alterSql)) {
        json_err('Failed to update table structure: ' . $conn->error);
    }
}

// Optional: validate grade is 1-6 or empty
if ($grade !== '' && !in_array($grade, ['1','2','3','4','5','6'], true)) {
    json_err('Grade must be 1-6 or empty');
}

// Normalize sections (remove extra spaces, collapse commas)
if ($sections !== '') {
    $arr = array_map('trim', explode(',', $sections));
    $arr = array_values(array_filter($arr, function($v){ return $v !== ''; }));
    $sections = implode(', ', $arr);
} else {
    $sections = null;
}
$gradeParam = ($grade === '' ? null : $grade);

// Update teacher record
$stmt = $conn->prepare("UPDATE teachers SET grade = ?, sections = ? WHERE id = ?");
if (!$stmt) {
    json_err('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('ssi', $gradeParam, $sections, $teacher_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    json_err('Update failed: ' . $stmt->error);
}
$stmt->close();
$conn->close();
