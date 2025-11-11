<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy the session
session_destroy();

// Redirect to teacher login page (not student login)
header('Location: teacher-login.php');
exit;
?>
