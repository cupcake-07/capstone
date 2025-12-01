<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

 require __DIR__ . '/../vendor/autoload.php';
//  var_dump($_POST);
// die();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reset_email = $_POST['reset_email'];
    sendPasswordResetEmail($reset_email);
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
