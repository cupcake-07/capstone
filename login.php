<?php
require_once 'config/database.php';

// Start session and enable error reporting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Check for flash messages from redirect
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$error_message = '';     // <-- added: ensure defined
$success_message = '';   // <-- added: ensure defined

// verify $conn
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log('[login.php] Missing or invalid $conn in config/database.php');
    $error_message = 'Database connection not available. Check config/database.php.';
    // ...existing code continues but signup/login will fail with this message ...
}

// helper: check if column exists
function has_column($conn, $table, $column) {
    $dbRow = $conn->query("SELECT DATABASE()")->fetch_row();
    $dbname = $dbRow[0] ?? '';
    if (!$dbname) return false;
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('sss', $dbname, $table, $column);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('[login.php] has_column execute error: ' . $stmt->error);
            $stmt->close();
            return false;
        }
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    } else {
        error_log('[login.php] has_column prepare error: ' . $conn->error);
        return false;
    }
}

$has_username = has_column($conn, 'students', 'username');

// --- ADDED: verify $conn is valid and required columns exist ---
$db_ready = true;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $db_ready = false;
    $error_message = 'Database connection not available. Check config/database.php.';
    error_log('[login.php] Missing or invalid $conn in config/database.php');
} else {
    // check required columns in students table
    $requiredCols = ['id','name','username','email','password','grade_level'];
    $placeholders = implode(',', array_fill(0, count($requiredCols), '?'));
    // Build a safe IN query by querying INFORMATION_SCHEMA for each column separately
    $missing = [];
    foreach ($requiredCols as $col) {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'students' AND COLUMN_NAME = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $dbRow = $conn->query("SELECT DATABASE()")->fetch_row();
            $dbname = $dbRow[0] ?? '';
            $stmt->bind_param('ss', $dbname, $col);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 0) $missing[] = $col;
            } else {
                error_log('[login.php] INFORMATION_SCHEMA execute error: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log('[login.php] INFORMATION_SCHEMA prepare error: ' . $conn->error);
        }
    }

    if (!empty($missing)) {
        $db_ready = false;
        // Provide a clear instruction with the SQL to run (do not run automatically if privileges may be missing)
        $error_message = 'Database schema incomplete: missing column(s): ' . implode(', ', $missing) .
                         '. Please add them. Example SQL to run (adjust types if needed):';
        // Build SQL hints
        $sqlHints = [];
        if (in_array('username', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `username` VARCHAR(100) NULL;";
            $sqlHints[] = "ALTER TABLE `students` ADD UNIQUE (`username`);";
        }
        if (in_array('grade_level', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `grade_level` VARCHAR(50) NULL;";
        }
        if (in_array('name', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `name` VARCHAR(255) NULL;";
        }
        if (in_array('email', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `email` VARCHAR(255) NULL;";
        }
        if (in_array('password', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `password` VARCHAR(255) NULL;";
        }
        if (in_array('id', $missing)) {
            $sqlHints[] = "ALTER TABLE `students` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY;";
        }

        // append small readable hint to error_message (won't expose raw DB errors)
        $error_message .= ' ' . implode(' ', $sqlHints);
        error_log('[login.php] Missing students columns: ' . implode(',', $missing));
    }
}
// --- END ADDED ---

// Handle Sign Up
// Only proceed if DB is ready
if ($db_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $name = trim($_POST['signup_name'] ?? '');
    $username = trim($_POST['signup_username'] ?? '');
    if (!$has_username) $username = null; // ignore provided username if DB doesn't have the column
    $email = strtolower(trim($_POST['signup_email'] ?? '')); // normalize email
    $password = $_POST['signup_password'] ?? '';
    
    // Basic validation
    if (empty($name) || ( $has_username && empty($username) ) || empty($email) || empty($password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
        if ($stmt === false) {
            $error_message = 'Database error.';
            error_log('[login.php] prepare email check failed: ' . $conn->error);
        } else {
            $stmt->bind_param('s', $email);
            if (!$stmt->execute()) {
                $error_message = 'Database error.';
                error_log('[login.php] execute email check failed: ' . $stmt->error);
                $stmt->close();
            } else {
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error_message = 'Email already registered.';
                    $stmt->close();
                } else {
                    $stmt->close();
                    // If DB has username, check username uniqueness
                    if ($has_username) {
                        $stmt = $conn->prepare("SELECT id FROM students WHERE username = ?");
                        if ($stmt === false) {
                            $error_message = 'Database error.';
                            error_log('[login.php] prepare username check failed: ' . $conn->error);
                        } else {
                            $stmt->bind_param('s', $username);
                            if (!$stmt->execute()) {
                                $error_message = 'Database error.';
                                error_log('[login.php] execute username check failed: ' . $stmt->error);
                                $stmt->close();
                            } else {
                                $stmt->store_result();
                                if ($stmt->num_rows > 0) {
                                    $error_message = 'Username already taken.';
                                    $stmt->close();
                                } else {
                                    $stmt->close();
                                    // proceed to insert with username
                                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                                    $stmt = $conn->prepare("INSERT INTO students (name, username, email, password, grade_level) VALUES (?, ?, ?, ?, ?)");
                                    if ($stmt === false) {
                                        $error_message = 'Database error.';
                                        error_log('[login.php] prepare insert with username failed: ' . $conn->error);
                                    } else {
                                        $grade_level = 'Not Set';
                                        $stmt->bind_param('sssss', $name, $username, $email, $hashed_password, $grade_level);
                                        if ($stmt->execute()) {
                                            if ($stmt->affected_rows > 0) {
                                                // { changed code } set flash and redirect to login
                                                $_SESSION['flash_success'] = 'Account created successfully! Please log in.';
                                                header('Location: login.php');
                                                exit;
                                            } else {
                                                $error_message = 'Account was not created. Please try again.';
                                                error_log('[login.php] insert executed but affected_rows=0: ' . $stmt->error);
                                            }
                                        } else {
                                            $error_message = 'Error creating account. Please try again.';
                                            error_log('[login.php] execute insert with username failed: ' . $stmt->error);
                                        }
                                        $stmt->close();
                                    }
                                }
                            }
                        }
                    } else {
                        // DB has no username column — insert without username
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare("INSERT INTO students (name, email, password, grade_level) VALUES (?, ?, ?, ?)");
                        if ($stmt === false) {
                            $error_message = 'Database error.';
                            error_log('[login.php] prepare insert without username failed: ' . $conn->error);
                        } else {
                            $grade_level = 'Not Set';
                            $stmt->bind_param('ssss', $name, $email, $hashed_password, $grade_level);
                            if ($stmt->execute()) {
                                if ($stmt->affected_rows > 0) {
                                    // { changed code } set flash and redirect to login
                                    $_SESSION['flash_success'] = 'Account created successfully! Please log in.';
                                    header('Location: login.php');
                                    exit;
                                } else {
                                    $error_message = 'Account was not created. Please try again.';
                                    error_log('[login.php] insert without username affected_rows=0: ' . $stmt->error);
                                }
                            } else {
                                $error_message = 'Error creating account. Please try again.';
                                error_log('[login.php] execute insert without username failed: ' . $stmt->error);
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
} elseif (!$db_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    // prevent signup attempts when DB schema is incomplete
    // preserve $error_message set above so it shows in the form
    error_log('[login.php] Signup blocked due to DB not ready. Message: ' . $error_message);
}

// Handle Log In
// Only proceed if DB is ready
if ($db_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = strtolower(trim($_POST['login_email'] ?? '')); // normalize email
    $password = $_POST['login_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        // Try student login
        $stmt = $conn->prepare("SELECT id, name, email, password FROM students WHERE email = ? LIMIT 1");
        if ($stmt === false) {
            $error_message = 'Database error.';
            error_log('[login.php] prepare login failed: ' . $conn->error);
        } else {
            $stmt->bind_param('s', $email);
            if (!$stmt->execute()) {
                $error_message = 'Database error.';
                error_log('[login.php] execute login failed: ' . $stmt->error);
                $stmt->close();
            } else {
                $user = null;
                // get_result fallback
                if (method_exists($stmt, 'get_result')) {
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows === 1) {
                        $user = $res->fetch_assoc();
                    }
                } else {
                    // fallback: bind_result + fetch
                    $stmt->store_result();
                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($fid, $fname, $femail, $fpassword);
                        if ($stmt->fetch()) {
                            $user = [
                                'id' => $fid,
                                'name' => $fname,
                                'email' => $femail,
                                'password' => $fpassword
                            ];
                        }
                    }
                }

                if ($user) {
                    $pwMatch = password_verify($password, $user['password']);
                    // Debug log: whether user found and whether password matches (DO NOT log the password/hash)
                    error_log("[login.php] Login attempt: email={$email}, user_id={$user['id']}, password_match=" . ($pwMatch ? '1' : '0'));
                    // Add this right after "$user = [...]"
                    error_log("[login.php] DEBUG - Stored hash: " . substr($user['password'], 0, 20) . "...");
                    error_log("[login.php] DEBUG - Hash length: " . strlen($user['password']));
                    if ($pwMatch) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_type'] = 'student';
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        header('Location: student.php');
                        exit;
                    } else {
                        $error_message = 'Invalid email or password.';
                    }
                 } else {
                     $error_message = 'Invalid email or password.';
                     error_log('[login.php] No user found for email=' . $email);
                 }
                 $stmt->close();
            }
        }
    }
} elseif (!$db_ready && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    error_log('[login.php] Login blocked due to DB not ready. Message: ' . $error_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in || Sign up form</title>
    <link rel="stylesheet" href="css/SignLog.css">
    <style>
        .message-box {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
<div class="container" id="container">
        <div class="form-container sign-up-container">
            <form method="POST" action="">
                <h1>Sign Up</h1>
                
                <?php if ($error_message && isset($_POST['signup'])): ?>
                    <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <div class="infield">
                    <input type="text" placeholder="Full Name" name="signup_name" value="<?php echo htmlspecialchars($_POST['signup_name'] ?? ''); ?>" required/>
                    <label></label>
                </div>
                <div class="infield">
                    <input type="text" placeholder="Username" name="signup_username" value="<?php echo htmlspecialchars($_POST['signup_username'] ?? ''); ?>" required/>
                    <label></label>
                </div>
                <div class="infield">
                    <input type="email" placeholder="Email" name="signup_email" value="<?php echo htmlspecialchars($_POST['signup_email'] ?? ''); ?>" required/>
                    <label></label>
                </div>
                <div class="infield">
                    <input type="password" placeholder="Password (min 6 chars)" name="signup_password" required/>
                    <label></label>
                </div>
                <button type="submit" name="signup" value="1">Sign Up</button>
            </form>
        </div>
        
        <div class="form-container log-in-container">
            <form method="POST" action="">
                <h1>Log in</h1>
                
                <!-- { changed code } show flash success on login side -->
                <?php if ($flash_success): ?>
                    <div class="message-box success-message"><?php echo htmlspecialchars($flash_success); ?></div>
                <?php endif; ?>
                
                <?php if ($flash_error): ?>
                    <div class="message-box error-message"><?php echo htmlspecialchars($flash_error); ?></div>
                <?php endif; ?>
                
                <?php if ($error_message && isset($_POST['login'])): ?>
                    <div class="message-box error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <div class="infield">
                    <input type="email" placeholder="Email" name="login_email" value="<?php echo htmlspecialchars($_POST['login_email'] ?? ''); ?>" required/>
                    <label></label>
                </div>
                <div class="infield">
                    <input type="password" placeholder="Password" name="login_password" required/>
                    <label></label>
                </div>
                <a href="forgot_password.php" class="forgot">Forgot your password?</a>
                <button type="submit" name="login" value="1">Log In</button>
                
                <div style="margin-top: 15px; text-align: center; font-size: 12px;">
                    <p><strong></strong>  <strong></strong></p>
                </div>
            </form>
        </div>
        
        <div class="overlay-container" id="overlayCon">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <img src="logo.png" usemap="#logo" onerror="this.onerror=null;this.src='https://placehold.co/100x100/0f520c/dada18?text=Logo'">
                    <map name="logo">
                        <area shape="poly" coords="101,8,200,106,129,182,73,182,1,110" href="#">
                    </map>
                    <h2>Welcome Back!</h2>
                    <p>To keep connected, please log in with your personal info.</p>
                </div>
                
                <div class="overlay-panel overlay-right">
                    <img src="logo.png" usemap="#logo" onerror="this.onerror=null;this.src='https://placehold.co/100x100/dada18/0f520c?text=Logo'">
                    <map name="logo">
                        <area shape="poly" coords="101,8,200,106,129,182,73,182,1,110" href="#">
                    </map>
                    <h2>Hello, Student!</h2>
                    <p>Enter your personal details and start your journey with us.</p>
                </div>
            </div>
        </div>
        
        <div class="toggle-switch-wrapper">
            <label class="toggle-switch">
                <input type="checkbox" id="toggleForms">
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>
    
    <script>
        const container = document.getElementById('container');
        const toggleSwitch = document.getElementById('toggleForms');

        container.classList.remove('right-panel-active');

        toggleSwitch.addEventListener('change', () => {
            if (toggleSwitch.checked) {
                container.classList.add('right-panel-active');
            } 
            else {
                container.classList.remove('right-panel-active');
            }
        });
    </script>
    
</body>
</html>
