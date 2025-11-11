<?php
session_start();
require_once 'config/database.php';

$error_message = '';
$success_message = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    $error_message = 'Invalid reset link.';
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset']) && empty($error_message)) {
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($password_confirm)) {
        $error_message = 'All fields required.';
    } elseif ($password !== $password_confirm) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } else {
        $token_hash = hash('sha256', $token);
        
        $stmt = $conn->prepare("SELECT id FROM students WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->bind_param('s', $token_hash);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE students SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $upd->bind_param('ss', $new_password_hash, $token_hash);
            
            if ($upd->execute()) {
                $success_message = 'Password reset successful! Redirecting to login...';
                $_SESSION['flash_success'] = 'Password reset successful! Please log in.';
            } else {
                $error_message = 'Failed to reset password.';
            }
            $upd->close();
        } else {
            $check = $conn->prepare("SELECT id, reset_token_expiry FROM students WHERE reset_token = ?");
            $check->bind_param('s', $token_hash);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                $error_message = 'Reset link has expired. Please request a new one.';
            } else {
                $error_message = 'Reset link is invalid.';
            }
            $check->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%); font-family: Arial, sans-serif; }
        .reset-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.4); width: 90%; max-width: 400px; }
        .reset-container h1 { text-align: center; color: #000; margin-bottom: 30px; }
        .message-box { padding: 15px 15px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .error-message { background-color: #f0f0f0; color: #333; border: 1px solid #999; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .infield { margin-bottom: 15px; }
        .infield input { width: 100%; padding: 12px; border: 1px solid #333; border-radius: 4px; font-size: 14px; box-sizing: border-box; background: #f9f9f9; color: #000; }
        .infield input:focus { outline: none; border-color: #000; background: #fff; }
        button { width: 100%; padding: 12px; background-color: #000; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 16px; }
        button:hover { background-color: #333; }
        .back-link { text-align: center; margin-top: 15px; }
        .back-link a { color: #000; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="reset-container">
    <h1>Reset Password</h1>
    
    <?php if ($success_message): ?>
        <div class="message-box success-message">
            <strong>✓ <?php echo htmlspecialchars($success_message); ?></strong>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        </script>
    <?php elseif ($error_message): ?>
        <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="infield">
                <input type="password" placeholder="New Password (min 6 chars)" name="new_password" required/>
            </div>
            <div class="infield">
                <input type="password" placeholder="Confirm Password" name="confirm_password" required/>
            </div>
            <button type="submit" name="reset" value="1">Reset Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
