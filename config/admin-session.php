<?php
// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'admin';
}

// Get admin session info
function getAdminSession() {
    if (isAdminLoggedIn()) {
        return [
            'id' => $_SESSION['admin_id'],
            'type' => $_SESSION['admin_type'],
            'name' => $_SESSION['admin_name'] ?? 'Admin'
        ];
    }
    return null;
}

// Require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: admin-login.php');
        exit;
    }
}
?>
