<?php
require_once 'config/database.php';

// Get current user type before destroying
$currentUserType = $_SESSION['user_type'] ?? 'student';

// Destroy full session
$_SESSION = [];
session_destroy();

// Always redirect to login page
header('Location: login.php');
exit;
?>
