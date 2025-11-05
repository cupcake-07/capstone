<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$email = strtolower(trim($_POST['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Please enter a valid email address.';
    header('Location: forgot_password.php');
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT id, name FROM students WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    $_SESSION['flash_success'] = 'If that email exists, a reset link has been sent.';
    header('Location: forgot_password.php');
    exit;
}
$stmt->bind_result($userId, $userName);
$stmt->fetch();
$stmt->close();

// Create reset token
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Create password_resets table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expiry DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($createTable);

// Store token in DB
$stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $userId, $token, $expiry);
if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['flash_error'] = 'Error generating reset link.';
    header('Location: forgot_password.php');
    exit;
}
$stmt->close();

// Create reset link
$resetLink = "http://localhost/capstone/reset_password.php?token=" . $token;

// Send email using PHPMailer
try {
    // Check if PHPMailer is installed
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
    }
    
    require __DIR__ . '/vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // SMTP configuration (Gmail)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'rambonanzakalis@gmail.com';        // ← PUT YOUR GMAIL HERE
    $mail->Password   = 'tyke ukrk tggw sbuh';         // ← PUT YOUR 16-CHAR APP PASSWORD HERE
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Email content
    $mail->setFrom('rambonanzakalis@gmail.com', 'Glorious God Family Christian School');  // ← SAME GMAIL HERE
    $mail->addAddress($email, $userName);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request';
    $mail->Body    = "
        <h2>Password Reset Request</h2>
        <p>Hi $userName,</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='$resetLink'>Reset Password</a></p>
        <p>This link expires in 1 hour.</p>
        <p>If you didn't request this, ignore this email.</p>
    ";
    $mail->AltBody = "Hi $userName,\n\nClick the link to reset your password:\n\n$resetLink\n\nThis link expires in 1 hour.";
    
    $mail->send();
    $_SESSION['flash_success'] = 'Password reset link has been sent to your email!';
    
} catch (Exception $e) {
    error_log('Email send error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Error sending email: ' . $e->getMessage();
}

header('Location: forgot_password.php');
exit;
