<?php
require_once 'config/database.php';

// Get logout type from URL parameter
$type = isset($_GET['type']) && $_GET['type'] === 'admin' ? 'admin' : 'student';

// Set the correct session name BEFORE session_start
if ($type === 'admin') {
    session_name('ADMIN_SESSION');
}
// else: use default PHP session name for students

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current session name for proper cookie deletion
$currentSessionName = session_name();

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (isset($_COOKIE[$currentSessionName])) {
    setcookie($currentSessionName, '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to appropriate login page
if ($type === 'admin') {
    header('Location: admin-login.php');
} else {
    header('Location: login.php');
}
exit;
?>
