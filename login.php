<?php
// Ensure session cookie usable across /capstone and its subfolders
$cookieParams = [
    'lifetime' => 0,
    'path' => '/capstone',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
}

// STUDENT-SPECIFIC session name - MUST be set before session_start()
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// Handle Logout - ONLY clear student session data
if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_type']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    // Do NOT call session_destroy() - this keeps student session active but logged out
    // Do NOT clear cookies - this maintains session isolation
    header('Location: /capstone/login.php');
    exit;
}

// Removed: $already_logged_in detection

require_once 'config/database.php';

$error_message = '';
$flash_success = $_SESSION['flash_success'] ?? '';
$reset_link = $_SESSION['reset_link'] ?? '';
$reset_email_session = $_SESSION['reset_email'] ?? '';
$current_role = $_POST['role'] ?? 'student'; // Track current role for form

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
    $role = $_POST['role'] ?? 'student';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = 'All fields required.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password min 6 chars.';
    } elseif (!in_array($role, ['student', 'teacher', 'admin'])) {
        $error_message = 'Invalid role selected.';
    } else {
        // Determine table based on role
        $table = ($role === 'admin') ? 'admins' : (($role === 'teacher') ? 'teachers' : 'students');
        
        $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error_message = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO $table (name, email, password) VALUES (?, ?, ?)");
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
        $user = null;
        $user_role = null;
        
        // Try to find user in each table (auto-detect role)
        $tables = ['students' => 'student', 'teachers' => 'teacher', 'admins' => 'admin'];
        
        foreach ($tables as $table => $role_name) {
            $stmt = $conn->prepare("SELECT id, name, email, password FROM $table WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $found_user = $res->fetch_assoc();
            $stmt->close();
            
            if ($found_user) {
                $user = $found_user;
                $user_role = $role_name;
                break;
            }
        }
        
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_role;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            
            session_write_close();

            if ($user_role === 'admin') {
                header('Location: /capstone/admin.php');
            } elseif ($user_role === 'teacher') {
                header('Location: /capstone/teachers/teacher.php');
            } else {
                header('Location: /capstone/student.php');
            }
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
        $user_role = null;
        $table = null;
        
        // Auto-detect role by checking tables
        $tables = ['students' => 'student', 'teachers' => 'teacher', 'admins' => 'admin'];
        
        foreach ($tables as $tbl => $role_name) {
            $stmt = $conn->prepare("SELECT id FROM $tbl WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $reset_email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $table = $tbl;
                $user_role = $role_name;
                $stmt->close();
                break;
            }
            $stmt->close();
        }
        
        if ($table && $user_role) {
            $reset_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $reset_token);
            $expiry = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
            
            $upd = $conn->prepare("UPDATE $table SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $upd->bind_param('sss', $token_hash, $expiry, $reset_email);
            
            if ($upd->execute()) {
                sendPasswordResetEmail($reset_email, $reset_token);
                $_SESSION['flash_success'] = 'Password reset link generated!';
                $_SESSION['reset_link'] = 'http://localhost/capstone/reset_password.php?token=' . $reset_token . '&email=' . urlencode($reset_email) . '&role=' . urlencode($user_role);
                $_SESSION['reset_email'] = $reset_email;
                $_SESSION['reset_role'] = $user_role;
                header('Location: login.php');
                exit;
            } else {
                $_SESSION['flash_success'] = 'Error generating reset link.';
                header('Location: login.php');
                exit;
            }
            $upd->close();
        } else {
            $_SESSION['flash_success'] = 'If the email is registered, a reset link was sent.';
            header('Location: login.php');
            exit;
        }
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset']) && empty($error_message)) {
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';
    $token = $_POST['reset_token'] ?? '';
    $reset_email_post = strtolower(trim($_POST['reset_email'] ?? ''));
    $role = $_POST['reset_role'] ?? 'student';
    
    if (empty($password) || empty($password_confirm)) {
        $error_message = 'All fields required.';
    } elseif ($password !== $password_confirm) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } elseif (empty($token) || empty($reset_email_post)) {
        $error_message = 'Invalid reset request.';
    } else {
        $token_hash = hash('sha256', $token);
        $table = ($role === 'admin') ? 'admins' : (($role === 'teacher') ? 'teachers' : 'students');
        
        $stmt = $conn->prepare("SELECT id FROM $table WHERE reset_token = ? AND email = ? AND reset_token_expiry > NOW() LIMIT 1");
        $stmt->bind_param('ss', $token_hash, $reset_email_post);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE $table SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ? AND email = ?");
            $upd->bind_param('sss', $new_password_hash, $token_hash, $reset_email_post);
            
            if ($upd->execute()) {
                $_SESSION['flash_success'] = 'Password reset successful! Please log in.';
                header('Location: login.php');
                exit;
            } else {
                $error_message = 'Failed to reset password. Try again.';
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
    <link rel="stylesheet" href="login/SignLog.css">
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

        .role-tabs { display: flex; gap: 10px; margin-bottom: 20px; justify-content: center; }
        .role-tab { padding: 10px 20px; background-color: #f0f0f0; border: 2px solid #ddd; border-radius: 4px; cursor: pointer; font-weight: 500; transition: all 0.3s; }
        .role-tab.active { background-color: #667eea; color: white; border-color: #667eea; }
        .role-tab:hover { background-color: #e8e8e8; }
        .role-tab.active:hover { background-color: #5568d3; }
        .hidden-role { display: none; }
    </style>
</head>
<body>
<div class="container" id="container">
    <div class="form-container sign-up-container">
        <form method="POST">
            <h1 class="signin">Sign Up</h1>
        
            <input type="hidden" name="role" id="signupRole" value="student"/>

            <?php if ($error_message && isset($_POST['signup'])): ?>
                <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="infield">
                <input type="text" placeholder="Full Name" name="signup_name" required/>
                <label></label>
            </div>
            <div class="infield">
                <input type="email" placeholder="Email" name="signup_email" required/>
                <label></label>
            </div>
            <div class="infield">
                <input type="password" placeholder="Password (min 6 chars)" name="signup_password" required/>
                <label></label>
            </div>
            <button type="submit" name="signup" value="1">Sign Up</button>
        </form>
    </div>
    
    <div class="form-container log-in-container">
        <form method="POST">
            <h1 class="login">Log In</h1>
            


            <input type="hidden" name="role" id="loginRole" value="student"/>

            <?php if ($flash_success): ?>
                <div class="message-box success-message"><?php echo htmlspecialchars($flash_success); ?></div>
                <?php if ($reset_link): 
                    $token = substr($reset_link, strpos($reset_link, 'token=') + 6, 64);
                    $reset_email_display = $reset_email_session;
                    $reset_role = $_SESSION['reset_role'] ?? 'student';
                ?>
                    <div class="message-box success-message" style="padding: 15px;">
                        <strong>✓ Reset Link Generated:</strong><br><br>
                        <button type="button" onclick="openResetPasswordModal('<?php echo htmlspecialchars($token); ?>','<?php echo htmlspecialchars($reset_email_display); ?>','<?php echo htmlspecialchars($reset_role); ?>')" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; width: auto;">
                            Click Here to Reset Password
                        </button>
                    </div>
                <?php endif; ?>
                <?php 
                unset($_SESSION['flash_success']);
                unset($_SESSION['reset_link']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_role']);
                ?>
            <?php endif; ?>
            <?php if ($error_message && isset($_POST['login'])): ?>
                <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="infield">
                <input type="email" placeholder="Email" name="login_email" required/>
                <label></label>
            </div>
            <div class="infield">
                <input type="password" placeholder="Password" name="login_password" required/>
                <label></label>
            </div>
            <button type="submit" name="login" value="1">Log In</button>
            <div class="forgot-pwd-link">
                <a onclick="openForgotPasswordModal()">Forgot Password?</a>
            </div>

            <!-- Bottom Navigation Links -->
            <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 13px;">
                <a href="/capstone/teachers/teacher-login.php" style="color: #667eea; text-decoration: none; font-weight: 600; margin: 0 6px;">Login as Teacher</a>
               
            </div>
        </form>

        <!-- Quick login links for teacher/admin -->
        
    </div>
    
    <div class="overlay-container" id="overlayCon">
        <div class="overlay">
           <div class="overlay-panel overlay-left">
                    <img src="g2flogo.png">
                    <map name="logo">
                        <area shape="poly" coords="101,8,200,106,129,182,73,182,1,110" href="#">
                    </map>
                    <h2>Hello, Friend! </h2>
                    <p>Enter your personal details and start your journey with us.</p>
                </div>
            <div class="overlay-panel overlay-right">
                    <img src="g2flogo.png">
                    <map name="logo">
                        <area shape="poly" coords="101,8,200,106,129,182,73,182,1,110" href="#">
                    </map>
                     <h2>Welcome To Glorious God's Family Christian School, Inc.</h2>
                    <p>To keep connected, please log in with your personal info.</p>
                   
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
        <form method="POST" class="modal-form" action="config/email.php">
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
            <input type="hidden" name="reset_email" id="resetEmailInput" value=""/>
            <input type="hidden" name="reset_role" id="resetRoleInput" value=""/>
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

    container.classList.remove('right-panel-active');
    toggleSwitch.checked = false;

    toggleSwitch.addEventListener('change', () => {
        if (toggleSwitch.checked) {
            container.classList.add('right-panel-active');
        } else {
            container.classList.remove('right-panel-active');
        }
    });

    function selectSignupRole(role) {
        document.getElementById('signupRole').value = role;
        updateRoleTabs('signup', role);
    }

    function updateRoleTabs(form, role) {
        const tabs = document.querySelectorAll(`.${form}-container .role-tab`);
        tabs.forEach(tab => {
            if (tab.getAttribute('data-role') === role) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
    }

    function openForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.add('active');
    }

    function closeForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').classList.remove('active');
    }

    function openResetPasswordModal(token, email, role) {
        document.getElementById('resetTokenInput').value = token;
        document.getElementById('resetEmailInput').value = email || '';
        document.getElementById('resetRoleInput').value = role || 'student';
        document.getElementById('resetPasswordModal').classList.add('active');
    }

    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').classList.remove('active');
        document.getElementById('resetTokenInput').value = '';
        document.getElementById('resetEmailInput').value = '';
        document.getElementById('resetRoleInput').value = '';
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
