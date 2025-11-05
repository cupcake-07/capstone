<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$grade = trim($_POST['grade_level'] ?? '');
$username = trim($_POST['username'] ?? '');

if ($name === '' || $email === '') {
    $_SESSION['flash_error'] = 'Name and email are required.';
    header('Location: student_settings.php');
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Invalid email format.';
    header('Location: student_settings.php');
    exit;
}

// check email uniqueness
$stmt = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ? LIMIT 1");
$stmt->bind_param('si', $email, $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['flash_error'] = 'Email already in use.';
    header('Location: student_settings.php');
    exit;
}
$stmt->close();

// check username uniqueness if not empty and column exists
$has_username = false;
$res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'username' LIMIT 1");
if ($res && $res->num_rows) $has_username = true;

if ($has_username && $username !== '') {
    $stmt = $conn->prepare("SELECT id FROM students WHERE username = ? AND id != ? LIMIT 1");
    $stmt->bind_param('si', $username, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $_SESSION['flash_error'] = 'Username already taken.';
        header('Location: student_settings.php');
        exit;
    }
    $stmt->close();
}

// perform update (include username if column exists)
if ($has_username) {
    $stmt = $conn->prepare("UPDATE students SET name = ?, username = ?, email = ?, grade_level = ? WHERE id = ?");
    $stmt->bind_param('ssssi', $name, $username, $email, $grade, $userId);
} else {
    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, grade_level = ? WHERE id = ?");
    $stmt->bind_param('sssi', $name, $email, $grade, $userId);
}

if ($stmt->execute()) {
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['flash_success'] = 'Profile updated successfully.';
} else {
    $_SESSION['flash_error'] = 'Failed to update profile. Please try again.';
}
$stmt->close();
header('Location: student_settings.php');
exit;
