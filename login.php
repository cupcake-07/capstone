<?php
// Session setup
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// If already logged in, redirect to student.php
if (isset($_SESSION['user_id'])) {
    header('Location: student.php');
    exit;
}

require_once 'config/database.php';

$error_message = '';
$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Check DB connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    $error_message = 'Database connection error.';
}

// Handle Signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup']) && empty($error_message)) {
    $name = trim($_POST['signup_name'] ?? '');
    $email = strtolower(trim($_POST['signup_email'] ?? ''));
    $password = $_POST['signup_password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = 'All fields required.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password min 6 chars.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error_message = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO students (name, email, password) VALUES (?, ?, ?)");
            $ins->bind_param('sss', $name, $email, $hash);
            
            if ($ins->execute()) {
                $_SESSION['flash_success'] = 'Account created! Please log in.';
                header('Location: login.php');
                exit;
            } else {
                $error_message = 'Signup failed.';
            }
            $ins->close();
        }
        $stmt->close();
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && empty($error_message)) {
    $email = strtolower(trim($_POST['login_email'] ?? ''));
    $password = $_POST['login_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password required.';
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'student';
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: student.php');
            exit;
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/SignLog.css">
    <style>
        .message-box { padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
<div class="container" id="container">
    <div class="form-container sign-up-container">
        <form method="POST">
            <h1>Sign Up</h1>
            <?php if ($error_message && isset($_POST['signup'])): ?>
                <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <div class="infield">
                <input type="text" placeholder="Full Name" name="signup_name" required/>
            </div>
            <div class="infield">
                <input type="email" placeholder="Email" name="signup_email" required/>
            </div>
            <div class="infield">
                <input type="password" placeholder="Password (min 6 chars)" name="signup_password" required/>
            </div>
            <button type="submit" name="signup" value="1">Sign Up</button>
        </form>
    </div>
    
    <div class="form-container log-in-container">
        <form method="POST">
            <h1>Log in</h1>
            <?php if ($flash_success): ?>
                <div class="message-box success-message"><?php echo htmlspecialchars($flash_success); ?></div>
            <?php endif; ?>
            <?php if ($error_message && isset($_POST['login'])): ?>
                <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <div class="infield">
                <input type="email" placeholder="Email" name="login_email" required/>
            </div>
            <div class="infield">
                <input type="password" placeholder="Password" name="login_password" required/>
            </div>
            <button type="submit" name="login" value="1">Log In</button>
        </form>
    </div>
    
    <div class="overlay-container" id="overlayCon">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <h2>Welcome Back!</h2>
                <p>Log in with your credentials.</p>
            </div>
            <div class="overlay-panel overlay-right">
                <h2>Hello, Student!</h2>
                <p>Create an account to get started.</p>
            </div>
        </div>
    </div>
    
    <div class="toggle-switch-wrapper">
        <label class="toggle-switch">
            <input type="checkbox" id="toggleForms">
            <span class="toggle-slider"></span>
        </label>
    </div>
</div>

<script>
    const container = document.getElementById('container');
    const toggleSwitch = document.getElementById('toggleForms');
    toggleSwitch.addEventListener('change', () => {
        container.classList.toggle('right-panel-active');
    });
</script>
</body>
</html>
