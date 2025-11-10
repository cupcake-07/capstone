<?php
// Start a session for admin only when there is no active session.
// This prevents "Session name cannot be changed when a session is active" warnings.
if (session_status() === PHP_SESSION_NONE) {
    session_name('ADMIN_SESSION');
    session_start();
} else {
    // session already active — do not call session_name() here.
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

$adminUser = getAdminSession();
?>
