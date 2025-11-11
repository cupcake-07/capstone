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
require_once 'config/email.php';

$error_message = '';
$flash_success = $_SESSION['flash_success'] ?? '';
$reset_link = $_SESSION['reset_link'] ?? '';

// Don't unset yet - we need them in the HTML
// unset($_SESSION['flash_success']);
// unset($_SESSION['reset_link']);

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

// Handle Forgot Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password']) && empty($error_message)) {
    $reset_email = strtolower(trim($_POST['reset_email'] ?? ''));
    
    if (empty($reset_email)) {
        $error_message = 'Email is required.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $reset_email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $reset_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $reset_token);
            
            // Set expiry to 24 hours from now (more time for testing)
            $expiry = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
            
            $upd = $conn->prepare("UPDATE students SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $upd->bind_param('sss', $token_hash, $expiry, $reset_email);
            
            if ($upd->execute()) {
                sendPasswordResetEmail($reset_email, $reset_token);
                $_SESSION['flash_success'] = 'Password reset link generated!';
                $_SESSION['reset_link'] = 'http://localhost/capstone/reset_password.php?token=' . $reset_token;
                header('Location: login.php');
                exit;
            } else {
                $_SESSION['flash_success'] = 'Error generating reset link.';
                header('Location: login.php');
                exit;
            }
            $upd->close();
        } else {
            $_SESSION['flash_success'] = 'If email exists, reset link sent!';
            header('Location: login.php');
            exit;
        }
        $stmt->close();
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset']) && empty($error_message)) {
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';
    $token = $_POST['reset_token'] ?? '';
    
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
                $_SESSION['flash_success'] = '✓ Password reset successful! Please log in.';
                header('Location: login.php');
                exit;
            } else {
                $error_message = 'Failed to reset password.';
            }
            $upd->close();
        } else {
            $error_message = 'Reset link expired or invalid.';
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
    <title>Login</title>
    <link rel="stylesheet" href="css/SignLog.css">
    <style>
        .message-box { padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .overlay-panel .logo-wrapper {
            margin-bottom: 18px;
            text-align: center;
        }
        .overlay-panel .logo {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            display: inline-block;
            border: 3px solid rgba(255,255,255,0.12);
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            background: #fff;
        }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 90%; max-width: 400px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; }
        .close-btn { background: none; border: none; font-size: 28px; cursor: pointer; color: #999; }
        .close-btn:hover { color: #000; }
        .modal-form .infield { margin-bottom: 15px; }
        .modal-form button { width: 100%; padding: 10px; background-color: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
        .modal-form button:hover { background-color: #5568d3; }
        .forgot-pwd-link { text-align: right; margin-top: 10px; }
        .forgot-pwd-link a { color: #667eea; text-decoration: none; font-size: 14px; cursor: pointer; }

        .reset-modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .reset-modal.active { display: flex; align-items: center; justify-content: center; }
        .reset-modal-content { background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%); padding: 40px; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.4); width: 90%; max-width: 400px; }
        .reset-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .reset-modal-header h2 { margin: 0; color: white; }
        .reset-close-btn { background: none; border: none; font-size: 28px; cursor: pointer; color: #999; }
        .reset-close-btn:hover { color: #fff; }
        .reset-modal-form .infield { margin-bottom: 15px; }
        .reset-modal-form input { width: 100%; padding: 12px; border: 1px solid #555; border-radius: 4px; font-size: 14px; background: #f9f9f9; color: #000; box-sizing: border-box; }
        .reset-modal-form input:focus { outline: none; border-color: #000; }
        .reset-modal-form button { width: 100%; padding: 12px; background-color: #000; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .reset-modal-form button:hover { background-color: #333; }
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
                <?php if ($reset_link): 
                    $token = substr($reset_link, strpos($reset_link, 'token=') + 6);
                ?>
                    <div class="message-box success-message" style="padding: 15px;">
                        <strong>✓ Reset Link Generated:</strong><br><br>
                        <button type="button" onclick="openResetPasswordModal('<?php echo htmlspecialchars($token); ?>')" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; width: auto;">
                            Click Here to Reset Password
                        </button>
                    </div>
                <?php endif; ?>
                <?php 
                // Now unset after using them
                unset($_SESSION['flash_success']);
                unset($_SESSION['reset_link']);
                ?>
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
            <div class="forgot-pwd-link">
                <a onclick="openForgotPasswordModal()">Forgot Password?</a>
            </div>
        </form>
    </div>
    
    <div class="overlay-container" id="overlayCon">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <div class="logo-wrapper">
                    <img src="school_logo.jpeg" alt="Logo" class="logo">
                </div>
                <h2>Hello, Student!</h2>
                <p>Create an account to get started.</p>
            </div>
            <div class="overlay-panel overlay-right">
                <div class="logo-wrapper">
                    <img src="school_logo.jpeg" alt="Logo" class="logo">
                </div>
                <h2>Welcome To Glorious God's Family Christian School!</h2>
                <p>Log in with your credentials.</p>
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

<!-- Forgot Password Modal -->
<div id="forgotPasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reset Password</h2>
            <button class="close-btn" onclick="closeForgotPasswordModal()">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <div class="infield">
                <input type="email" placeholder="Enter your email" name="reset_email" required/>
            </div>
            <button type="submit" name="forgot_password" value="1">Send Reset Link</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="reset-modal">
    <div class="reset-modal-content">
        <div class="reset-modal-header">
            <h2>Reset Password</h2>
            <button class="reset-close-btn" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form method="POST" class="reset-modal-form">
            <input type="hidden" name="reset_token" id="resetTokenInput" value=""/>
            <div class="infield">
                <input type="password" placeholder="New Password (min 6 chars)" name="new_password" required/>
            </div>
            <div class="infield">
                <input type="password" placeholder="Confirm Password" name="confirm_password" required/>
            </div>
            <button type="submit" name="reset" value="1">Reset Password</button>
        </form>
    </div>
</div>

<script>
    const container = document.getElementById('container');
    const toggleSwitch = document.getElementById('toggleForms');
    toggleSwitch.addEventListener('change', () => {
        container.classList.toggle('right-panel-active');
    });

    function openForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.add('active');
    }

    function closeForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.remove('active');
    }

    function openResetPasswordModal(token) {
        document.getElementById('resetTokenInput').value = token;
        document.getElementById('resetPasswordModal').classList.add('active');
    }

    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').classList.remove('active');
    }

    window.onclick = function(event) {
        const forgotModal = document.getElementById('forgotPasswordModal');
        const resetModal = document.getElementById('resetPasswordModal');
        if (event.target == forgotModal) {
            forgotModal.classList.remove('active');
        } else if (event.target == resetModal) {
            resetModal.classList.remove('active');
        }
    }
</script>
</body>
</html>
