<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function generateOTP($email, $conn) {
    $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', time() + (5 * 60)); // 5 minutes expiry
    
    $del = $conn->prepare("DELETE FROM otp_records WHERE email = ?");
    $del->bind_param('s', $email);
    $del->execute();
    $del->close();
    
    $stmt = $conn->prepare("INSERT INTO otp_records (email, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $email, $otp_code, $expires_at);
    $result = $stmt->execute();
    $stmt->close();
    
    error_log("Generated OTP for {$email}: {$otp_code}, expires at {$expires_at}");
    
    return $otp_code;
}

function verifyOTP($email, $otp_code, $conn) {
    error_log("Attempting to verify OTP for email: {$email}, code: {$otp_code}");
    
    // First check if OTP exists and is valid
    $check_stmt = $conn->prepare("SELECT id, otp_code, expires_at FROM otp_records WHERE email = ? ORDER BY id DESC LIMIT 1");
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        error_log("No OTP record found for email: {$email}");
        $check_stmt->close();
        return false;
    }
    
    $row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    error_log("Found OTP record - Code in DB: {$row['otp_code']}, Expires: {$row['expires_at']}, Current time: " . date('Y-m-d H:i:s'));
    
    // Check if OTP matches
    if ($row['otp_code'] !== $otp_code) {
        error_log("OTP code mismatch: input={$otp_code}, db={$row['otp_code']}");
        return false;
    }
    
    // Check if OTP is expired
    $expires = new DateTime($row['expires_at']);
    $now = new DateTime();
    
    if ($now > $expires) {
        error_log("OTP expired: {$row['expires_at']} is before {$now->format('Y-m-d H:i:s')}");
        return false;
    }
    
    error_log("OTP verification SUCCESSFUL for email: {$email}");
    return true;
}

function sendOTPEmail($email, $otp_code) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rambonanzakalis@gmail.com';
        $mail->Password = 'mrwp tfru ksmg jvqq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('rambonanzakalis@gmail.com', 'Glorious God\'s Family School');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your One-Time Password (OTP)';
        
        $mail->Body = "
            <h2>Your OTP Code</h2>
            <p>Your one-time password is:</p>
            <h1 style='color: #667eea; letter-spacing: 5px; font-size: 32px;'>{$otp_code}</h1>
            <p>This code expires in 5 minutes.</p>
            <p>If you didn't request this, ignore this email.</p>
        ";
        
        $result = $mail->send();
        error_log("OTP email sent to {$email}");
        return true;
    } catch (Exception $e) {
        error_log("OTP email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPasswordResetEmail($recipientEmail, $resetToken) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rambonanzakalis@gmail.com';
        $mail->Password = 'mrwp tfru ksmg jvqq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('rambonanzakalis@gmail.com', 'Glorious God\'s Family School');
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        
        $resetLink = 'http://localhost/reset_password.php?token=' . $resetToken . '&email=' . urlencode($recipientEmail);
        
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
        error_log("Password reset email failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>
