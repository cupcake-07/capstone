<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $result = $conn->query("SELECT id, name, email, password FROM admins WHERE email = '$email' LIMIT 1");

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_type'] = 'admin';
                $_SESSION['admin_name'] = $user['name'];
                
                header('Location: admin.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Admin Login - School Management System</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
            .login-container h1 { text-align: center; color: #333; margin-bottom: 10px; font-size: 24px; }
            .login-container .subtitle { text-align: center; color: #666; margin-bottom: 5px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
            .login-container p { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 600; }
            .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
            .form-group input:focus { outline: none; border-color: #667eea; }
            .btn-login { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: 600; cursor: pointer; }
            .btn-login:hover { background: #5568d3; }
            .error { background: #fee; color: #c33; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
            .demo-info { background: #f0f0f0; padding: 15px; border-radius: 4px; margin-top: 20px; font-size: 13px; color: #666; }
            .demo-info strong { display: block; margin-bottom: 8px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="subtitle">Admin Panel</div>
            <h1>School Management</h1>
            <p>Glorious God's Family Christian School</p>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your admin email" required />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required />
                </div>

                <button type="submit" class="btn-login">Login as Admin</button>
            </form>

            <div class="demo-info">
                <strong>Demo Admin Credentials:</strong>
                Email: admin@capstone.com<br>
                Password: admin123
            </div>
        </div>
    </body>
</html>
