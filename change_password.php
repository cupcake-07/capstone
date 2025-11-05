<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    $_SESSION['flash_error'] = 'All password fields are required.';
    header('Location: student_settings.php');
    exit;
}
if ($new !== $confirm) {
    $_SESSION['flash_error'] = 'New password and confirmation do not match.';
    header('Location: student_settings.php');
    exit;
}
if (strlen($new) < 6) {
    $_SESSION['flash_error'] = 'New password must be at least 6 characters.';
    header('Location: student_settings.php');
    exit;
}

// fetch current hash
$stmt = $conn->prepare("SELECT password FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($hash);
if (!$stmt->fetch()) {
    $stmt->close();
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: student_settings.php');
    exit;
}
$stmt->close();

if (!password_verify($current, $hash)) {
    $_SESSION['flash_error'] = 'Current password is incorrect.';
    header('Location: student_settings.php');
    exit;
}

$newHash = password_hash($new, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
$stmt->bind_param('si', $newHash, $userId);
if ($stmt->execute()) {
    $_SESSION['flash_success'] = 'Password changed successfully.';
} else {
    $_SESSION['flash_error'] = 'Failed to change password. Please try again.';
}
$stmt->close();
header('Location: student_settings.php');
exit;
