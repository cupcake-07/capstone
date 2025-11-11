<?php

function sendPasswordResetEmail($studentEmail, $resetToken) {
    $resetLink = 'http://localhost/capstone/reset_password.php?token=' . $resetToken;
    
    $subject = 'Password Reset Request - Glorious Gods Family School';
    $htmlBody = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #667eea;'>Password Reset Request</h2>
            <p>You requested a password reset for your account.</p>
            <p><a href='$resetLink' style='background-color: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Reset Password</a></p>
            <p>Or copy: $resetLink</p>
            <p><strong>Expires in 1 hour.</strong></p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: rambonanzakalis@gmail.com\r\n";
    $headers .= "Reply-To: rambonanzakalis@gmail.com\r\n";
    
    $result = mail($studentEmail, $subject, $htmlBody, $headers);
    
    if (!$result) {
        error_log("Mail failed for: $studentEmail");
    } else {
        error_log("Mail sent to: $studentEmail");
    }
    
    return $result;
}
?>
