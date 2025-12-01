<?php
// TEACHER-SPECIFIC session cleanup - only destroy teacher session
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// Clear only teacher session data
$_SESSION = [];

// Destroy only the teacher session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy only the current (teacher) session
session_destroy();

// Redirect to teacher login page
header('Location: /teachers/teacher-login.php');
exit;
?>
