<?php
// Use a separate session name for teachers - MUST be before session_start()
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $result = $conn->query("SELECT id, name, email, password FROM teachers WHERE email = '$email' LIMIT 1");

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = 'teacher';
                $_SESSION['user_name'] = $user['name'];
                
                header('Location: teacher.php');
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
    <title>Teacher Login - School Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #1a1a1a;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .navbar-logo {
            background: #d4af37;
            color: #1a1a1a;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }

        .navbar-text .navbar-title {
            font-weight: 700;
            font-size: 16px;
        }

        .navbar-text .navbar-subtitle {
            font-size: 12px;
            color: #aaa;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
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
            color: #1a1a1a;
            margin-bottom: 8px;
            font-size: 24px;
            font-weight: 700;
        }

        .login-container .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-container p {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 14px;
            background: #fafafa;
            color: #1a1a1a;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #333;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .btn-login:hover {
            background: #000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .error {
            background: #fff5f5;
            color: #8b0000;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid #ffcdd2;
        }

        .auth-links {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .auth-links p {
            margin: 0 0 10px 0;
            font-size: 13px;
            color: #666;
        }

        .auth-links a {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #333;
        }

        .info-box {
            background: #f9f9f9;
            padding: 14px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            border: 1px solid #e8e8e8;
            line-height: 1.6;
        }

        .info-box strong {
            display: block;
            color: #1a1a1a;
            margin-bottom: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-logo">GGF</div>
            <div class="navbar-text">
                <div class="navbar-title">Glorious God's Family</div>
                <div class="navbar-subtitle">Christian School</div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container">
            <div class="subtitle">Teacher Portal</div>
            <h1>School Management</h1>
            <p>Glorious God's Family Christian School</p>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Teacher Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required />
                </div>

                <button type="submit" class="btn-login">Login as Teacher</button>
            </form>

            <div class="info-box">
                <strong>New to the system?</strong>
                Create an account to get started with grade management and classroom coordination.
            </div>

            <div class="auth-links">
                <p>Don't have an account?</p>
                <a href="teacher-register.php">Create Teacher Account</a>
                <p style="margin-top: 16px;">
                    <a href="../login.php">Login as Student</a> | 
                    <a href="../admin-login.php">Login as Admin</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
