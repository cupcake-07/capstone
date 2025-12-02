<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/otp.php';

$error = '';
$flash_success = $_SESSION['flash_success'] ?? '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_type']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    header('Location: admin-login.php');
    exit;
}

// Define multiple admin accounts
$admin_accounts = [
    [
        'email' => 'rambonanzakalis@gmail.com',
        'password' => 'admin123',
        'name' => 'Admin User'
    ],
    [
        'email' => 'manager@school.com',
        'password' => 'manager123',
        'name' => 'Manager User'
    ]
];

// Handle Admin Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $authenticated = false;
        $user_data = null;
        
        // Check against hardcoded accounts
        foreach ($admin_accounts as $account) {
            if ($account['email'] === $email && $account['password'] === $password) {
                $authenticated = true;
                $user_data = $account;
                break;
            }
        }
        
        // Also check database for additional admins
        if (!$authenticated) {
            $stmt = $conn->prepare("SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $authenticated = true;
                    $user_data = $user;
                }
            }
            $stmt->close();
        }
        
        if ($authenticated) {
            // Generate and send OTP instead of immediately logging in
            $otp_code = generateOTP($email, $conn);
            $otp_sent = sendOTPEmail($email, $otp_code);
            
            if ($otp_sent) {
                $_SESSION['pending_admin_id'] = $user_data['id'] ?? 1;
                $_SESSION['pending_admin_type'] = 'admin';
                $_SESSION['pending_admin_name'] = $user_data['name'];
                $_SESSION['pending_admin_email'] = $user_data['email'] ?? $email;
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_sent'] = true;
                
                $_SESSION['flash_success'] = 'OTP sent to your email. Please verify.';
                header('Location: admin-login.php');
                exit;
            } else {
                $error = 'Failed to send OTP. Please try again.';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

// Handle OTP Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp_input = trim($_POST['otp_code'] ?? '');
    $otp_email = $_SESSION['otp_email'] ?? '';
    
    if (empty($otp_input) || empty($otp_email)) {
        $error = 'OTP verification failed - empty input.';
    } else {
        $verification_result = verifyOTP($otp_email, $otp_input, $conn);
        
        if ($verification_result) {
            $_SESSION['admin_id'] = $_SESSION['pending_admin_id'] ?? null;
            $_SESSION['admin_type'] = $_SESSION['pending_admin_type'] ?? null;
            $_SESSION['admin_name'] = $_SESSION['pending_admin_name'] ?? null;
            $_SESSION['admin_email'] = $_SESSION['pending_admin_email'] ?? null;
            
            if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_type'])) {
                $error = 'Session error - failed to set user data.';
            } else {
                unset($_SESSION['pending_admin_id']);
                unset($_SESSION['pending_admin_type']);
                unset($_SESSION['pending_admin_name']);
                unset($_SESSION['pending_admin_email']);
                unset($_SESSION['otp_email']);
                unset($_SESSION['otp_sent']);
                unset($_SESSION['flash_success']);
                
                session_regenerate_id(true);
                header('Location: admin.php');
                exit;
            }
        } else {
            $error = 'Invalid or expired OTP. Please try again.';
        }
    }
}

// Handle Forgot Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $reset_email = strtolower(trim($_POST['reset_email'] ?? ''));
    
    if (empty($reset_email)) {
        $error = 'Email is required.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $reset_email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $reset_token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $reset_token);
            $expiry = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
            
            $upd = $conn->prepare("UPDATE admins SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $upd->bind_param('sss', $token_hash, $expiry, $reset_email);
            
            if ($upd->execute()) {
                sendPasswordResetEmail($reset_email, $reset_token, $conn);
                $_SESSION['flash_success'] = 'Password reset link sent to your email!';
                $_SESSION['reset_link'] = 'http://localhost/reset_password.php?token=' . $reset_token . '&email=' . urlencode($reset_email);
                $_SESSION['reset_email'] = $reset_email;
                header('Location: admin-login.php');
                exit;
            }
            $upd->close();
        } else {
            $_SESSION['flash_success'] = 'If the email is registered, a reset link was sent.';
            header('Location: admin-login.php');
            exit;
        }
        $stmt->close();
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $password = $_POST['new_password'] ?? '';
    $password_confirm = $_POST['confirm_password'] ?? '';
    $token = $_POST['reset_token'] ?? '';
    $reset_email_post = strtolower(trim($_POST['reset_email'] ?? ''));
    
    if (empty($password) || empty($password_confirm)) {
        $error = 'All fields required.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (empty($token) || empty($reset_email_post)) {
        $error = 'Invalid reset request.';
    } else {
        $token_hash = hash('sha256', $token);
        
        $stmt = $conn->prepare("SELECT id FROM admins WHERE reset_token = ? AND email = ? AND reset_token_expiry > NOW() LIMIT 1");
        $stmt->bind_param('ss', $token_hash, $reset_email_post);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ? AND email = ?");
            $upd->bind_param('sss', $new_password_hash, $token_hash, $reset_email_post);
            
            if ($upd->execute()) {
                $_SESSION['flash_success'] = 'Password reset successful! Please log in.';
                header('Location: admin-login.php');
                exit;
            } else {
                $error = 'Failed to reset password. Try again.';
            }
            $upd->close();
        } else {
            $error = 'Reset link expired or invalid.';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin Login - School Management System</title>
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
            font-size: 28px;
            font-weight: 700;
        }

        .login-container .subtitle {
            text-align: center;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-container p {
            text-align: center;
            color: #555;
            margin-bottom: 28px;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #111;
            font-weight: 700;
            font-size: 15px;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #bdbdbd;
            border-radius: 6px;
            font-size: 16px;
            background: #fff;
            color: #111;
            font-family: 'Inter', sans-serif;
        }

        .form-group input::placeholder {
            font-size: 15px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.06);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #b83fa3ff;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #4f65e0ff;
        }

        .error {
            background: #fff6f6;
            color: #a00000;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 15px;
            border: 1px solid #a00000;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 15px;
            border: 1px solid #c3e6cb;
        }

        .forgot-pwd-link {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-pwd-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 400px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .close-btn:hover {
            color: #000;
        }

        .modal-form .form-group {
            margin-bottom: 15px;
        }

        .modal-form button {
            width: 100%;
            padding: 10px;
            background-color: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
        }

        .modal-form button:hover {
            background-color: #5568d3;
        }

        /* OTP Modal */
        .otp-modal-content {
            background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%);
            color: white;
        }

        .otp-modal-content h2 {
            color: white;
        }

        .otp-timer-display {
            text-align: center;
            margin: 20px 0;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 12px;
            border-radius: 6px;
        }

        .otp-timer-display p {
            color: #b0b0b0;
            margin: 0 0 8px 0;
            font-size: 13px;
        }

        .otp-timer-display .timer {
            color: #ffd700;
            font-weight: bold;
            font-size: 28px;
            margin: 0;
        }

        .otp-modal-content input {
            background: #f9f9f9;
            color: #000;
        }

        .otp-modal-content button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="subtitle">Admin Panel</div>
        <h1>School Management</h1>
        <p>Glorious God's Family Christian School</p>

        <?php if ($flash_success): ?>
            <div class="success-message"><?php echo htmlspecialchars($flash_success); ?></div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

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

            <button type="submit" name="login" value="1" class="btn-login">Login as Admin</button>
        </form>

        <div class="forgot-pwd-link">
            <a onclick="openForgotPasswordModal()">Forgot Password?</a>
        </div>
    </div>

    <!-- OTP Verification Modal -->
    <div id="otpModal" class="modal">
        <div class="modal-content otp-modal-content">
            <div class="modal-header">
                <h2>Verify OTP</h2>
            </div>
            <form method="POST" class="modal-form">
                <?php if ($error && isset($_POST['verify_otp'])): ?>
                    <div class="error" style="margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <p style="text-align: center; margin-bottom: 15px; font-size: 15px;">Enter the 6-digit OTP sent to your email</p>
                
                <div class="otp-timer-display">
                    <p>Time Remaining:</p>
                    <p class="timer" id="otpTimer">5:00</p>
                </div>
                
                <div class="form-group">
                    <input type="text" placeholder="Enter 6-digit OTP" name="otp_code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus/>
                </div>
                <button type="submit" name="verify_otp" value="1">Verify OTP & Login</button>
            </form>
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
                <div class="form-group">
                    <input type="email" placeholder="Enter your email" name="reset_email" required/>
                </div>
                <button type="submit" name="forgot_password" value="1">Send Reset Link</button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reset Password</h2>
                <button class="close-btn" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <form method="POST" class="modal-form">
                <input type="hidden" name="reset_token" id="resetTokenInput" value=""/>
                <input type="hidden" name="reset_email" id="resetEmailInput" value=""/>
                <div class="form-group">
                    <input type="password" placeholder="New Password (min 6 chars)" name="new_password" required/>
                </div>
                <div class="form-group">
                    <input type="password" placeholder="Confirm Password" name="confirm_password" required/>
                </div>
                <button type="submit" name="reset" value="1">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
        function openForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.add('active');
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('active');
        }

        function openResetPasswordModal(token, email) {
            document.getElementById('resetTokenInput').value = token;
            document.getElementById('resetEmailInput').value = email || '';
            document.getElementById('resetPasswordModal').classList.add('active');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
            document.getElementById('resetTokenInput').value = '';
            document.getElementById('resetEmailInput').value = '';
        }

        // Show OTP modal automatically if OTP was sent
        <?php if (isset($_SESSION['otp_sent']) && $_SESSION['otp_sent'] === true): ?>
            window.addEventListener('load', function() {
                const otpModal = document.getElementById('otpModal');
                if (otpModal) {
                    otpModal.classList.add('active');
                    startOTPTimer();
                    const otpInput = document.querySelector('#otpModal input[name="otp_code"]');
                    if (otpInput) {
                        setTimeout(() => otpInput.focus(), 100);
                    }
                }
            });
            <?php unset($_SESSION['otp_sent']); ?>
        <?php endif; ?>

        // OTP Timer countdown (5 minutes)
        function startOTPTimer() {
            let timeLeft = 300;
            const timerDisplay = document.getElementById('otpTimer');
            
            const timerInterval = setInterval(function() {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                if (timerDisplay) {
                    timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    if (timerDisplay) {
                        timerDisplay.textContent = 'Expired';
                        timerDisplay.style.color = '#ff6b6b';
                    }
                    const submitBtn = document.querySelector('#otpModal button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                        submitBtn.textContent = 'OTP Expired - Please Login Again';
                    }
                }
            }, 1000);
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
