<?php
require_once 'config/database.php';

$email = 'rambonanzakalis@gmail.com';

// Check what's in the database
$stmt = $conn->prepare("SELECT reset_token, reset_token_expiry FROM students WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

echo "<pre>";
echo "Email: " . $email . "\n";
echo "Token in DB: " . ($row['reset_token'] ?? 'NULL') . "\n";
echo "Expiry in DB: " . ($row['reset_token_expiry'] ?? 'NULL') . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";

$stmt->close();
?>
