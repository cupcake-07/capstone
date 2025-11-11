<?php
require_once 'config/smtp_config.php';
require_once 'config/email.php';

// Test sending email
$result = sendPasswordResetEmail('rambonanzakalis@gmail.com', 'test-token-12345');

if ($result) {
    echo "✓ Email sent successfully!";
} else {
    echo "✗ Email failed to send. Check error log.";
}

// Check if config is loaded
echo "<br><br>";
echo "SMTP_HOST: " . SMTP_HOST . "<br>";
echo "SMTP_PORT: " . SMTP_PORT . "<br>";
echo "SMTP_FROM: " . SMTP_FROM . "<br>";
?>
