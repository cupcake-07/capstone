<?php
require_once 'config/database.php';

// Determine which session to destroy based on referrer or session name
$isTeacher = false;

// Check if we're coming from teacher area
if (strpos($_SERVER['HTTP_REFERER'] ?? '', '/teachers/') !== false) {
    $isTeacher = true;
}

// Try to detect based on active session
if (session_status() === PHP_SESSION_NONE) {
    // Try teacher session first
    session_name('TEACHER_SESSION');
    session_start();
    if (!empty($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher') {
        $isTeacher = true;
    } else {
        session_destroy();
        // Switch to student session
        session_name('STUDENT_SESSION');
        session_start();
    }
} else {
    $isTeacher = ($_SESSION['user_type'] ?? '') === 'teacher';
}

// Destroy the active session
session_destroy();

// Redirect to appropriate login page
$redirectUrl = $isTeacher ? 'teachers/teacher-login.php' : 'login.php';
header('Location: ' . $redirectUrl);
exit;
?>
<?php
// Use same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// Clear all session data
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
