<?php
require_once 'config/database.php';

// Check which session is active
$adminSessionName = 'ADMIN_SESSION';
$defaultSessionName = session_name();

// Check if admin session is active
session_name($adminSessionName);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdmin = isset($_SESSION['admin_id']) && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'admin';

// Destroy the current session
$_SESSION = [];
session_destroy();

// Redirect based on which session was active
if ($isAdmin) {
    header('Location: admin-login.php');
} else {
    header('Location: login.php');
}
exit;
?>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy session
session_unset();
session_destroy();

// Check redirect parameter
$redirect = $_GET['redirect'] ?? 'login';

if ($redirect === 'student') {
    // Redirect to student login
    header('Location: login.php?role=student');
} else {
    // Default redirect to main login
    header('Location: login.php');
}
exit;
?>
