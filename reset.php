<?php
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'capstone_db';

// Create connection to MySQL server (without database first)
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
// require_once 'config/database.php';
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Invalid password reset link.');
}

 
    $decoded_email = base64_decode($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
   $new_password = $_POST['new_password'] ;
    $confirm_password = $_POST['confirm_password'];
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $decoded_email);
        $stmt->execute();
        $stmt->close();
        header('Location: login.php');
        exit;
    }
    





?>

<form method="POST">
    <h2>Reset Your Password</h2>
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <div>
        <label for="new_password">New Password:</label>
        <input type="password" id="new_password" name="new_password" required>
    </div>
    <div>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <button type="submit">Reset Password</button>
</form>