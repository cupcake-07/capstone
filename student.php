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

// sanitize for output
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');
$grade = htmlspecialchars($user['grade_level'] ?? 'Not Set');

// --- CHANGED: Stop forcing Grade 1 to Section A; only default to 'A' when DB value is empty ---
$rawSection = trim((string)($user['section'] ?? ''));
if ($rawSection === '' || strtolower($rawSection) === 'n/a') {
    // default to A only when no section set in DB
    $section = 'A';
} else {
    $section = htmlspecialchars($rawSection);
}

$studentIdDisplay = htmlspecialchars($user['id'] ?? '');
$isEnrolled = $user['is_enrolled'] ?? 1;
$statusText = $isEnrolled ? 'Enrolled' : 'Not Enrolled';

// --- ADD: helper to load schedule for specific grade+section ---
function load_schedule_for($grade, $section) {
    $schedules_file = __DIR__ . '/teachers/data/schedules.json';
    if (!file_exists($schedules_file)) return null;
    $json = file_get_contents($schedules_file);
    $data = json_decode($json, true);
    $key = $grade . '_' . $section;
    if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
    return null;
}

// After verifying session and loading $user from students table (ensure avg_score is selected)
$userId = intval($_SESSION['user_id']);

// Fetch saved grades for this student and prepare structured data for display
$gradesStmt = $conn->prepare("SELECT assignment, score, created_at FROM grades WHERE student_id = ? ORDER BY assignment ASC, created_at ASC");
$gradesBySubject = []; // [subject][quarter] => [scores...]
$gradesEntries = [];   // subject => [ ['quarter'=>n,'score'=>x,'date'=>d,'assignment'=>s], ... ]
$subjectsOrder = []; // keep subjects order
if ($gradesStmt) {
    $gradesStmt->bind_param('i', $userId);
    $gradesStmt->execute();
    $res = $gradesStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assignment = trim($row['assignment']);
        $score = floatval($row['score']);
        $date = $row['created_at'];
        
        // parse "Q{n} - Subject" pattern
        if (preg_match('/^Q\s*([1-4])\s*-\s*(.+)$/i', $assignment, $m)) {
            $quarter = intval($m[1]);
            $subject = trim($m[2]);
        } else {
            // fallback: no quarter in assignment, treat as quarter 0 (other)
            $quarter = 0;
            $subject = trim($assignment);
        }
        
        if ($subject === '') $subject = 'General';
        
        // Track subject order (only on first occurrence)
        if (!isset($gradesBySubject[$subject])) {
            $gradesBySubject[$subject] = [];
            $subjectsOrder[] = $subject;
        }
        
        if (!isset($gradesBySubject[$subject][$quarter])) {
            $gradesBySubject[$subject][$quarter] = [];
        }
        
        // Add score to this quarter for this subject
        $gradesBySubject[$subject][$quarter][] = $score;

        // collect detailed entries (preserve date and assignment)
        if (!isset($gradesEntries[$subject])) $gradesEntries[$subject] = [];
        $gradesEntries[$subject][] = [
            'quarter' => $quarter,
            'score' => $score,
            'date' => $date,
            'assignment' => $assignment
        ];
    }
    $gradesStmt->close();
}

// helper average
function avgArray($arr) {
	$arr = array_filter($arr, function($v){ return is_numeric($v); });
	if (count($arr) === 0) return null;
	return array_sum($arr) / count($arr);
}

// compute per-subject quarter averages and final averages
$displayGrades = [];
$allScores = [];
foreach ($subjectsOrder as $subj) {
	$subjectData = $gradesBySubject[$subj];
	$row = [];
	$quarterTotals = [];
	for ($q = 1; $q <= 4; $q++) {
		if (isset($subjectData[$q]) && count($subjectData[$q]) > 0) {
			$qavg = avgArray($subjectData[$q]);
			$row['q'.$q] = round($qavg, 1);
			$quarterTotals[] = $qavg;
			foreach ($subjectData[$q] as $sval) {
				$allScores[] = $sval;
			}
		} else {
			$row['q'.$q] = null;
		}
	}
	$final = null;
	if (count($quarterTotals) > 0) {
		$final = round(array_sum($quarterTotals) / count($quarterTotals), 1);
	}
	$row['final'] = $final;
	$displayGrades[$subj] = $row;
}

// overall average: use the persisted students.avg_score (updated by teacher when grades are saved)
$overallAvgDisplay = '-';
if (isset($user['avg_score']) && $user['avg_score'] !== null && floatval($user['avg_score']) > 0) {
	$overallAvgDisplay = number_format(floatval($user['avg_score']), 1) . '%';
} else {
	// Fallback: calculate from individual grades if avg_score is not set
	if (count($allScores) > 0) {
		$overallAvgDisplay = round(array_sum($allScores) / count($allScores), 1) . '%';
	}
}
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
            <?php
              // determine weekday key used in teacher schedules (monday..friday)
              $weekday = strtolower(date('l')); // e.g. Monday
              $weekday_map = ['monday','tuesday','wednesday','thursday','friday'];
              $dayKey = in_array($weekday, ['monday','tuesday','wednesday','thursday','friday']) ? $weekday : 'monday';

              $studentSchedule = load_schedule_for($grade, $section);
              if ($studentSchedule && is_array($studentSchedule)):
                  $items = [];
                  foreach ($studentSchedule as $row) {
                      $time = $row['time'] ?? '';
                      $entry = $row[$dayKey] ?? ['teacher'=>'','subject'=>''];
                      $subject = trim($entry['subject'] ?? '');
                      $teacherName = trim($entry['teacher'] ?? '');
                      if ($subject !== '' || $teacherName !== '') {
                          $display = trim(($time ? $time . ' • ' : '') . ($subject ?: $teacherName) . ($teacherName && $subject ? ' • ' . $teacherName : ''));
                          $items[] = $display;
                      }
                  }
                  if (count($items) > 0):
            ?>
                    <ul class="schedule">
                      <?php foreach ($items as $it): ?>
                        <li><?php echo htmlspecialchars($it); ?></li>
                      <?php endforeach; ?>
                    </ul>
            <?php else: ?>
                    <div style="color:#666;">No classes scheduled for today in Grade <?php echo htmlspecialchars($grade); ?> Section <?php echo htmlspecialchars($section); ?>.</div>
            <?php
                  endif;
              else:
            ?>
                <div style="color:#666;">No schedule available for Grade <?php echo htmlspecialchars($grade); ?> Section <?php echo htmlspecialchars($section); ?>.</div>
            <?php endif; ?>
            <a href="schedule.php?grade=<?php echo urlencode($grade); ?>&section=<?php echo urlencode($section); ?>" class="link-more">View full schedule →</a>
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
            <ul class="ann-list">
              <?php
                // Fetch upcoming school events (latest 2)
                $upcomingEvents = $conn->query("SELECT event_date, title FROM school_events ORDER BY event_date DESC LIMIT 2");
                $eventCount = 0;
                
                if ($upcomingEvents) {
                  while ($event = $upcomingEvents->fetch_assoc()) {
                    $eventCount++;
                    $eventDate = date('M d, Y', strtotime($event['event_date']));
                    echo '<li><strong>' . htmlspecialchars($eventDate) . '</strong> — <span style="color: #0369a1; font-weight: 600;">📅</span> ' . htmlspecialchars($event['title']) . '</li>';
                  }
                }
                
                // Show fallback if no events
                if ($eventCount === 0) {
                  echo '<li><strong>Oct 20</strong> — 📅 Parent-Teacher Conference on Oct 20.</li>';
                  echo '<li><strong>Oct 15</strong> — 📅 School fee due date extended.</li>';
                }
              ?>
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

      const avatarInput = document.getElementById('avatarInput');
      const avatarImage = document.getElementById('avatarImage');
      const userId = <?php echo $userId; ?>;

      // Fix: proper .then parameter name and safe handling
      fetch('api/get-avatar.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
          if (data && data.avatar) {
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

      // --- NEW: Poll server for grade/section updates every 7 seconds ---
      const badgeEl = document.getElementById('badgeGrade');
      const profileSectionEl = document.getElementById('profileSection');
      const roleLineEl = document.getElementById('roleLine');

      function pollStudentInfo() {
        fetch('api/get-student-info.php', { credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => {
            if (!data || !data.success) return;
            const newGrade = String(data.grade || '');
            const newSection = String(data.section || '');
            // update badge
            if (newGrade && badgeEl) badgeEl.textContent = 'Grade ' + newGrade;
            // update profile section value
            if (newSection && profileSectionEl) profileSectionEl.textContent = newSection;
            // update role/line (keep student id)
            if (roleLineEl) {
              roleLineEl.innerHTML = 'Section ' + (newSection || '<?php echo $section; ?>') + ' • Student ID: <strong><?php echo $studentIdDisplay; ?></strong>';
            }
          })
          .catch(err => {
            // silent fail
            // console.log('poll error', err);
          });
      }

      // initial poll + interval
      pollStudentInfo();
      setInterval(pollStudentInfo, 7000);

    })();

    // Toggle subject details when subject row clicked
    document.addEventListener('click', function (e) {
      const row = e.target.closest('.subject-row');
      if (row) {
        const subject = row.dataset.subject;
        const details = document.querySelectorAll('.subject-details[data-subject="' + CSS.escape(subject) + '"]');
        details.forEach(d => d.classList.toggle('hidden'));
      }
    });

    // small helper to ensure CSS.escape exists in older browsers
    if (typeof CSS === 'undefined' || typeof CSS.escape === 'undefined') {
      // basic polyfill
      (function(global){
        var s = /[^\w-]/g;
        if (!global.CSS) global.CSS = {};
        global.CSS.escape = function(value){ return String(value).replace(s, '\\$&'); };
      })(window);
    }
  </script>
</body>
</html>
