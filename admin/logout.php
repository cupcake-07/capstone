<?php
// Use admin session name
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy admin session only
$_SESSION = [];
session_destroy();

// Redirect to admin login
header('Location: ../admin-login.php');
exit;
?>
