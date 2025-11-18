<?php
// Use same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
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
if ($stmt = $conn->prepare("SELECT id, name, username, email, grade_level, section, is_enrolled, avg_score FROM students WHERE id = ? LIMIT 1")) {
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
            $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade, $fsection, $fis_enrolled, $favg_score);
            if ($stmt->fetch()) {
                $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade,'section'=>$fsection,'is_enrolled'=>$fis_enrolled, 'avg_score' => $favg_score];
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

// --- REPLACE: sanitize/display values and add raw/normalized keys for lookups ---
/*
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');
$grade = htmlspecialchars($user['grade_level'] ?? 'Not Set');
$rawSection = trim((string)($user['section'] ?? ''));
if ($rawSection === '' || strtolower($rawSection) === 'n/a') {
    $section = 'A';
} else {
    $section = htmlspecialchars($rawSection);
}
*/
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');

// raw values for lookup (not HTML-escaped)
$gradeKey = trim((string)($user['grade_level'] ?? ''));
$sectionRaw = trim((string)($user['section'] ?? ''));

// normalize section for lookup and fallback rules
$normalizedSection = strtoupper($sectionRaw);
if ($normalizedSection === '' || in_array(strtolower($normalizedSection), ['n/a','na','-','none','tbd'], true)) {
    $normalizedSection = 'A';
}

// display-safe values
$grade = $gradeKey !== '' ? htmlspecialchars($gradeKey) : 'Not Set';
$section = htmlspecialchars($normalizedSection);

$studentIdDisplay = htmlspecialchars($user['id'] ?? '');
$isEnrolled = $user['is_enrolled'] ?? 1;
$statusText = $isEnrolled ? 'Enrolled' : 'Not Enrolled';

// --- ADD: helper to load schedule for specific grade+section ---
// Remove this old function as it's replaced by the API call in JS
// function load_schedule_for($grade, $section) { ... }

// New: determine student grade & section server-side for the sneak peek card (use already fetched $user)
$studentGrade = trim((string)($user['grade_level'] ?? ''));
$studentSection = trim((string)($user['section'] ?? ''));

// Normalize section fallback: empty or misc values => 'A'
$normalizedSectionLookup = strtoupper($studentSection);
if ($normalizedSectionLookup === '' || in_array(strtolower($normalizedSectionLookup), ['n/a','na','-','none','tbd'], true)) {
    $normalizedSectionLookup = 'A';
}
$studentSection = $normalizedSectionLookup;

// Friendly label map (same as admin)
$gradeLabels = [
    'K1' => 'Kinder 1',
    'K2' => 'Kinder 2',
    '1'  => 'Grade 1',
    '2'  => 'Grade 2',
    '3'  => 'Grade 3',
    '4'  => 'Grade 4',
    '5'  => 'Grade 5',
    '6'  => 'Grade 6',
];
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
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

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
            <!-- ADD id for JS updates -->
            <div class="badge" id="badgeGrade">Grade <?php echo $grade; ?></div>
          </div>

          <h2 class="name"><?php echo $name; ?></h2>
          <!-- role line will be updated by JS; keep class but use innerHTML in script -->
          <p class="role" id="roleLine">Section <?php echo $section; ?> • Student ID: <strong><?php echo $studentIdDisplay; ?></strong></p>

          <div class="card info">
            <div class="row">
              <div class="label">Email</div>
              <div class="value"><?php echo $email; ?></div>
            </div>
            <div class="row">
              <div class="label">Section</div>
              <!-- ADD id for JS updates -->
              <div class="value" id="profileSection"><?php echo $section; ?></div>
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

            <!-- Remove the old server-side schedule code and replace with the API-based sneak peek -->
            <div id="sneakPeekSchedule">
                <div style="font-weight:700; margin-bottom:8px;">Schedule (Today)</div>
                <div id="sneakPeekContent" style="color:#666; font-size:13px;">
                    Loading today's schedule...
                </div>
                <a id="sneakPeekLink" href="schedule.php" style="display:block; margin-top:8px; color:#1884d4; text-decoration:none; font-weight:600;">View full schedule →</a>
            </div>
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
                  
                </div>
              </div>

              <div class="card-body">
                <div class="tab-pane" id="grades">
                  <table class="grades-table">
                    <thead>
                      <tr><th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th></tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($displayGrades)): ?>
                        <?php foreach ($displayGrades as $subject => $vals): ?>
                          <tr class="subject-row" data-subject="<?php echo htmlspecialchars($subject); ?>">
                            <td style="cursor:pointer;"><?php echo htmlspecialchars($subject); ?></td>
                            <td><?php echo isset($vals['q1']) ? htmlspecialchars($vals['q1']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q2']) ? htmlspecialchars($vals['q2']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q3']) ? htmlspecialchars($vals['q3']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q4']) ? htmlspecialchars($vals['q4']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['final']) ? htmlspecialchars($vals['final']) . '%' : '-'; ?></td>
                          </tr>

                          <!-- details row: all inputted grades for this subject -->
                          <tr class="subject-details hidden" data-subject="<?php echo htmlspecialchars($subject); ?>">
                            <td colspan="6" style="padding:6px 24px;">
                              <?php if (!empty($gradesEntries[$subject])): ?>
                                <div style="font-size:13px;color:#666;margin-bottom:6px;">All recorded grades for <strong><?php echo htmlspecialchars($subject); ?></strong>:</div>
                                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                  <thead>
                                    <tr style="color:#666;">
                                      <th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;">Quarter</th>
                                      <th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;">Assignment</th>
                                      <th style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">Score</th>
                                      <th style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">Date</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php foreach ($gradesEntries[$subject] as $entry): ?>
                                      <tr>
                                        <td style="padding:6px 8px;"><?php echo $entry['quarter'] > 0 ? 'Q'.$entry['quarter'] : '-'; ?></td>
                                        <td style="padding:6px 8px;"><?php echo htmlspecialchars($entry['assignment']); ?></td>
                                        <td style="padding:6px 8px;text-align:right;"><?php echo number_format(floatval($entry['score']),1); ?>%</td>
                                        <td style="padding:6px 8px;text-align:right;"><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              <?php else: ?>
                                <div style="color:#999;">No individual grades recorded for this subject.</div>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="6">No grades available</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="tab-pane hidden" id="avg">
                  <div class="big-metric">Overall Average: <strong><?php echo htmlspecialchars($overallAvgDisplay); ?></strong></div>
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
            <h3>Latest Announcements & Events</h3>
            <!-- Replace server-side DB query with client-side loader that uses the same API as teachers -->
            <ul class="ann-list" id="ann-list">
              <li class="loading-message">Loading announcements...</li>
            </ul>
            <a href="announcements.php" class="link-more">All announcements & events →</a>
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
    })();
  </script>

  <!-- Remove the old client-side schedule script as it's replaced -->
  <!-- <script> // Client-side "today" schedule renderer ... </script> -->

  <!-- ...existing code... -->

  <script>
  // Minimal client-side: normalize grade variants and fetch today's schedule
  (function() {
      function normalizeGradeCode(code){
          if (!code) return '';
          const c = String(code).trim().toUpperCase().replace(/\s+/g, '');
          if (c === 'K1' || c === 'KINDER1') return 'K1';
          if (c === 'K2' || c === 'KINDER2') return 'K2';
          const m = c.match(/^[1-6]$/);
          if (m) return c;
          return c;
      }

      // Server-populated values (now set from $user)
      const studentGradeRaw = '<?php echo addslashes($studentGrade ?? ''); ?>';
      const studentSection = '<?php echo addslashes(strtoupper($studentSection ?? '')); ?>';
      const studentGrade = normalizeGradeCode(studentGradeRaw) || '';

      const gradeLabels = <?php echo json_encode($gradeLabels); ?>;
      const displayGradeLabel = gradeLabels[studentGrade] || (studentGrade ? ('Grade ' + studentGrade) : 'Grade');

      const contentEl = document.getElementById('sneakPeekContent');
      const linkEl = document.getElementById('sneakPeekLink');

      if (!studentGrade || !studentSection) {
          contentEl.textContent = 'No grade/section found for the current user.';
          linkEl.style.display = 'none';
          return;
      }

      // Build API URL
      const url = new URL('api/get-schedule.php', window.location.origin);
      url.searchParams.set('grade', studentGrade);
      url.searchParams.set('section', studentSection);

      fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(json => {
          if (!json || !json.success) {
              contentEl.textContent = 'No schedule available for ' + displayGradeLabel + ' Section ' + studentSection + '.';
              return;
          }
          const today = json.today || [];
          if (!today || today.length === 0) {
              contentEl.textContent = `No classes scheduled for today in ${displayGradeLabel} Section ${studentSection}.`;
              // link to full schedule is kept
              return;
          }

          // Build a small list
          const list = document.createElement('div');
          list.style.display = 'flex';
          list.style.flexDirection = 'column';
          list.style.gap = '8px';
          today.forEach(item => {
              const row = document.createElement('div');
              row.style.display = 'flex';
              row.style.justifyContent = 'space-between';
              row.style.fontSize = '13px';
              // left: period/time subject
              const left = document.createElement('div');
              left.style.color = '#111';
              left.style.fontWeight = '600';
              left.innerHTML = (item.period ? ('P' + item.period + ' • ') : '') + (item.time || '');
              const right = document.createElement('div');
              right.style.color = '#444';
              right.style.fontWeight = '500';
              right.style.textAlign = 'right';
              const teacherSub = [];
              if (item.subject) teacherSub.push(item.subject);
              if (item.teacher) teacherSub.push('<span style="font-weight:400;color:#666;">' + item.teacher + '</span>');
              right.innerHTML = teacherSub.join(' • ');
              row.appendChild(left);
              row.appendChild(right);
              list.appendChild(row);
          });

          contentEl.innerHTML = ''; // clear
          contentEl.appendChild(list);

          // Keep the "View full schedule" link; optionally set to a detailed view link with query params
          linkEl.href = 'schedule.php?grade=' + encodeURIComponent(studentGrade) + '&section=' + encodeURIComponent(studentSection);
          linkEl.style.display = 'inline-block';
      })
      .catch(err => {
          console.error('Failed to load schedule', err);
          contentEl.textContent = 'Failed to load schedule.';
      });
  })();
  </script>

</body>
</html>
