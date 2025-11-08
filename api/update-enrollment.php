<?php
// Start admin session with custom name
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Suppress errors
error_reporting(0);
ini_set('display_errors', 0);

// Check admin session
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_type'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not admin']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'POST only']));
}

$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$is_enrolled = isset($_POST['is_enrolled']) ? (int)$_POST['is_enrolled'] : 0;

if ($student_id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid student ID']));
}

$sql = "UPDATE students SET is_enrolled = $is_enrolled WHERE id = $student_id";

if ($conn->query($sql)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Updated']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

exit;
?>
