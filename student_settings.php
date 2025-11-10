<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);

// fetch user - include section
$user = null;
if ($stmt = $conn->prepare("SELECT id, name, username, email, grade_level, section FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        $user = $res->fetch_assoc() ?? null;
    } else {
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade, $fsection);
            $stmt->fetch();
            $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade,'section'=>$fsection];
        }
    }
    $stmt->close();
}
if (!$user) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: login.php');
    exit;
}

// flash messages
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// simple helpers
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }

// Define $name early for use in navbar
$name = esc($user['name'] ?? 'Student');
$email = esc($user['email'] ?? '');
$grade = esc($user['grade_level'] ?? 'Not Set');

// Force Grade 1 students to Section A (match grades.php logic)
$rawSection = trim((string)($user['section'] ?? ''));
if ($grade === '1' || empty($rawSection) || strtolower($rawSection) === 'n/a') {
  $section = 'A';
} else {
  $section = esc($rawSection);
}

$studentId = esc($user['id'] ?? '');
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
    .settings-container {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 24px;
    }

    .settings-left {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .settings-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .panel {
      background: white;
      padding: 20px;
      border-radius: 6px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .panel h3 {
      margin: 0 0 8px;
      font-size: 16px;
      font-weight: 600;
      color: #1a1a1a;
    }

    .panel h4 {
      margin: 0 0 12px;
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .muted {
      color: #666;
      font-size: 13px;
      margin: 0;
      line-height: 1.4;
    }

    .form-row {
      margin-bottom: 14px;
    }

    .form-row:last-child {
      margin-bottom: 0;
    }

    .form-row label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
      color: #1a1a1a;
      font-size: 12px;
    }

    .settings-input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #d0d0d0;
      border-radius: 4px;
      font-size: 13px;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
      background: #fafafa;
      color: #1a1a1a;
    }

    .settings-input:focus {
      outline: none;
      border-color: #333;
      background: white;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
    }

    .actions {
      display: flex;
      gap: 8px;
      margin-top: 16px;
    }

    .btn {
      padding: 8px 14px;
      background: #1a1a1a;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
    }

    .btn:hover {
      background: #000;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn.ghost {
      background: white;
      border: 1px solid #d0d0d0;
      color: #1a1a1a;
    }

    .btn.ghost:hover {
      background: #f5f5f5;
      border-color: #999;
    }

    .msg {
      padding: 10px 12px;
      border-radius: 4px;
      margin-bottom: 14px;
      font-size: 12px;
    }

    .msg.success {
      background: #f0f9ff;
      color: #1a5f1a;
      border: 1px solid #c8e6c9;
    }

    .msg.error {
      background: #fff5f5;
      color: #8b0000;
      border: 1px solid #ffcdd2;
    }

    .hero {
      background: white;
      padding: 20px;
      border-radius: 6px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.08);
      text-align: center;
      position: sticky;
      top: 20px;
    }

    .avatar-wrap {
      margin-bottom: 14px;
    }

    .avatar-container {
      width: 100px;
      height: 100px;
      margin: 0 auto 10px;
      position: relative;
      overflow: hidden;
      border-radius: 6px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .badge {
      display: inline-block;
      background: #f0f0f0;
      padding: 3px 10px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      color: #1a1a1a;
      border: 1px solid #d0d0d0;
    }

    .name {
      margin: 0 0 4px;
      font-size: 18px;
      font-weight: 700;
      color: #1a1a1a;
    }

    .role {
      margin: 0 0 14px;
      font-size: 11px;
      color: #666;
      line-height: 1.4;
    }

    .card {
      background: #f9f9f9;
      padding: 14px;
      border-radius: 4px;
      margin-bottom: 14px;
      border: 1px solid #e8e8e8;
    }

    .card.info .row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      font-size: 12px;
      border-bottom: 1px solid #eee;
    }

    .card.info .row:last-child {
      border-bottom: none;
    }

    .card.info .label {
      font-weight: 600;
      color: #666;
    }

    .card.info .value {
      color: #1a1a1a;
      font-weight: 500;
      text-align: right;
    }

    hr {
      margin: 14px 0;
      border: none;
      border-top: 1px solid #e0e0e0;
    }

    .more-actions {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .more-actions .btn {
      width: 100%;
      text-align: center;
    }

    @media (max-width: 900px) {
      .settings-container {
        grid-template-columns: 1fr;
      }

      .settings-grid {
        grid-template-columns: 1fr;
      }

      .hero {
        position: relative;
        top: auto;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <div class="navbar-logo">GGF</div>
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

  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <main class="main">
      <header class="header">
        <h1>Settings</h1>
        <p class="muted">Manage your profile, password and account preferences.</p>
      </header>

      <div class="settings-container">
        <div class="settings-left">
          <div class="panel">
            <h3>Account Settings</h3>
            <p class="muted">Quick links to update frequently used account preferences.</p>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
              <a class="btn" href="student.php">View Profile</a>
              <a class="btn ghost" href="account.php">Billing</a>
            </div>
          </div>

          <div class="settings-grid">
            <!-- Edit Details -->
            <div class="panel">
              <h3>Edit Details</h3>
              <?php if ($flash_success): ?><div class="msg success"><?php echo esc($flash_success); ?></div><?php endif; ?>
              <?php if ($flash_error): ?><div class="msg error"><?php echo esc($flash_error); ?></div><?php endif; ?>

              <form method="POST" action="update_profile.php">
                <div class="form-row">
                  <label for="fullName">Full Name</label>
                  <input id="fullName" name="name" class="settings-input" type="text" value="<?php echo esc($user['name']); ?>" required />
                </div>

                <div class="form-row">
                  <label for="email">Email</label>
                  <input id="email" name="email" class="settings-input" type="email" value="<?php echo esc($user['email']); ?>" required />
                </div>

                <div class="form-row">
                  <label for="username">Username (optional)</label>
                  <input id="username" name="username" class="settings-input" type="text" value="<?php echo esc($user['username'] ?? ''); ?>" />
                </div>

                <div class="actions">
                  <button class="btn" type="submit">Save Changes</button>
                  <a class="btn ghost" href="student_settings.php">Cancel</a>
                </div>
              </form>
            </div>

            <!-- Change Password -->
            <div class="panel">
              <h3>Change Password</h3>
              <form method="POST" action="change_password.php">
                <div class="form-row">
                  <label for="currentPassword">Current Password</label>
                  <input id="currentPassword" name="current_password" class="settings-input" type="password" required />
                </div>
                <div class="form-row">
                  <label for="newPassword">New Password</label>
                  <input id="newPassword" name="new_password" class="settings-input" type="password" minlength="6" required />
                </div>
                <div class="form-row">
                  <label for="confirmPassword">Confirm New Password</label>
                  <input id="confirmPassword" name="confirm_password" class="settings-input" type="password" minlength="6" required />
                </div>

                <div class="actions">
                  <button class="btn" type="submit">Change Password</button>
                  <a class="btn ghost" href="student_settings.php">Cancel</a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Right: Profile Summary -->
        <aside class="hero">
          <div class="avatar-wrap">
            <div class="avatar-container">
              <img id="settingsAvatar" class="avatar" src="https://placehold.co/240x240/0f520c/dada18?text=Photo" alt="Student photo">
            </div>
            <div class="badge"><?php echo $grade; ?></div>
          </div>

          <h2 class="name"><?php echo $name; ?></h2>
          <p class="role">Section <?php echo $section; ?><br>ID: <strong><?php echo $studentId; ?></strong></p>

          <div class="card info">
            <div class="row">
              <div class="label">Email</div>
              <div class="value"><?php echo $email; ?></div>
            </div>
            <div class="row">
              <div class="label">Section</div>
              <div class="value"><?php echo $section; ?></div>
            </div>
            <div class="row">
              <div class="label">Status</div>
              <div class="value"><?php echo esc(($user['grade_level'])? 'Enrolled':'Not Enrolled'); ?></div>
            </div>
          </div>

          <div class="more-actions">
            <a class="btn ghost" href="student.php">← Back to Profile</a>
          </div>
        </aside>
      </div>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();

      const settingsAvatar = document.getElementById('settingsAvatar');
      const userId = <?php echo $userId; ?>;

      fetch('api/get-avatar.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
          if (data.avatar) {
            settingsAvatar.src = data.avatar;
          }
        })
        .catch(err => console.log('No avatar found'));
    })();
  </script>
</body>
</html>