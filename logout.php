<?php
require_once 'config/database.php';

// Unified logout handler that knows which session to destroy
$type = $_GET['type'] ?? 'student';

if ($type === 'admin') {
    session_name('ADMIN_SESSION');
    session_start();
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_type']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    header('Location: admin-login.php');
} elseif ($type === 'teacher') {
    session_name('TEACHER_SESSION');
    session_start();
    unset($_SESSION['teacher_id']);
    unset($_SESSION['teacher_type']);
    unset($_SESSION['teacher_name']);
    unset($_SESSION['teacher_email']);
    header('Location: teacher-login.php');
} else {
    // student
    session_name('STUDENT_SESSION');
    session_start();
    unset($_SESSION['user_id']);
    unset($_SESSION['user_type']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    header('Location: login.php');
}
exit;
?>
