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

// Include the shared header (to inherit site theme) and hide the navbar on this page.
// Adjust header include path(s) to match your project structure.
$hide_navbar_css = '<style>
/* Hide common navbar elements so header remains but top navigation is hidden on this page */
nav, .navbar, .topbar, .site-header .navbar, .site-navbar, .navbar-brand, .site-logo, .brand { display: none !important; }

/* Reset any top padding so login area vertically centers correctly */
body { padding-top: 0 !important; }

/* Login container styles to center the card; you may adjust as needed */
.login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 0; }
.login-card { width:100%; max-width:420px; }

/* If header.php has a fixed header offset, ensure the login sits flush on top */
.main, #main, .content { padding-top: 0 !important; margin-top: 0 !important; }
</style>';

if (file_exists(__DIR__ . '/header.php')) {
    include __DIR__ . '/header.php';
    echo $hide_navbar_css;
} elseif (file_exists(__DIR__ . '/includes/header.php')) {
    include __DIR__ . '/includes/header.php';
    echo $hide_navbar_css;
} else {
    // No shared header found; if you still want to hide any inline top bars, echo the CSS anyway
    echo $hide_navbar_css;
}

$error_message = '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// ...existing code...

// Ensure the login form markup is using the .login-container / .login-card classes so it remains centered.
// Replace or adapt your existing login HTML with these classes if necessary:
/*
<div class="login-container">
    <div class="login-card card shadow-sm">
        <div class="card-header text-center">
            <h3 class="mb-0">Teacher Login</h3>
        </div>
        <div class="card-body">
            ...your existing login form...
        </div>
    </div>
</div>
*/

// ...existing code...
?>
