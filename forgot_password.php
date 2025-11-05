<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/SignLog.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f6f7f9; }
        .forgot-container { background:#fff; padding:40px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.1); max-width:400px; width:100%; }
        .forgot-container h1 { margin:0 0 12px; text-align:center; }
        .forgot-container p { text-align:center; color:#666; margin:0 0 20px; font-size:14px; }
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
        .form-group label { font-weight:600; color:#333; }
        .form-group input { padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
        .form-group input:focus { outline:none; border-color:#0f520c; box-shadow:0 0 0 3px rgba(15,82,12,0.1); }
        .btn { background:#000; color:#fff; border:0; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:700; }
        .btn:hover { background:#333; }
        .msg { padding:12px 15px; border-radius:4px; margin-bottom:15px; text-align:center; }
        .msg.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .msg.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .back-link { text-align:center; margin-top:16px; }
        .back-link a { color:#0f520c; text-decoration:none; font-weight:600; font-size:14px; }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h1>Forgot Password?</h1>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
        
        <?php if ($flash_success): ?>
            <div class="msg success"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="msg error"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="send_reset_link.php">
            <div class="form-group">
                <label for="email"></label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required />
            </div>
            <button type="submit" class="btn">Send Reset Link</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
