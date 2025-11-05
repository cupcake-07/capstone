<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$user_id = null;

if (empty($token)) {
    $error = 'Invalid reset link.';
} else {
    // Validate token - check if it exists, hasn't been used, and hasn't expired
    $stmt = $conn->prepare("SELECT user_id, expiry FROM password_resets WHERE token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $expiry);
            $stmt->fetch();
            
            // Check if token is expired
            if (strtotime($expiry) < time()) {
                $error = 'Reset link has expired. Please request a new one.';
                $user_id = null;
            }
            // Check if token was already used
            $checkUsed = $conn->prepare("SELECT used FROM password_resets WHERE token = ? LIMIT 1");
            if ($checkUsed) {
                $checkUsed->bind_param('s', $token);
                $checkUsed->execute();
                $checkUsed->bind_result($used);
                $checkUsed->fetch();
                if ($used == 1) {
                    $error = 'This reset link has already been used.';
                    $user_id = null;
                }
                $checkUsed->close();
            }
        } else {
            $error = 'Reset link is invalid or not found.';
        }
        $stmt->close();
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $user_id) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $hashedPassword, $user_id);
            if ($stmt->execute()) {
                // Mark token as used
                $stmt2 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                if ($stmt2) {
                    $stmt2->bind_param('s', $token);
                    $stmt2->execute();
                    $stmt2->close();
                }
                
                $success = 'Password reset successfully! You can now log in.';
                $token = ''; // Clear token so form doesn't show again
            } else {
                $error = 'Error updating password. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/SignLog.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f6f7f9; }
        .reset-container { background:#fff; padding:40px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:400px; width:100%; }
        .reset-container h1 { margin:0 0 12px; text-align:center; }
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
        .form-group label { font-weight:600; color:#333; }
        .form-group input { padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
        .form-group input:focus { outline:none; border-color:#0f520c; box-shadow:0 0 0 3px rgba(15,82,12,0.1); }
        .btn { background:#000; color:#fff; border:0; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:700; width:100%; }
        .btn:hover { background:#333; }
        .msg { padding:12px 15px; border-radius:4px; margin-bottom:15px; text-align:center; }
        .msg.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .msg.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .back-link { text-align:center; margin-top:16px; }
        .back-link a { color:#0f520c; text-decoration:none; font-weight:600; font-size:14px; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h1>Reset Password</h1>
        
        <?php if ($success): ?>
            <div class="msg success"><?php echo htmlspecialchars($success); ?></div>
            <div class="back-link"><a href="login.php">Go to Login</a></div>
        <?php elseif ($error): ?>
            <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
            <div class="back-link"><a href="forgot_password.php">Request New Reset Link</a></div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="new_password" placeholder="Min 6 characters" minlength="6" required />
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm password" minlength="6" required />
                </div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
            <div class="back-link"><a href="login.php">Back to Login</a></div>
        <?php endif; ?>
    </div>
</body>
</html>
