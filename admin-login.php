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
            /* Black & white / grayscale palette - increased sizing */
            body {
                font-family: 'Inter', sans-serif;
                font-size: 18px;                /* increased base font size */
                line-height: 1.45;
                background: linear-gradient(135deg, #2535c7ff 0%, #b43f91ff 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #111;
                padding: 24px;
            }
            .login-container {
                 background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25), 0 2px 8px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 400px;
            }
            .login-container h1 {
                text-align: center;
                color: #ad49a5ff;
                margin-bottom: 12px;
                font-size: 28px;               /* larger heading */
                font-weight: 700;
            }
            .login-container .subtitle {
                text-align: center;
                color: #333;
                margin-bottom: 8px;
                font-size: 14px;               /* slightly larger */
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .login-container p {
                text-align: center;
                color: #555;
                margin-bottom: 28px;
                font-size: 16px;               /* larger paragraph */
            }
            .form-group { margin-bottom: 22px; }
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #111;
                font-weight: 700;
                font-size: 15px;
            }
            .form-group input {
                width: 100%;
                padding: 14px;                /* larger input padding */
                border: 1px solid #bdbdbd;
                border-radius: 6px;
                font-size: 16px;              /* larger input text */
                background: #fff;
                color: #111;
            }
            .form-group input::placeholder { font-size: 15px; }
            .form-group input:focus {
                outline: none;
                border-color: #000;
                box-shadow: 0 0 0 4px rgba(0,0,0,0.06);
            }
            .btn-login {
                width: 100%;
                padding: 14px;                /* larger button */
                background: #b83fa3ff;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 18px;              /* larger button text */
                font-weight: 700;
                cursor: pointer;
            }
            .btn-login:hover { background: #4f65e0ff; }
            .error {
                background: #fff6f6;
                color: #a00000;
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 20px;
                font-size: 15px;
                border: 1px solid #a00000;
            }
            .demo-info {
                background: #f7f7f7;
                padding: 16px;
                border-radius: 6px;
                margin-top: 22px;
                font-size: 14px;
                color: #444;
                border: 1px solid #e6e6e6;
            }
            .demo-info strong { display: block; margin-bottom: 8px; color: #111; font-size:15px; }
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
