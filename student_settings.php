<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);

// fetch user
$user = null;
if ($stmt = $conn->prepare("SELECT id, name, username, email, grade_level FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        $user = $res->fetch_assoc() ?? null;
    } else {
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade);
            $stmt->fetch();
            $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade];
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
$studentId = esc($user['id'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Student Settings — Profile Settings</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <style>
    /* Page-specific layout only — rely on css/student_v2.css for sidebar styling */
    section.profile-grid {
      display: grid;
      grid-template-columns: 1fr 360px; /* main content + right hero column */
      gap: 28px;
      align-items: start;
      margin-top: 6px;
    }

    .content { width: 100%; }

    .settings-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      align-items: start;
    }

    /* Page-local panels / forms / avatar (do NOT override .side or .page-wrapper) */
    .panel {
      background: #fff;
      padding: 18px;
      border-radius: 8px;
      box-shadow: 0 6px 20px rgba(12,16,18,0.04);
      position: relative;
      z-index: 2;
    }
    .panel h3 { margin: 0 0 12px; font-size: 18px; }
    .form-row { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
    .form-row label { font-size:13px; color:#444; }
    .form-row input, .form-row select { padding:10px 12px; border:1px solid #e9e9e9; border-radius:8px; font-size:14px; background:#fff; }
    .actions { display:flex; gap:10px; margin-top:8px; }
    .msg { padding:10px 12px; border-radius:6px; margin-bottom:12px; display:none; }
    .msg.success { background:#e9fff0; color:#0b6b2f; border:1px solid #c9efcf; }
    .msg.error { background:#fff3f3; color:#7a1414; border:1px solid #f3caca; }

    .hero { position: static; margin: 0; }
    .avatar-wrap { display:flex; flex-direction:column; align-items:center; gap:12px; padding-top:6px; }
    .avatar-container {
      width: 300px;
      height: 300px;
      max-width: 100%;
      border-radius:12px;
      overflow:hidden;
      margin: 0 auto;
      background:#0f520c;
      display:flex; align-items:center; justify-content:center;
      box-shadow: 0 8px 0 rgba(0,0,0,0.03);
    }
    .avatar-container img.avatar { width:100%; height:100%; object-fit:cover; display:block; }

    .badge { margin-top:6px; display:inline-block; padding:6px 10px; border-radius:20px; background:#202020; color:#fff; font-size:13px; }

    @media (max-width: 1000px) {
      section.profile-grid { grid-template-columns: 1fr; }
      .settings-grid { grid-template-columns: 1fr; }
      .avatar-container { width: 220px; height:220px; }
      .panel { box-shadow: 0 4px 14px rgba(12,16,18,0.04); }
    }
  </style>
</head>
<body>
  <!-- TOP NAVBAR (same structure as student.php) -->
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
    <aside class="side">
      <nav class="nav">
        <a href="student.php">Profile</a>
        <a href="schedule.php">Schedule</a>
        <a href="grades.php">Grades</a>
        <a href="account.php">Account Balance</a>
        <a href="announcements.php">Announcements</a>
        <a class="active" href="student_settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Student</strong></div>
    </aside>

    <main class="main">
      <header class="header">
        <h1>Settings</h1>
        <p class="muted">Manage your profile, password and account preferences.</p>
      </header>

      <section class="profile-grid">
        <div class="content">
          <div class="panel" style="margin-bottom:20px;">
            <h3>Account Settings</h3>
            <p class="muted">Quick links to update frequently used account preferences.</p>
            <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
              <a class="btn" href="student.php">View Profile</a>
              <a class="btn ghost" href="account.php">Billing</a>
            </div>
          </div>

          <div class="settings-grid">
            <!-- Left: Edit details -->
            <div class="panel">
              <h3>Edit Details</h3>
              <?php if ($flash_success): ?><div class="msg success" style="display:block;"><?php echo esc($flash_success); ?></div><?php endif; ?>
              <?php if ($flash_error): ?><div class="msg error" style="display:block;"><?php echo esc($flash_error); ?></div><?php endif; ?>

              <!-- Replace action with your server endpoint -->
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
                  <label for="grade">Grade Level</label>
                  <select id="grade" name="grade_level" class="settings-input">
                    <option value="">Not Set</option>
                    <?php for($g=1;$g<=12;$g++): $label="Grade $g"; ?>
                      <option <?php echo ($user['grade_level']==$label)?'selected':''; ?>><?php echo $label; ?></option>
                    <?php endfor; ?>
                  </select>
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

            <!-- Right: Change password -->
            <aside class="panel">
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

              <hr style="margin:14px 0;border:none;border-top:1px solid #f0f0f0;">

              <h4 style="margin:0 0 10px;">More</h4>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <a class="btn ghost" href="logout.php?redirect=student">Log out</a>
              </div>
            </aside>
          </div>
        </div>

        <!-- Right column: profile summary -->
        <aside class="hero" style="max-width:420px;">
          <div class="avatar-wrap">
            <div class="avatar-container">
              <img id="settingsAvatar" class="avatar" src="https://placehold.co/240x240/0f520c/dada18?text=Photo" alt="Student photo">
            </div>
            <div class="badge"><?php echo $grade; ?></div>
          </div>

          <h2 class="name"><?php echo $name; ?></h2>
          <p class="role">Section A • Student ID: <strong><?php echo $studentId; ?></strong></p>

          <div class="card info">
            <div class="row"><div class="label">Email</div><div class="value"><?php echo $email; ?></div></div>
            <div class="row"><div class="label">Status</div><div class="value status"><?php echo esc(($user['grade_level'])? 'Enrolled':'Not Enrolled'); ?></div></div>
          </div>

          <div style="margin-top:12px;"><a class="btn ghost" href="student.php">Back to Profile</a></div>
        </aside>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();

      const settingsAvatar = document.getElementById('settingsAvatar');
      const userId = <?php echo $userId; ?>;

      // Load avatar from database
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