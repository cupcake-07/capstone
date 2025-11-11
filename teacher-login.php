<?php
// Teacher Session - UNIQUE NAME
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// Handle Logout - only for TEACHER session
if (isset($_GET['logout']) && $_GET['logout'] === 'teacher') {
    unset($_SESSION['teacher_id']);
    unset($_SESSION['teacher_type']);
    unset($_SESSION['teacher_name']);
    unset($_SESSION['teacher_email']);
    header('Location: teacher-login.php');
    exit;
}

// If already logged in, redirect to teacher.php
if (isset($_SESSION['teacher_id'])) {
    header('Location: teacher.php');
    exit;
}

require_once 'config/database.php';

$error_message = '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// ...rest of teacher login code...
?>
