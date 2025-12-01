<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/autoload.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset_email = $_POST['reset_email'];
    if (sendPasswordResetEmail($reset_email)) {
        $_SESSION['email_sent'] = true;
    } else {
        $_SESSION['email_error'] = true;
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

function sendPasswordResetEmail($recipientEmail) {
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
