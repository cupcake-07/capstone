<?php

function sendPasswordResetEmail($studentEmail, $resetToken) {
    $resetLink = 'http://localhost/capstone/reset_password.php?token=' . $resetToken;
    
    $subject = 'Password Reset Request';
    $htmlBody = "
        <h2>Password Reset Request</h2>
        <p>You requested a password reset. Click the link below:</p>
        <p><a href='$resetLink'>Reset Password</a></p>
        <p>This link expires in 1 hour.</p>
    ";
    
    // Use PHP's mail function with proper headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ramboananzakalis@gmail.com\r\n";
    
    return mail($studentEmail, $subject, $htmlBody, $headers);
}
?>
