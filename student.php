<?php
// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// Redirect to login if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);

// Fetch user info - query should always use the session user ID
$user = null;
if ($stmt = $conn->prepare("SELECT id, name, username, email, grade_level, section, is_enrolled FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
        }
    } else {
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade, $fsection, $fis_enrolled);
            if ($stmt->fetch()) {
                $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade,'section'=>$fsection,'is_enrolled'=>$fis_enrolled];
            }
        }
    }
    $stmt->close();
}

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// sanitize for output
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');
$grade = htmlspecialchars($user['grade_level'] ?? 'Not Set');
$section = htmlspecialchars($user['section'] ?? 'N/A');
$studentIdDisplay = htmlspecialchars($user['id'] ?? '');
$isEnrolled = $user['is_enrolled'] ?? 1;
$statusText = $isEnrolled ? 'Enrolled' : 'Not Enrolled';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Student Profile — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
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

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a class="active" href="student.php">Profile</a>
        <a href="schedule.php">Schedule</a>
        <a href="grades.php">Grades</a>
        <a href="account.php">Account Balance</a>
        <a href="announcements.php">Announcements</a>
        <a href="student_settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Student</strong></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Student Profile</h1>
        <?php if (!$isEnrolled): ?>
          <div style="background-color: #fee; color: #c33; padding: 15px; border-radius: 4px; margin-top: 10px;">
            <strong>⚠️ Notice:</strong> Your enrollment status is currently <strong>Not Enrolled</strong>. Please contact the administration office.
          </div>
        <?php endif; ?>
      </header>

      <section class="profile-grid">
        <!-- LEFT: hero -->
        <aside class="hero">
          <div class="avatar-wrap">
            <div class="avatar-container">
              <img id="avatarImage" class="avatar" src="https://placehold.co/240x240/0f520c/dada18?text=Photo" alt="Student photo">
              <div class="avatar-overlay">
                <label for="avatarInput" class="upload-label">
                  <span class="upload-icon">📤</span>
                  <span class="upload-text">Upload Photo</span>
                </label>
                <input id="avatarInput" type="file" class="avatar-input" accept="image/*" />
              </div>
            </div>
            <div class="badge">Grade <?php echo $grade; ?></div>
          </div>

          <h2 class="name"><?php echo $name; ?></h2>
          <p class="role">Section A • Student ID: <strong><?php echo $studentIdDisplay; ?></strong></p>

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
              <div class="value status"><?php echo $statusText; ?></div>
            </div>
            <div class="row">
              <div class="label">Member Since</div>
              <div class="value">-</div>
            </div>
          </div>

          <div class="quick-buttons">
            <a class="btn ghost" href="student_settings.php">Edit Profile</a>
            <a class="btn" href="account.php">Account Balance</a>
          </div>

          <div class="small-card">
            <h4>Schedule (Today)</h4>
            <ul class="schedule">
              <li><span class="time">08:00</span> • Mathematics</li>
              <li><span class="time">09:00</span> • Science</li>
              <li><span class="time">10:00</span> • English</li>
            </ul>
            <a href="schedule.php" class="link-more">View full schedule →</a>
          </div>
        </aside>

        <!-- RIGHT: details, tiles -->
        <section class="content">
          <div class="cards">
            <div class="card large">
              <div class="card-head">
                <h3>Grades</h3>
                <div class="card-actions">
                  <button class="tab-btn active" data-tab="grades">Quarter View</button>
                  <button class="tab-btn" data-tab="avg">Average</button>
                </div>
              </div>

              <div class="card-body">
                <div class="tab-pane" id="grades">
                  <table class="grades-table">
                    <thead><tr><th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th></tr></thead>
                    <tbody>
                      <tr><td>Mathematics</td><td>88</td><td>90</td><td>85</td><td>87</td><td>88</td></tr>
                      <tr><td>Science</td><td>92</td><td>91</td><td>93</td><td>90</td><td>92</td></tr>
                      <tr><td>English</td><td>84</td><td>86</td><td>85</td><td>88</td><td>86</td></tr>
                    </tbody>
                  </table>
                </div>

                <div class="tab-pane hidden" id="avg">
                  <div class="big-metric">Overall Average: <strong>89.3%</strong></div>
                  <p class="muted">Great work — keep it up!</p>
                </div>
              </div>
            </div>

            <div class="card small-grid">
              <div class="mini-card">
                <div class="mini-head">Account Balance</div>
                <div class="mini-val">₱1,250.00</div>
                <a class="mini-link" href="account.php">Pay Now</a>
              </div>

              <div class="mini-card">
                <div class="mini-head">Status</div>
                <div class="mini-val status"><?php echo $statusText; ?></div>
                <a class="mini-link" href="#">Contact Admin</a>
              </div>

              <div class="mini-card">
                <div class="mini-head">Announcements</div>
                <div class="mini-val small">2 new</div>
                <a class="mini-link" href="announcements.php">See announcements</a>
              </div>

              <div class="mini-card">
                <div class="mini-head">Profile</div>
                <div class="mini-val small">Complete</div>
                <a class="mini-link" href="#">View details</a>
              </div>
            </div>
          </div>

          <div class="card announcements">
            <h3>Latest Announcements</h3>
            <ul class="ann-list">
              <li><strong>Oct 15</strong> — Parent-Teacher Conference on Oct 20.</li>
              <li><strong>Oct 10</strong> — School fee due date extended.</li>
            </ul>
            <a href="announcements.php" class="link-more">All announcements →</a>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year">2025</span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();

      document.querySelectorAll('.tab-btn').forEach(btn=>{
        btn.addEventListener('click', () => {
          document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
          btn.classList.add('active');
          const target = btn.dataset.tab;
          document.querySelectorAll('.tab-pane').forEach(p=> p.classList.add('hidden'));
          const el = document.getElementById(target);
          if(el) el.classList.remove('hidden');
        });
      });

      const avatarInput = document.getElementById('avatarInput');
      const avatarImage = document.getElementById('avatarImage');
      const userId = <?php echo $userId; ?>;

      // Load avatar from database
      fetch('api/get-avatar.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
          if (data.avatar) {
            avatarImage.src = data.avatar;
          }
        })
        .catch(err => console.log('No avatar found'));

      if (avatarInput) {
        avatarInput.addEventListener('change', (e) => {
          const file = e.target.files[0];
          if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (event) => {
              const imageData = event.target.result;
              avatarImage.src = imageData;
              
              // Save to database
              fetch('api/upload-avatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, avatar: imageData })
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  console.log('Avatar saved successfully');
                }
              })
              .catch(err => console.error('Error saving avatar:', err));
            };
            reader.readAsDataURL(file);
          }
        });
      }
    })();
  </script>
</body>
</html>
