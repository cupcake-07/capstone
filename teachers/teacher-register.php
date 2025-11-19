<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';
$name = '';
$email = '';
$phone = '';
$subject = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($address)) {
        $error = 'Name, email, password, and address are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    }

    if (empty($error)) {
        // Use prepared statement to check email existence
        $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $error = 'Email already registered';
            }
            $checkStmt->close();
        } else {
            $error = 'Database error. Please try again.';
        }
    }

    if (empty($error)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Insert the new fields address into teachers table
        $stmt = $conn->prepare("INSERT INTO teachers (name, email, password, phone, subject, address) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param('ssssss', $name, $email, $hashed_password, $phone, $subject, $address);
            if ($stmt->execute()) {
                $success = 'Account created successfully! Redirecting to login...';
                header('refresh:2;url=teacher-login.php');
                $name = $email = $phone = $subject = $address = '';
            } else {
                $error = 'Error creating account. Please try again.';
            }
            $stmt->close();
        } else {
            $error = 'Database error. Please try again.';
        }
    }
}

// Define subjects array
$subjects = ['Mathematics', 'English', 'Science', 'Social Studies', 'Physical Education', 'Arts', 'Music', 'Computer'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Teacher Register - School Management System</title>
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
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25), 0 2px 8px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 480px;
        }

        .register-container h1 {
            text-align: center;
            color: #1a1a1a;
            margin-bottom: 8px;
            font-size: 24px;
            font-weight: 700;
        }

        .register-container .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .register-container p {
            text-align: center;
            color: #666;
            margin-bottom: 28px;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #1a1a1a;
            font-weight: 600;
            font-size: 12px;
        }

        .form-group input,
        .form-group select {
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

        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 14px;
            background: #fafafa;
            color: #1a1a1a;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            min-height: 80px;
            resize: vertical;
        }

        .form-group input[type="date"] {
            padding: 8px 12px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #333;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-register {
            width: 100%;
            padding: 12px;
             background: #b83fa3ff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            margin-top: 8px;
        }

        .btn-register:hover {
           background: #4f65e0ff;
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

        .success {
            background: #f0f9ff;
            color: #1a5f1a;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            border: 1px solid #c8e6c9;
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
            margin-bottom: 4px;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="subtitle">Teacher Portal</div>
        <h1>Create Account</h1>
        <p>Glorious God's Family Christian School</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required />
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+63 9XX XXX XXXX" value="<?php echo htmlspecialchars($phone ?? ''); ?>" />
                </div>

                <div class="form-group">
                    <label for="subject">Subject *</label>
                    <select id="subject" name="subject" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subj): ?>
                            <option value="<?php echo htmlspecialchars($subj); ?>" <?php echo ($subject === $subj) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subj); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- New Address Field -->
            <div class="form-group">
                <label for="address">Address *</label>
                <textarea id="address" name="address" placeholder="Enter your address" required><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" placeholder="At least 6 characters" minlength="6" required />
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" minlength="6" required />
            </div>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="info-box">
            <strong>Required fields are marked with *</strong>
            Please ensure all information is accurate for proper account setup.
        </div>

        <div class="auth-links">
            <p>Already have an account?</p>
            <a href="teacher-login.php">Login here</a>
            <p style="margin-top: 16px;">
                <a href="../login.php">Login as Student</a> | 
                <a href="../admin-login.php">Login as Admin</a>
            </p>
        </div>
    </div>
</body>
</html>
