<?php
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacher_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

$stmt = $conn->prepare("SELECT password FROM teachers WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('s', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    exit;
}

$row = $result->fetch_assoc();
$stored_hash = $row['password'];
$stmt->close();

if (!password_verify($current_password, $stored_hash)) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_BCRYPT);
$update_stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$update_stmt->bind_param('ss', $new_hash, $teacher_id);
if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password']);
}
$update_stmt->close();
?>
