<?php
session_start();
require_once 'config/database.php';

$error_message = '';
$success_message = '';
$token = $_GET['token'] ?? '';
$email = strtolower(trim($_GET['email'] ?? ''));

// Basic validation: require both token and email
if (empty($token) || empty($email)) {
    $error_message = 'Invalid or missing reset link.';
}

// Helper: check token+email validity
function isValidResetToken($conn, $token, $email) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id FROM students WHERE reset_token = ? AND email = ? AND reset_token_expiry > NOW() LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $token_hash, $email);
    $stmt->execute();
    $stmt->store_result();
    $valid = $stmt->num_rows > 0;
    $stmt->close();
    return $valid;
}

// If we have token+email, verify before showing the form
if (empty($error_message)) {
    if (!isValidResetToken($conn, $token, $email)) {
        $error_message = 'Reset link is invalid or has expired.';
    }
}

// Handle Password Reset (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset']) && empty($error_message)) {
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';
    $post_token = $_POST['reset_token'] ?? '';
    $post_email = strtolower(trim($_POST['reset_email'] ?? ''));

    if (empty($post_token) || empty($post_email) || $post_token !== $token || $post_email !== $email) {
        $error_message = 'Invalid reset request.';
    } elseif (empty($password) || empty($password_confirm)) {
        $error_message = 'All fields required.';
    } elseif ($password !== $password_confirm) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } else {
        // Final verification before update
        if (!isValidResetToken($conn, $token, $email)) {
            $error_message = 'Reset link is invalid or has expired.';
        } else {
            $token_hash = hash('sha256', $token);
            $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE students SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ? AND email = ?");
            if ($upd) {
                $upd->bind_param('sss', $new_password_hash, $token_hash, $email);
                if ($upd->execute()) {
                    $_SESSION['flash_success'] = 'Password reset successful! Please log in.';
                    header('Location: login.php');
                    exit;
                } else {
                    $error_message = 'Failed to reset password. Try again later.';
                }
                $upd->close();
            } else {
                $error_message = 'Server error. Try again later.';
            }
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
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f4f4f4; font-family: Arial, sans-serif; }
        .card { background:#fff; padding:28px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.08); width:100%; max-width:420px; }
        h1 { margin:0 0 18px 0; font-size:20px; color:#111; text-align:center; }
        .message { padding:12px; border-radius:6px; margin-bottom:16px; text-align:center; }
        .error { background:#fff3f3; color:#7a1414; border:1px solid #f3caca; }
        .success { background:#f3fff3; color:#145a14; border:1px solid #c3e6cb; }
        .infield { margin-bottom:12px; }
        input[type="password"], input[type="text"], input[type="email"] { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box; }
        button { width:100%; padding:12px; background:#111; color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer; }
        .back { margin-top:12px; text-align:center; }
        .back a { color:#111; text-decoration:none; }
    </style>
</head>
<body>
<div class="card">
    <h1>Reset Password</h1>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <div class="back"><a href="login.php">← Back to Login</a></div>
    <?php else: ?>
        <form method="POST" novalidate>
            <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="infield">
                <input type="password" name="new_password" placeholder="New password (min 6 chars)" required>
            </div>
            <div class="infield">
                <input type="password" name="confirm_password" placeholder="Confirm password" required>
            </div>
            <button type="submit" name="reset" value="1">Set New Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
