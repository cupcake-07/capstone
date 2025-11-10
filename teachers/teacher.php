<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in as teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

// Fetch total enrolled students
$totalStudentsResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE is_enrolled = 1");
$totalStudents = $totalStudentsResult->fetch_assoc()['count'];

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');

// --- EARLY: Load all schedules once ---
$allSchedules = [];
$dataFile = __DIR__ . '/data/schedules.json';
if (file_exists($dataFile)) {
    $allSchedules = json_decode(file_get_contents($dataFile), true) ?: [];
}
// --- END early load ---

// --- START: replace simplified teacher info with DB-driven grade/sections and subjects ---
$teacherId = intval($_SESSION['user_id'] ?? 0);
$gradeSectionDisplay = 'Managed by admin';
$subjectsAssigned = 'Not assigned';
$gradeRaw = '';
$sectionsRaw = '';
$subjectRaw = '';

if ($teacherId > 0) {
    $tq = $conn->prepare("SELECT grade, sections, subject FROM teachers WHERE id = ? LIMIT 1");
    if ($tq) {
        $tq->bind_param('i', $teacherId);
        $tq->execute();
        $tr = $tq->get_result();
        if ($tr && $tr->num_rows === 1) {
            $trow = $tr->fetch_assoc();
            $gradeRaw = trim((string)($trow['grade'] ?? ''));
            $sectionsRaw = trim((string)($trow['sections'] ?? ''));
            $subjectRaw = trim((string)($trow['subject'] ?? ''));

            // Build Grade & Sections display if available
            $parts = [];
            if ($gradeRaw !== '') {
                $parts[] = 'Grade ' . $gradeRaw;
            }
            if ($sectionsRaw !== '') {
                $sArr = array_map('trim', explode(',', $sectionsRaw));
                $sArr = array_values(array_filter($sArr, function($v){ return $v !== ''; }));
                if (!empty($sArr)) {
                    $parts[] = 'Sections ' . implode(', ', $sArr);
                }
            }
            if (!empty($parts)) {
                $gradeSectionDisplay = implode(' — ', $parts);
            }

            // Subjects assigned (existing behavior, now reads from DB)
            if ($subjectRaw !== '') {
                $partsS = array_map('trim', explode(',', $subjectRaw));
                $partsS = array_values(array_filter($partsS, function($v){ return $v !== ''; }));
                if (!empty($partsS)) {
                    $subjectsAssigned = count($partsS) . ' subject(s) — ' . implode(', ', array_slice($partsS, 0, 3));
                }
            }
        }
        $tq->close();
    }
}
// --- END: replace simplified teacher info ---


// Build schedule display: load schedules.json and render summary for the teacher's assigned grade/sections
$scheduleDisplay = 'No schedule available.';
if (!empty($gradeRaw) && !empty($allSchedules)) {
    // ensure sections array exists
    $sectionsArr = [];
    if (!empty($sectionsRaw)) {
        $sectionsArr = array_values(array_filter(array_map('trim', explode(',', $sectionsRaw)), function($v){ return $v !== ''; }));
    } else {
        // fallback: if teacher has only a grade, try default section A
        $sectionsArr = ['A'];
    }

    $rendered = [];
    foreach ($sectionsArr as $sec) {
        $key = $gradeRaw . '_' . $sec;
        if (isset($allSchedules[$key]) && is_array($allSchedules[$key])) {
            $sched = $allSchedules[$key];
            $tbl = '<div style="max-width:720px;overflow:auto;"><strong>Grade '.htmlspecialchars($gradeRaw).' - Section '.htmlspecialchars($sec).'</strong>';
            $tbl .= '<table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:13px;">';
            $tbl .= '<thead><tr>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Period</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Time</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Mon</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Tue</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Wed</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Thu</th>'
                  . '<th style="border:1px solid #ddd;padding:6px;text-align:left">Fri</th>'
                  . '</tr></thead><tbody>';
            foreach ($sched as $row) {
                $p = htmlspecialchars($row['period'] ?? '');
                $t = htmlspecialchars($row['time'] ?? '');
                $cells = [];
                foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
                    $teacherName = htmlspecialchars($row[$d]['teacher'] ?? '');
                    $subjectName = htmlspecialchars($row[$d]['subject'] ?? '');
                    $cells[] = trim($teacherName . ($subjectName ? ' — ' . $subjectName : '')) ?: '&nbsp;';
                }
                $tbl .= '<tr>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$p.'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$t.'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$cells[0].'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$cells[1].'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$cells[2].'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$cells[3].'</td>'
                      . '<td style="border:1px solid #eee;padding:6px;vertical-align:top;">'.$cells[4].'</td>'
                      . '</tr>';
            }
            $tbl .= '</tbody></table></div>';
            $rendered[] = $tbl;
        }
    }
    if (!empty($rendered)) {
        $scheduleDisplay = implode('<br>', $rendered);
    }
}

// --- NEW: determine current / upcoming class for logged-in teacher (today) ---
$todayStatus = 'No classes right now.';
$userRaw = trim($_SESSION['user_name'] ?? '');
if (!empty($userRaw) && !empty($allSchedules)) {
    $todayKey = strtolower(date('l')); // 'monday'..'friday'
    $now = time();
    $activeFound = false;
    $nextStart = PHP_INT_MAX;
    $nextClass = null;

    // Scan ALL schedules for any class where this teacher teaches today
    foreach ($allSchedules as $scheduleKey => $sched) {
        if (!is_array($sched)) continue;
        foreach ($sched as $row) {
            $teacherNameCell = trim((string)($row[$todayKey]['teacher'] ?? ''));
            if ($teacherNameCell === '') continue;
            
            // case-insensitive match
            if (strcasecmp(trim($teacherNameCell), trim($userRaw)) !== 0) continue;

            $timeStr = trim((string)($row['time'] ?? ''));
            if ($timeStr === '') continue;
            
            // split start/end on first '-'
            $parts = array_map('trim', explode('-', $timeStr, 2));
            if (count($parts) < 2) continue;
            $startStr = $parts[0];
            $endStr = $parts[1];

            // parse using strtotime
            $startTs = strtotime($startStr);
            $endTs = strtotime($endStr);
            
            if ($startTs === false || $endTs === false) continue;

            // normalize if end earlier than start (assume next day)
            if ($endTs <= $startTs) $endTs += 24*3600;

            if ($startTs <= $now && $now <= $endTs) {
                // active now
                $subject = trim((string)($row[$todayKey]['subject'] ?? ''));
                $grade = substr($scheduleKey, 0, strpos($scheduleKey, '_'));
                $section = substr($scheduleKey, strpos($scheduleKey, '_') + 1);
                $todayStatus = 'You have a class now: ' . ($subject ? htmlspecialchars($subject).' — ' : '') . 'Grade '.htmlspecialchars($grade).' Section '.htmlspecialchars($section).' ('.htmlspecialchars($timeStr).')';
                $activeFound = true;
                break 2;
            }

            if ($startTs > $now && $startTs < $nextStart) {
                $nextStart = $startTs;
                $grade = substr($scheduleKey, 0, strpos($scheduleKey, '_'));
                $section = substr($scheduleKey, strpos($scheduleKey, '_') + 1);
                $nextClass = [
                    'start' => $startTs,
                    'timeStr' => $timeStr,
                    'subject' => trim((string)($row[$todayKey]['subject'] ?? '')),
                    'section' => $section,
                    'grade' => $grade
                ];
            }
        }
    }

    if (!$activeFound && $nextClass) {
        $startDisplay = date('g:i A', $nextClass['start']);
        $todayStatus = 'Upcoming class at '.htmlspecialchars($startDisplay).': '.($nextClass['subject'] ? htmlspecialchars($nextClass['subject']).' — ' : '') . 'Grade '.htmlspecialchars($nextClass['grade']).' Section '.htmlspecialchars($nextClass['section']) . ' ('.htmlspecialchars($nextClass['timeStr']).')';
    }
}
// --- END new logic ---
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard Elegant View</title>
  <link rel="stylesheet" href="teacher.css" />
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
        <span><?php echo $user_name; ?></span>
        <a href="../login.php">
        <img src="loginswitch.png" id="loginswitch"></img></a>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php" class="active">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>      
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Dashboard</h1>
      </header>

      <section class="cards" id="dashboard">
        <div class="card">
          <div class="card-title">Total Students</div>
          <div class="card-value" id="totalstudents"><?php echo $totalStudents; ?></div>
        </div>
        <div class="card">
          <div class="card-title">Grade and Section</div>
          <div class="card-value" id="gradesection"><?php echo htmlspecialchars($gradeSectionDisplay); ?></div>
        </div>
        <div class="card">
          <div class="card-title">Subjects Assigned</div>
          <div class="card-value" id="subjectassigned"><?php echo htmlspecialchars($subjectsAssigned); ?></div>
        </div>

        <!-- New card showing current/upcoming class status -->
        <div class="card">
          <div class="card-title">Now / Upcoming</div>
          <div class="card-value" id="classstatus"><?php echo $todayStatus; ?></div>
        </div>

        <div class="card">
          <div class="card-title">Schedule</div>
          <div class="card-value" id="schedule"><?php echo $scheduleDisplay; ?></div>
        </div>

        <div class="card">
          <div class="card-title">Announcements</div>
          <div class="card-value" id="announcements">No new announcements</div>
        </div>
      </section>
    </main>
    
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Contact Us</h3>
          <p>123 Faith Avenue</p>
          <p>Your City, ST 12345</p>
          <p>Phone: (555) 123-4567</p>
          <p>Email: info@gloriousgod.edu</p>
        </div>
        <div class="footer-section">
          <h3>Connect With Us</h3>
          <div class="social-links">
            <a href="#" aria-label="Facebook">
              <svg xlmns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-facebook">
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
              </svg>
            </a>
            <a href="#" aria-label="Instagram">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-instagram">
                <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.5" y1="6.5" y2="6.5"/>
              </svg>
            </a>
            <a href="#" aria-label="Twitter">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-twitter">
                <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2 1.7-1.4 1.2-4-1.2-5.4l-.4-.4a7.9 7.9 0 0 0-1.7-1.1c1.5-1.4 3.7-2 6.5-1.6 3-1.6 5.5-2.8 7.3-3.6 1.8.8 2.6 2.2 2.6 3.6z"/>
              </svg>
            </a>
          </div>
        </div>
        <div class="footer-section">
            <h3>System Info</h3>
            <p>Schoolwide Management System</p>
            <p>Version 1.0.0</p>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2025 Glorious God Family Christian School. All rights reserved.</p>
        <div class="footer-links">
          <a href="privacy.php">Privacy Policy</a> |
          <a href="terms.php">Terms of Service</a>
        </div>
        <footer class="footer">© <span id="year">2025</span> Schoolwide Management System</footer>
      </div>
    </footer>
    <script>
        // Update the year in the footer
        document.getElementById('year').textContent = new Date().getFullYear();
    </script> 
  </body>
</html>
