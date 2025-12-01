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
        :root {
            --blue: #2b7cff;
            --pink: #ff4da6;
            --bg-start: #eaf4ff;
            --bg-end: #fff2f9;
            --card-bg: #ffffff;
            --text: #0f1723;
            --muted: #6b7280;
            --radius: 12px;
        }

        * { box-sizing: border-box; }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 28px;
            font-family: "Inter", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
        }

        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(255,255,255,0.99));
            padding: 36px;
            border-radius: var(--radius);
            width: 100%;
            max-width: 520px;
            box-shadow: 0 10px 30px rgba(17, 24, 39, 0.06), inset 0 1px 0 rgba(255,255,255,0.6);
            border: 1px solid rgba(255,77,166,0.07);
            position: relative;
            overflow: visible;
        }

        /* subtle top accent without modifying HTML */
        .card::before {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: -18px;
            width: 72px;
            height: 6px;
            border-radius: 8px;
            background: linear-gradient(90deg, var(--blue), var(--pink));
            box-shadow: 0 6px 18px rgba(43,124,255,0.08), 0 6px 18px rgba(255,77,166,0.06);
        }

        h1 {
            margin: 0 0 18px;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            color: var(--text);
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14px;
            line-height: 1.3;
        }

        .error {
            background: linear-gradient(180deg, rgba(255,77,166,0.06), rgba(255,77,166,0.03));
            color: #8b1034;
            border: 1px solid rgba(255,77,166,0.14);
        }

        .success {
            background: linear-gradient(180deg, rgba(43,124,255,0.06), rgba(43,124,255,0.03));
            color: #0f3c78;
            border: 1px solid rgba(43,124,255,0.14);
        }

        .infield { margin-bottom: 14px; }

        input[type="password"], input[type="text"], input[type="email"] {
            width: 100%;
            padding: 14px;
            border: 1px solid rgba(15, 23, 36, 0.08);
            border-radius: 10px;
            font-size: 15px;
            background: #fff;
            color: var(--text);
            outline: none;
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.12s ease;
            box-shadow: 0 6px 16px rgba(17, 24, 39, 0.03);
        }

        input::placeholder { color: #9ca3af; }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 8px 30px rgba(43,124,255,0.08), 0 2px 6px rgba(255,77,166,0.03);
            transform: translateY(-1px);
        }

        button {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(90deg, var(--blue), var(--pink));
            cursor: pointer;
            box-shadow: 0 8px 26px rgba(43,124,255,0.14);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            letter-spacing: 0.2px;
        }

        button:hover { transform: translateY(-2px); box-shadow: 0 14px 36px rgba(43,124,255,0.16); }

        button:active { transform: translateY(-1px); }

        .back { margin-top: 18px; text-align: center; font-size: 14px; }
        .back a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .back a:hover { color: var(--pink); }

        @media (max-width: 480px) {
            .card { padding: 22px; }
            input, button { padding: 12px; }
        }
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
