<?php
// Ensure same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once 'config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$name = htmlspecialchars($_SESSION['user_name'] ?? 'Student', ENT_QUOTES);
$message = '';
$messageType = ''; // 'success' or 'error'

// Fetch user info from DB
$user = null;
$stmt = $conn->prepare("SELECT id, name, email FROM students WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();
    }
    $stmt->close();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newName = trim($_POST['name'] ?? '');
    $newEmail = strtolower(trim($_POST['email'] ?? ''));
    
    if (empty($newName) || empty($newEmail)) {
        $message = 'Name and email are required.';
        $messageType = 'error';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format.';
        $messageType = 'error';
    } else {
        // Check if email is taken by another user
        $checkStmt = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param('si', $newEmail, $userId);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                $message = 'Email already in use.';
                $messageType = 'error';
            } else {
                $updateStmt = $conn->prepare("UPDATE students SET name = ?, email = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param('ssi', $newName, $newEmail, $userId);
                    if ($updateStmt->execute()) {
                        $_SESSION['user_name'] = $newName;
                        $_SESSION['user_email'] = $newEmail;
                        $message = 'Profile updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating profile.';
                        $messageType = 'error';
                    }
                    $updateStmt->close();
                }
            }
            $checkStmt->close();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All password fields are required.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'New password must be at least 6 characters.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM students WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            
            if (!password_verify($currentPassword, $row['password'])) {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param('si', $hashedPassword, $userId);
                    if ($updateStmt->execute()) {
                        $message = 'Password changed successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error changing password.';
                        $messageType = 'error';
                    }
                    $updateStmt->close();
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Settings</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ===== SETTINGS PAGE STYLING ===== */
    .settings-tabs { 
      display: flex; 
      gap: 0; 
      border-bottom: 2px solid #000; 
      margin-bottom: 20px;
      background: #f5f5f5;
    }
    
    .settings-tabs button { 
      background: transparent;
      border: none; 
      padding: 14px 24px; 
      font-size: 14px; 
      font-weight: 600; 
      color: #666; 
      cursor: pointer; 
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .settings-tabs button:hover { 
      color: #000;
      background: #e8e8e8;
    }
    
    .settings-tabs button.active { 
      color: #000; 
      background: #fff;
      border-bottom-color: #000;
    }

    .tab-content { 
      display: none; 
    }
    
    .tab-content.active { 
      display: block;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* ===== FORM STYLING ===== */
    .form-group { 
      margin-bottom: 20px; 
    }
    
    .form-group label { 
      display: block; 
      margin-bottom: 8px; 
      font-weight: 600; 
      color: #000; 
    }
    
    .form-group input, 
    .form-group textarea { 
      width: 100%; 
      padding: 10px 12px; 
      border: 1px solid #ddd; 
      border-radius: 4px; 
      font-size: 14px;
      transition: all 0.2s ease;
      font-family: 'Inter', sans-serif;
    }
    
    .form-group input:focus, 
    .form-group textarea:focus { 
      outline: none; 
      border-color: #000; 
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
    }

    .form-row { 
      display: grid; 
      grid-template-columns: 1fr 1fr; 
      gap: 15px; 
    }

    /* ===== BUTTON STYLING ===== */
    .btn-primary { 
      background: #4d57afff; 
      color: #fff; 
      padding: 11px 22px; 
      border: none; 
      border-radius: 4px; 
      cursor: pointer; 
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .btn-primary:hover { 
      background: #333;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-secondary { 
      background: #f0f0f0; 
      color: #000; 
      padding: 11px 22px; 
      border: 1px solid #ddd;
      border-radius: 4px; 
      cursor: pointer; 
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
      border-color: #999;
    }

    /* ===== ALERT STYLING ===== */
    .alert { 
      padding: 12px 15px; 
      border-radius: 4px; 
      margin-bottom: 20px;
      border-left: 4px solid #000;
    }
    
    .alert-success { 
      background: #f0f0f0; 
      color: #000;
      border-left-color: #000;
    }
    
    .alert-error { 
      background: #f5f5f5; 
      color: #000;
      border-left-color: #000;
    }

    /* ===== INFO BOX STYLING ===== */
    .info-box { 
      background: #f9f9f9; 
      padding: 15px; 
      border-radius: 4px; 
      border-left: 4px solid #000; 
      margin-bottom: 20px;
    }
    
    .info-box strong { 
      color: #000; 
    }

    .info-box ul {
      margin: 10px 0 0 0;
      padding-left: 20px;
    }

    .info-box li {
      color: #333;
      margin-bottom: 6px;
      font-size: 13px;
    }

    /* ===== CARD STYLING ===== */
    .card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 6px;
    }

    .card.large {
      border: 1px solid #ddd;
      background: #fff;
    }

    .card-head h3 {
      color: #000;
    }

    .danger-zone-btn {
      background: #4d57afff; 
      color: #fff;
      padding: 11px 22px;
      border-radius: 4px;
      text-decoration: none;
      font-weight: 600;
      display: inline-block;
      transition: all 0.3s ease;
    }

    .danger-zone-btn:hover {
      background: #333;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* ===== WRAPPER STYLING ===== */
    .settings-wrapper {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      border: 1px solid #ddd;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
      }

      .settings-tabs {
        gap: 5px;
      }

      .settings-tabs button {
        padding: 12px 16px;
        font-size: 13px;
      }
    }
  </style>
</head>
<body>
  <!-- TOP NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
      <img src="g2flogo.png" alt="Glorious God's Family Logo" style="height: 40px; margin-left:-20px"  />
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo $name; ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Account Settings</h1>
        <p style="color:#666;margin-top:5px;">Manage your account preferences and security</p>
      </header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <!-- Message Alert -->
          <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
              <?php echo htmlspecialchars($message); ?>
            </div>
          <?php endif; ?>

          <!-- Settings Tabs -->
          <div class="settings-wrapper">
            <div class="settings-tabs">
              <button class="tab-btn active" data-tab="profile">Profile Information</button>
              <button class="tab-btn" data-tab="password">Change Password</button>
              <button class="tab-btn" data-tab="security">Security & Privacy</button>
            </div>

            <!-- Profile Information Tab -->
            <div id="profile" class="tab-content active">
              <form method="POST">
                <div class="form-group">
                  <label>Full Name</label>
                  <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required/>
                </div>

                <div class="form-group">
                  <label>Email Address</label>
                  <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required/>
                </div>

                <div style="display: flex; gap: 10px;">
                  <button type="submit" name="update_profile" class="btn-primary">Save Changes</button>
                  <button type="reset" class="btn-secondary">Cancel</button>
                </div>
              </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password" class="tab-content">
              <form method="POST">
                <div class="info-box">
                  <strong>Password Requirements:</strong>
                  <ul>
                    <li>At least 6 characters long</li>
                    <li>Must be different from your current password</li>
                    <li>Use a mix of letters, numbers, and symbols for better security</li>
                  </ul>
                </div>

                <div class="form-group">
                  <label>Current Password</label>
                  <input type="password" name="current_password" placeholder="Enter your current password" required/>
                </div>

                <div class="form-group">
                  <label>New Password</label>
                  <input type="password" name="new_password" placeholder="Enter new password (min 6 chars)" required/>
                </div>

                <div class="form-group">
                  <label>Confirm New Password</label>
                  <input type="password" name="confirm_password" placeholder="Confirm new password" required/>
                </div>

                <div style="display: flex; gap: 10px;">
                  <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                  <button type="reset" class="btn-secondary">Cancel</button>
                </div>
              </form>
            </div>

            <!-- Security & Privacy Tab -->
            <div id="security" class="tab-content">
              <div class="info-box">
                <strong>Session Information</strong><br/>
                Your session is secure and active. You are logged in as <strong><?php echo htmlspecialchars($user['email'] ?? ''); ?></strong>
              </div>

              <div class="info-box">
                <strong>Security Tips:</strong>
                <ul>
                  <li>Never share your password with anyone</li>
                  <li>Log out from public computers after use</li>
                  <li>Change your password regularly</li>
                  <li>Use a strong, unique password</li>
                </ul>
              </div>

              <div class="card large" style="margin-top: 20px; background: #f9f9f9;">
                <div class="card-head">
                  <h3 style="color: #000;">Danger Zone</h3>
                </div>
                <div class="card-body">
                  <p style="color: #666; margin-bottom: 15px;">
                    Logging out will end your current session. You can log back in anytime with your credentials.
                  </p>
                  <a href="logout.php" class="danger-zone-btn">Log Out</a>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    const year = document.getElementById('year');
    if(year) year.textContent = new Date().getFullYear();

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
          tab.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-btn').forEach(b => {
          b.classList.remove('active');
        });
        
        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        btn.classList.add('active');
      });
    });
  </script>
</body>
</html>