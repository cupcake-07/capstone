<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/otp.php';

session_start();

// Display success or error message
if (isset($_SESSION['email_sent'])) {
    echo '<div style="background-color: #d4edda; color: #155724; padding: 30px; margin: 30px auto; border-radius: 8px; border: 2px solid #c3e6cb; text-align: center; max-width: 500px; font-size: 18px;">
        <strong style="font-size: 24px;">✓ Success!</strong><br><br> Password reset email has been sent to your inbox. Please check your email for the reset link.
        <br><br>
        <button onclick="history.back()" style="background-color: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">← Go Back</button>
    </div>';
    unset($_SESSION['email_sent']);
}

if (isset($_SESSION['email_error'])) {
    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 30px; margin: 30px auto; border-radius: 8px; border: 2px solid #f5c6cb; text-align: center; max-width: 500px; font-size: 18px;">
        <strong style="font-size: 24px;">✗ Error!</strong><br><br> Failed to send email. Please try again later.
        <br><br>
        <button onclick="history.back()" style="background-color: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">← Go Back</button>
    </div>';
    unset($_SESSION['email_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $reset_email = strtolower(trim($_POST['reset_email'] ?? ''));
    
    if (empty($reset_email)) {
        $_SESSION['email_error'] = 'Email is required.';
    } else {
        require_once 'database.php';
        
        $user_role = null;
        $table = null;
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
                $_SESSION['email_sent'] = true;
            } else {
                $_SESSION['email_error'] = true;
            }
            $upd->close();
        } else {
            $_SESSION['email_sent'] = true; // Security: don't reveal if email exists
        }
    }
    
    header('Location: ../login.php');
    exit;
}

function sendPasswordResetEmail($recipientEmail, $resetToken) {
    $mail = new PHPMailer(true);
    $encodedEmail = base64_encode($recipientEmail);
    $resetLink = "http://localhost/capstone/reset.php?token={$encodedEmail}";

    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rambonanzakalis@gmail.com'; // Replace with your Gmail
        $mail->Password = 'mrwp tfru ksmg jvqq';      // Replace with your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Email Details
        $mail->setFrom('rambonanzakalis@gmail.com', 'Glorious God\'s Family School');
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        
        // $resetLink = 'http://localhost/capstone/reset_password.php?token=' . $resetToken . '&email=' . urlencode($recipientEmail);
        
         $mail->Body = "
          <h2>Password Reset Request</h2>
            <p>Click the link below to reset your password:</p>
          <a href='{$resetLink}' style='background-color: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Reset Password</a>
            <p>This link expires in 24 hours.</p>
            <p>If you didn't request this, ignore this email.</p>
         ";


        
        
            
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>
